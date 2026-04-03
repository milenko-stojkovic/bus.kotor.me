# Admin panel – funkcionalnosti

Specifikacija admin funkcionalnosti. Modeli: Reservation, TempData, DailyParkingData, ListOfTimeSlot, ReportEmail, system_config.

**Implementirano (van opšte specifikacije ispod):** pregled i akcije za **`late_manual_review`** / povezane statuse — `App\Http\Controllers\Admin\LateSuccessController` (lista, detalj, **force create** rezervacije, **reject**). Rute pod admin middleware-om (v. `routes/web.php`).

**Control panel** (šalter / dolasci) je **odvojen** proizvodni tok: guard **`control`**, model **`App\Models\Admin`**, rute pod **`/control`** — v. **[control-panel.md](./control-panel.md)**. Klasični admin ovde i dalje znači **`User`** + uloga / `AdminMiddleware` na **`/admin`**.

---

## 1. Rezervacije

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Pregled rezervacija** | Lista svih rezervacija (filteri: datum, status, guest/user). | `Reservation` |
| **Naknadna rezervacija** | Kreiranje rezervacije od strane admina (npr. telefonski / na šalteru). | `Reservation`, `DailyParkingData` (kapacitet) |
| **Besplatna rezervacija** | Kreiranje rezervacije sa statusom `free` (bez plaćanja). | `Reservation.status` = `'free'` |
| **Pristup i izmena napravljene rezervacije** | Pogled i izmena rezervacije u statusu `late_success` (već upisane). | `Reservation`, `TempData` (ako još postoji) |
| **Izmene termina** | Izmena `drop_off_time_slot_id`, `pick_up_time_slot_id`, `reservation_date` na postojećoj rezervaciji. | `Reservation`, validacija preko `DailyParkingData` |
| **Promena statusa rezervacija** | Menjanje `status` (npr. paid / free). | `Reservation.status` |

---

## 2. Blokiranje termina i dana

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Blokiranje/deblokiranje dana** | Za određeni datum onemogućiti/omogućiti rezervacije (npr. postavljanjem kapaciteta na 0 ili posebnim flagom ako se doda). | `DailyParkingData` po datumu (svi slotovi za taj dan) ili nova kolona/tabela po potrebi |
| **Blokiranje/deblokiranje time slot-a** | Za određeni datum i jedan slot (ili grupu slotova) onemogućiti/omogućiti rezervacije. | `DailyParkingData`: `capacity` = 0 za blokadu, ili `reserved`/posebna logika |

Napomena: trenutno kapacitet i dostupnost kontrolišu se preko `DailyParkingData` (capacity, reserved, pending). Blokiranje može biti: postavljanje `capacity` na 0 za taj datum+slot, ili dodavanje posebne tabele/kolone (npr. `is_blocked`) ako treba razdvojiti “nema mesta” od “admin blokirao”.

---

## 3. Temp data i statusi

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Promena statusa temp_data** | Izmena `status`: `pending`, `failed`, `late_success`. | `TempData.status` (`TempData::STATUS_*`) |
| **Promena statusa reservations** | Izmena `Reservation->status` (paid / free). | `Reservation.status` |

---

## 4. E-mailovi i izveštaji

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Ažuriranje e-mailova za izveštaje** | CRUD za adrese na koje se šalju obaveštenja/izveštaji. | `ReportEmail` (tabela `report_emails`) |

---

## 5. Sistemska konfiguracija

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Broj slotova (kapacitet)** | Izmena vrednosti “broj dostupnih mesta” koja se koristi za nove dane/slotove. | `system_config`, polje `available_parking_slots` (integer). Koristi se u seederu i pri logici kapaciteta. |

Napomena: `system_config` ima `name` (unique) i `value` (integer). Za admin formu: prikaz/izmena reda gde je `name = 'available_parking_slots'`.

---

## 6. Istorija plaćanja (registrovani korisnici)

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Pristup history plaćanja** | Admin vidi istoriju rezervacija za registrovane korisnike (po `user_id`). | `Reservation` gde je `user_id` set; prikaz po user-u (npr. izbor korisnika ili pregled jednog korisnika). |

Guest rezervacije (`user_id` = null) nemaju “istoriju po korisniku”; mogu se pretraživati po email-u, tablici, datumu itd.

---

## 7. Tehnički predlog (Laravel)

- **Rute:** sve pod prefiksom npr. `admin/`, middleware `auth` + admin guard ili `role:admin`.
- **Kontroleri:** npr. `App\Http\Controllers\Admin\ReservationController`, `TempDataController`, `ReportEmailController`, `SystemConfigController`, `DailyParkingDataController` (blokiranje).
- **Modeli:** `Reservation`, `TempData`, `DailyParkingData`, `ListOfTimeSlot`, `ReportEmail`; za `system_config` opciono model `SystemConfig` sa helperom za `available_parking_slots`.
- **Politike:** provera da je trenutni user admin pre pristupa ovim akcijama.

Ovaj dokument služi kao referenca za implementaciju admin panela; pojedinačne funkcionalnosti mogu se realizovati korak po korak.
