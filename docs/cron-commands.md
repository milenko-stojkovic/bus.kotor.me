# Cron / scheduled commands – spisak

Svi cron job-ovi su Laravel Artisan komande. Registruju se u `bootstrap/app.php` preko `withSchedule()`. Prioritet: prvo fiskalizacija, zatim parking slot update, potom email potvrde.

---

## 1. ProcessPendingReservations

**Komanda:** `reservations:process-pending`

**Opis:** Proverava sve slogove u temp_data sa statusom **pending**. Pokušava naknadnu fiskalizaciju za svaki slog koji još nije fiskalizovan. Ako fiskalizacija uspe → upisuje fiscal_jir, fiscal_ikof, fiscal_qr, fiscal_operator, fiscal_date u reservations, briše slog iz temp_data. Ako ne uspe → ostaje pending ili se markira **failed** posle X pokušaja.

**Frekvencija:** npr. svakih 5 minuta.

**Tabele:** temp_data, reservations, post_fiscalization_data (ako se koristi za istoriju pokušaja).

---

## 2. ExpirePendingReservations

**Komanda:** `reservations:expire-pending`

**Opis:** Proverava temp_data slogove koji su **pending** duže od definisanog vremena (npr. 30 minuta). Menja status u **failed** ili briše slog automatski.

**Frekvencija:** svakih 10 minuta.

**Tabele:** temp_data.

---

## 3. AssignLateSuccessReservations

**Komanda:** `reservations:assign-late-success`

**Opis:** Proverava temp_data slogove sa statusom **late_success**. Omogućava adminu da ažurira datum, drop-off i pick-up. Upisuje nove termine u reservations koristeći snapshot iz temp_data. Briše slog iz temp_data kad je naknadna rezervacija uspešno kreirana.

**Frekvencija:** po potrebi (ručno ili svakih 5–15 minuta).

**Tabele:** temp_data, reservations.

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

## 6. CleanupOldTempData (opciono)

**Komanda:** `temp-data:cleanup`

**Opis:** Briše slogove u temp_data starije od X dana koji nisu više relevantni. Pomaže u održavanju čistoće baze.

**Frekvencija:** dnevno.

**Tabele:** temp_data.

---

## Napomene

- Svaka komanda koristi Eloquent za status (pending, failed, late_success) i snapshot polja.
- Za pokretanje scheduler-a na serveru: `* * * * * php /path/to/artisan schedule:run` (cron entry).
- Lokalno: `php artisan schedule:work` ili `php artisan schedule:list`.
- Komande su u `app/Console/Commands/`. Raspored je u `bootstrap/app.php` → `withSchedule()`.
- Config: `config/reservations.php` (pending_expire_minutes, temp_data_retention_days); opciono env `RESERVATIONS_PENDING_EXPIRE_MINUTES`, `RESERVATIONS_TEMP_DATA_RETENTION_DAYS`.
