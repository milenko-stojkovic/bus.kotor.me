# Cron / scheduled commands – spisak

Svi cron job-ovi su Laravel Artisan komande.

- **Local SAFE schedule**: `routes/console.php` (bez real bank/fiscal poziva)
- **Production-only schedule**: `bootstrap/app.php` preko `withSchedule()` (bank/fiscal komande pod `app()->environment('production')`)

Puna tabela rasporeda: **`docs/scheduled-tasks-overview.md`**.

**Napomena:** `temp_data` se zadržava kao **audit trail** — uspešno plaćanje **ne briše** red (status `processed`).

---

## 1. ProcessPendingReservations

**Komanda:** `reservations:process-pending`

**Opis:** Namenjeno obradi `pending` redova (npr. naknadna fiskalizacija). **Trenutno je komanda u velikoj meri stub** — v. `ProcessPendingReservations.php`. Poslovno pravilo: **`temp_data` se ne briše** na uspehu u glavnom payment toku (status `processed`); bilo kakvo buduće „čišćenje“ mora biti usklađeno sa `docs/workflow-placanje-temp-data.md`.

**Frekvencija:** npr. svakih 5 minuta.

**Tabele:** temp_data, reservations, post_fiscalization_data (ako se koristi za istoriju pokušaja).

---

## 1b. RetryPostFiscalization

**Komanda:** `post-fiscalization:retry`

**Opis:** Retry fiskalizacije za rezervacije iz **post_fiscalization_data** gde je **next_retry_at <= now**. Poziva fiskalni API; pri uspehu ažurira reservation fiscal_*, briše slog iz post_fiscalization_data i šalje kupcu **novi fiskalni PDF** i email. Pri neuspehu poveća attempts i postavi next_retry_at.

**Frekvencija:** svakih 10 minuta (bootstrap/app.php).

**Tabele:** post_fiscalization_data, reservations.

---

## 1c. CheckPendingPaymentStatus (timeout callback)

**Komanda:** `payment:check-pending-inquiry`

**Opis:** (1) Za **temp_data** u **pending** starije od **`payment.stale_pending_warn_after_minutes`** (npr. 12) — log **`payment_pending_too_long`** u `payments` (throttle keš po slogu; **bez promene statusa**). (2) Samo ako je **`PaymentStatusInquiryService::isImplemented()`** = true (Bankart + `BANKART_STATUS_INQUIRY_ENABLED` + kompletna konfiguracija): za pending starije od **`payment.pending_inquiry_after_minutes`** poziva **inquire()**; keš **throttle** po **`merchant_transaction_id`** (`payment.status_inquiry_throttle_minutes`). Rezultat **SUCCESS** / **ERROR** (Bankart `transactionStatus`) → **`PaymentCallbackJob`** sa istim payload semantikom kao webhook (**nije** direktan `PaymentSuccessHandler` iz komande).

**Frekvencija:** svakih 5 minuta (bootstrap/app.php).

**Tabele:** temp_data, reservations, daily_parking_data.

**Config:** `stale_pending_warn_after_minutes` (`PAYMENT_STALE_PENDING_WARN_AFTER_MINUTES`); `pending_inquiry_after_minutes` (`PAYMENT_PENDING_INQUIRY_AFTER_MINUTES`); `status_inquiry_throttle_minutes` (`PAYMENT_STATUS_INQUIRY_THROTTLE_MINUTES`); `bankart_status_inquiry_enabled` (`BANKART_STATUS_INQUIRY_ENABLED`).

---

## 2. ExpirePendingReservations

**Komanda:** `reservations:expire-pending`

**Opis:** Proverava temp_data slogove koji su **pending** duže od praga (`config/reservations.php` → `pending_expire_minutes`). Postavlja status **`expired`**, loguje tranziciju i **smanjuje `pending`** na `daily_parking_data` za **oba** time slota (v. `ExpirePendingReservations`).

**Frekvencija:** svakih 10 minuta.

**Tabele:** temp_data.

---

## 3. AssignLateSuccessReservations

**Komanda:** `reservations:assign-late-success`

**Opis:** Proverava temp_data slogove sa statusom **late_success**. **Trenutno stub** — v. `AssignLateSuccessReservations.php`. Planirani/automatski tok treba da bude usklađen sa admin flow-om (`LateSuccessController`) i politikom audit trail-a (`temp_data` se ne briše bez eksplicitnog pravila).

**Frekvencija:** po potrebi (ručno ili svakih 5–15 minuta).

**Tabele:** temp_data, reservations.

---

## 3b. SyncDailyParkingDays

**Komanda:** `parking:sync-days`

