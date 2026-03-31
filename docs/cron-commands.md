# Cron / scheduled commands – spisak

Svi cron job-ovi su Laravel Artisan komande. Registruju se u `bootstrap/app.php` preko `withSchedule()`. Puna tabela rasporeda: **`docs/scheduled-tasks-overview.md`**.

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

**Opis:** Proverava **temp_data** sa statusom **pending** starije od X minuta (config `payment.pending_inquiry_after_minutes`, npr. 10). Za svaki poziva **status inquiry** kod banke. Ako banka kaže **SUCCESS** → pokreće **isti flow kao callback** (PaymentSuccessHandler: transakcija, kreiranje rezervacije, temp_data → processed, oslobađanje soft-locka, ProcessReservationAfterPaymentJob). Scenario: callback od banke nikad ne stigne (mreža, firewall, outage).

**Frekvencija:** svakih 5 minuta (bootstrap/app.php).

**Tabele:** temp_data, reservations, daily_parking_data.

**Config:** `config/payment.php` → `pending_inquiry_after_minutes`; env `PAYMENT_PENDING_INQUIRY_AFTER_MINUTES`.

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

**Opis:** Proverava reservations gde **email_sent = 0**. Šalje potvrdu rezervacije korisniku. Nakon slanja → **email_sent = 1**.

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
- Komande su u `app/Console/Commands/`. Raspored je u `bootstrap/app.php` → `withSchedule()`.
- Config: `config/reservations.php` (pending_expire_minutes, temp_data_retention_days); opciono env `RESERVATIONS_PENDING_EXPIRE_MINUTES`, `RESERVATIONS_TEMP_DATA_RETENTION_DAYS`.
