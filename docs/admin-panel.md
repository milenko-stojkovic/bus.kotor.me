# Admin panel – funkcionalnosti

Specifikacija admin funkcionalnosti. Modeli: Reservation, TempData, DailyParkingData, ListOfTimeSlot, ReportEmail, system_config.

## Dva odvojena „admin“ toka (2026-04)

| Šta | URL prefiks | Auth | Namena |
|-----|-------------|------|--------|
| **Glavni admin panel** | `/admin` | Guard **`panel_admin`**, tabela **`admins`**, kolona **`admin_access=1`** (i **`control_access=0`**) | Upozorenja/Informacije (`admin_alerts`), navigacija ka budućim modulima (Blokiranje, …). Login: **`GET /admin/login`**. |
| **Staff operativa** (rezervacije, late-success) | `/staff` | **`User`** + **`AdminMiddleware`** (uloga admin ili email u `admins`) | `ReservationListController`, `LateSuccessController` — v. `routes/web.php` imena **`staff.*`**. |

**Control panel** (šalter / dolasci): guard **`control`**, **`/control`** — v. **[control-panel.md](./control-panel.md)**. **`admin_access`** i **`control_access`** su međusobno isključivi; isti red u `admins` nikad ne drži oba = 1 (v. migracija + `Admin::booted`).

**Tabela `admin_alerts`:** operativna lista upozorenja (ne inbox); incident **SUCCESS posle `canceled`** upisuje se u **`admin_alerts`** preko **`AdminFiscalizationAlertService::notifyPaymentSuccessAfterCanceled`** (uz postojeći email).

---

**Implementirano (van opšte specifikacije ispod):** pregled i akcije za **`late_manual_review`** / povezane statuse — `App\Http\Controllers\Admin\LateSuccessController` (lista, detalj, **force create** rezervacije, **reject**). Rute su pod prefiksom **`/staff`**, middleware **`admin`** (v. `routes/web.php`).

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
| **Blokiranje/deblokiranje dana** | Admin blokira dan/termine bez menjanja `capacity/reserved/pending`. Blokada sprečava novu prodaju preko `daily_parking_data.is_blocked`. | `DailyParkingData.is_blocked` + `block_zone_worklist` |
| **Rezervacije u blok zoni (worklist)** | Slotovi sa postojećim `reserved>0` ili `pending>0` ne postaju odmah blokirani; ulaze u listu za ručno prilagođavanje. | `block_zone_worklist` |

Napomena: blokiranje je **odvojeno** od kapaciteta. `availableCapacity()` i `pending/reserved` semantika ostaju iste; UI/checkout samo dodatno tretira `is_blocked=1` kao nedostupno.

Rute (admin panel):
- `GET /admin/blokiranje` (`panel_admin.blocking`)
- `POST /admin/blokiranje` (`panel_admin.blocking.apply`)
- `GET /admin/blokiranje/dan/{date}` (`panel_admin.blocking.day`)
- `POST /admin/blokiranje/dan/apply` (`panel_admin.blocking.unblock.apply`)
- `GET|POST /admin/blokiranje/worklist/{row}/prilagodi` (prilagođavanje rezervacije)

**`reservations.created_by_admin`:** boolean, default `false` (signal za budući modul „Besplatne rezervacije“ / admin-kreirane free rezervacije). Postojeći tokovi kreiranja rezervacije eksplicitno postavljaju `false`. **Migracija:** `2026_04_11_120000_add_created_by_admin_to_reservations_table.php`.

**Prilagođavanje u blok zoni:** UI datum koristi prefiltar dana (minimum dva teorijski slobodna mesta tog dana — **nije** konačna garancija). Odlučujuća provera novih slotova (`!is_blocked`, `pending=0`, kapacitet; isti slot ID jednom) radi se **posle** `lockForUpdate` na relevantnim `daily_parking_data` redovima (`BlockingController` + `BlockReservationAdjustmentValidator`). Ako finalna validacija padne, nema delimičnih izmena. Posle uspešnog `Primeni` (blok/deblok) ili prilagođavanja redirect nosi `_fresh=timestamp` radi osvežavanja prikaza (bez auto-refresh tokom rada).

**Testovi (izbor):** `tests/Feature/AdminPanel/BlockReservationHardeningTest.php` (default kolone, post-lock odbijanje, `_fresh` na Primeni).

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