**Opis:** Osigurava pokrivenost `daily_parking_data` za tekući dan i narednih ~90 dana (`upsert`); briše redove za prošle datume. Ne menja namerno postojeće `reserved` / `pending` za žive redove na način koji krši poslovna pravila — v. `SyncDailyParkingDays`.

**Frekvencija:** dnevno u 00:05 (`bootstrap/app.php`).

---

## 4. UpdateDailyParkingAvailability

**Komanda:** `parking:update-availability`

**Opis:** Ažurira daily_parking_data prema napravljenim rezervacijama. Povećava **reserved** i smanjuje **pending** kada se rezervacija fiskalizuje. Može proveravati i resetovati kapacitet za novi datum ako je potrebno.

**Frekvencija:** svakih 5–10 minuta.

**Tabele:** daily_parking_data, reservations, temp_data.

---

## 5. SendReservationEmails

**Komanda:** `reservations:send-emails`

**Opis:** Proverava reservations gde **`email_sent = Reservation::EMAIL_NOT_SENT` (0)**. Šalje potvrdu rezervacije korisniku. Nakon slanja → **`EMAIL_SENT` (1)** preko **`markConfirmationEmailSent()`**. *(Stanje **EMAIL_SENDING** (2) koriste queue jobovi za lock — v. `SendInvoiceEmailJob`.)*

**Frekvencija:** svakih 5–10 minuta.

**Tabele:** reservations.

---

## 6. CleanupOldTempData

**Komanda:** `temp-data:cleanup`

**Opis:** Trenutna implementacija **ne briše** redove — `temp_data` se čuva za audit. Komanda je rezervisana za buduće arhiviranje ili metrike.

**Frekvencija:** dnevno.

**Tabele:** temp_data.

---

## Napomene

- Svaka komanda koristi Eloquent za status (pending, failed, late_success) i snapshot polja.
- Za pokretanje scheduler-a na serveru: `* * * * * php /path/to/artisan schedule:run` (cron entry).
- Lokalno: `php artisan schedule:work` ili `php artisan schedule:list`.
- Komande su u `app/Console/Commands/`.
- Raspored scheduler-a je podeljen:
  - **Local SAFE schedule**: `routes/console.php`
  - **Production-only schedule**: `bootstrap/app.php` → `withSchedule()`
- Config: `config/reservations.php` (pending_expire_minutes, temp_data_retention_days); env: `RESERVATIONS_PENDING_EXPIRE_MINUTES`, `TEMP_DATA_RETENTION_DAYS` (legacy: `RESERVATIONS_TEMP_DATA_RETENTION_DAYS`).

---

## Lokalni cron (Laragon Cronical)

Ovaj projekat koristi Laravel scheduler. Na Windows / Laragon-u se tipično koristi **Cronical** koji u pozadini izvršava cron entry.

### Gde je `cronical.dat`

- Podrazumevana putanja (Laragon): `C:\laragon\bin\cronical\cronical.dat`

### Cronical job (obavezno: `schedule:run` na minut)

Dodaj sledeći job u `cronical.dat`:

```
* * * * * cmd /c cd C:\laragon\www\bus.kotor.me && php artisan schedule:run >> NUL 2>&1
```

Ako `php` nije u PATH-u, koristi punu putanju do Laragon PHP:

```
* * * * * cmd /c cd C:\laragon\www\bus.kotor.me && C:\laragon\bin\php\php-8.x.x\php.exe artisan schedule:run >> NUL 2>&1
```

### Kako pokrenuti Cronical service

- U Laragon UI: **Menu → Cron → Start** (ili “Cronical” ako je tako imenovano)
- Provera da radi: pogledaj “Last run” u Cronical UI, ili proveri da se `schedule:run` izvršava svake minute.

### Šta je zakazano za “Kartica avansa”

Scheduler je dodat u `routes/console.php`:

- `advance:send-yearly-statements` se izvršava **1. januara u 10:00**

### Lokalno testiranje schedulera (dev only)

Privremeno izmeni u `routes/console.php`:

```php
Schedule::command('advance:send-yearly-statements')
    ->everyMinute();
```

Zatim sa uključenim Cronical job-om sačekaj 1–2 minuta i proveri:

- da se u logu `storage/logs/payments-*.log` pojavljuje `advance_yearly_statement_sent` ili `advance_yearly_statement_skipped`
- ili ručno pokreni `php artisan schedule:run` i proveri output/log

Nakon testa **vrati** scheduling na:

```php
Schedule::command('advance:send-yearly-statements')
    ->yearlyOn(1, 1, '10:00');
```

---

## Laravel scheduler / Laragon safe local schedule

