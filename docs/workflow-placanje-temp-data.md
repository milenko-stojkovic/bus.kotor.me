# Workflow plaćanja i temp_data

Logika mora ostati konzistentna: temp_data kao soft lock, zatim upis u reservations i brisanje iz temp_data.

---

## Flow

### 1. Korisnik (ulogovan ili anoniman) krene plaćanje

- Upis u **temp_data**:
  - `merchant_transaction_id`
  - termini: `drop_off_time_slot_id`, `pick_up_time_slot_id`, `reservation_date`
  - vozilo / tip: `vehicle_type_id`, `license_plate`
  - podaci: `user_name`, `country`, `email`
  - **user_id** → `NULL` (guest) ili konkretan `id` (ulogovan)
  - `status` → `pending`

### 2. Plaćanje uspe

**Backend:**

1. Čita slog iz **temp_data** (po `merchant_transaction_id` ili id).
2. Pravi slog u **reservations**:
   - prepisuje **user_id** iz temp_data u reservations.user_id
   - prepisuje sva snapshot polja (user_name, country, license_plate, vehicle_type_id, email)
   - termini, datum, vehicle_id (ako ima), status, fiscal ako ima
3. **Briše slog iz temp_data** (da nema dupliranja).

**Rezultat:**

- Ulogovani korisnik ima istoriju (reservation.user_id set).
- Anoniman korisnik i dalje može da kupi rezervaciju (reservation.user_id null, sve u snapshot poljima).
- Admin može kasnije da “veže” rezervaciju ako baš mora (ručno ili posebnim flow-om).

### 3. Plaćanje ne uspe / cancel

- Ažuriraš **temp_data.status** u `failed` (ili ostaviš `pending` i cron/cleanup ga obradi).
- Po pravilu: **failed / cancel → brišeš slog** iz temp_data da nema zombi-podataka.

---

## Cron i temp_data

Ako imaš cron koji čačka temp_data:

| Status | Akcija |
|--------|--------|
| **late_success** | user_id **ostaje** u temp_data dok admin ne napravi rezervaciju “u ime korisnika” (ako se taj flow koristi). Inače se slog i ovde može obrisati nakon što je rezervacija kreirana. |
| **failed / cancel** | **Brišeš slog** → nema zombi-podataka. |

Mali ali bitan detalj: ako late_success služi da admin kasnije ručno kreira rezervaciju iz tog sloga, ne briši ga dok admin ne potvrdi ili dok ne istekne neki timeout.

---

## Mini-checklista temp_data (finalna verzija kolona)

| Kolona | Tip (MySQL) | Napomena |
|--------|-------------|----------|
| id | INT UNSIGNED | PK, auto increment |
| merchant_transaction_id | VARCHAR(64) UNIQUE | Jedinstven po transakciji |
| user_id | BIGINT UNSIGNED NULL | Guest = NULL, ulogovan = users.id |
| drop_off_time_slot_id | INT UNSIGNED | FK list_of_time_slots |
| pick_up_time_slot_id | INT UNSIGNED | FK list_of_time_slots |
| reservation_date | DATE | |
| user_name | VARCHAR(255) | Snapshot |
| country | VARCHAR(100) | Snapshot |
| license_plate | VARCHAR(50) | Snapshot |
| vehicle_type_id | INT UNSIGNED | FK vehicle_types |
| email | VARCHAR(255) | Snapshot |
| status | ENUM('pending', 'failed', 'late_success') | pending = čekanje, failed = neuspeh, late_success = uspeh (rezervacija može biti kreirana kasnije) |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

Sve kolone u checklisti postoje u migracijama (create_temp_data + add_user_id_to_temp_data). Nemoj brisati user_id iz temp_data; potreban je za prepis u reservations i za admin “u ime korisnika”.