### Cronical pokreće samo `schedule:run`

U `C:\laragon\bin\cronical\cronical.dat` dodati (svaki minut):

```
* * * * * cmd /c cd C:\laragon\www\bus.kotor.me && php artisan schedule:run >> NUL 2>&1
```

*(Ako `php` nije u PATH-u, vidi gore punu putanju.)*

### SAFE job-ovi koji su zakazani lokalno

Ovi job-ovi su dodati u `routes/console.php` i smatraju se bezbednim za lokalni dev scheduler jer ne kontaktiraju stvarnu banku niti fiskalni servis:

- `advance:send-yearly-statements` — **yearlyOn(1, 1, '10:00')**
  - Guard: ne radi ništa ako je `config('features.advance_payments') === false`
- `reservations:expire-pending` — **everyTenMinutes**
- `parking:sync-days` — **dailyAt('00:05')**
- `temp-data:cleanup` — **daily**

### EXCLUDED job-ovi (namerno nisu zakazani lokalno)

Sledeći job-ovi su **namerno izostavljeni** iz lokalnog scheduler-a (ne pojavljuju se u `schedule:list`) zbog rizika od realnih eksternih finansijskih/fiskalnih radnji:

- `post-fiscalization:retry`
  - **Reason**: poziva stvarni fiskalni servis i šalje fiskalni PDF / email nakon uspeha
- `payment:check-pending-inquiry`
  - **Reason**: radi bankarski inquiry prema stvarnoj banci (Bankart), može triggerovati payment state machine
- `reservations:process-pending`
  - **Reason**: u opisu je direktno vezano za naknadnu/stvarnu fiskalizaciju; iako delovi mogu biti stub, tretira se kao unsafe za lokalni scheduler

Sledeće komande su **nezakazane** jer frekvencija u dokumentu nije striktno definisana (navedeno je opseg ili “po potrebi”):

- `reservations:assign-late-success` — **Reason**: “po potrebi / 5–15 minuta” (nije striktna frekvencija)
- `parking:update-availability` — **Reason**: “svakih 5–10 minuta” (nije striktna frekvencija)
- `reservations:send-emails` — **Reason**: “svakih 5–10 minuta” (nije striktna frekvencija)

---

## Production readiness (bank/fiscal) — ne sme se zaboraviti

Sledeći scheduled job-ovi su kritični za produkciju jer imaju veze sa **stvarnim plaćanjem** i/ili **stvarnom fiskalizacijom**. Lokalno su **namerno isključeni iz scheduler-a** (SAFE schedule) da bi se izbegao rizik, ali pre izlaska u produkciju moraju biti:

- **implementirani i stabilni** (ako su označeni kao stub/TODO),
- **konfigurisani** (env, kredencijali, URL),
- **operativno provereni** (cron + queue worker + logovi/alerti),
- i jasno verifikovani kroz `schedule:list` + end-to-end scenarije.

### Komande koje zahtevaju production proveru

- `payment:check-pending-inquiry`
  - **Zašto je kritično**: radi real bank inquiry (Bankart) i može pokrenuti payment state machine (dispatch `PaymentCallbackJob`).
  - **Pre produkcije**: potvrditi da je inquiry bezbedan (throttle, idempotency), da su env/kredencijali validni, i da se ponašanje slaže sa `docs/payment-callback-handling.md` i `docs/payment-states.md`.

- `post-fiscalization:retry`
  - **Zašto je kritično**: radi realnu fiskalizaciju (poziva fiskalni servis), menja reservation fiscal_* i šalje fiskalni PDF/email.
  - **Pre produkcije**: potvrditi da su fiskalni env parametri validni, retry/backoff pravila i audit logovi rade, i da je email flow stabilan.

- `reservations:process-pending`
  - **Zašto je kritično**: namenjeno pipeline obradi i može uključiti fiskal tokove; u dokumentaciji je označeno da je delimično stub.
  - **Pre produkcije**: eksplicitno definisati šta tačno radi, završiti stub delove (ako postoje), i dodati operativnu proveru (logovi, metrika, alerti).

### Operativni minimum (pre produkcije)

- Cron (na serveru): `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
- Queue worker: stabilno pokrenut (u produkciji se bank/fiscal tokovi ne smeju oslanjati na `sync`).
- `storage/logs/payments-*.log` i alert email (ako je podešen) pokazuju očekivane događaje.

### Verifikacija

Na Windows/Laragon:

- `.\laragon-artisan.cmd schedule:list`

Proveri da:

- SAFE job-ovi gore postoje
- EXCLUDED job-ovi nisu prisutni
- `schedule:list` ne puca (posebno zbog cron izraza)
