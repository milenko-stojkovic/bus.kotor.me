# Admin panel – funkcionalnosti

Specifikacija admin funkcionalnosti. Modeli: Reservation, TempData, DailyParkingData, ListOfTimeSlot, ReportEmail, system_config.

## Dva odvojena „admin“ toka (2026-04)

| Šta | URL prefiks | Auth | Namena |
|-----|-------------|------|--------|
| **Glavni admin panel** | `/admin` | Guard **`panel_admin`**, tabela **`admins`**, kolona **`admin_access=1`** (i **`control_access=0`**) | Dashboard **Upozorenja / Informacije** (`admin_alerts`, pregled nedostupnosti i blokada), navigacija (Blokiranje, Besplatne rezervacije, …). Login: **`GET /admin/login`**. |
| **Staff operativa** (rezervacije, late-success) | `/staff` | **`User`** + **`AdminMiddleware`** (uloga admin ili email u `admins`) | `ReservationListController`, `LateSuccessController` — v. `routes/web.php` imena **`staff.*`**. |

**Control panel** (šalter / dolasci): guard **`control`**, **`/control`** — v. **[control-panel.md](./control-panel.md)**. **`admin_access`** i **`control_access`** su međusobno isključivi; isti red u `admins` nikad ne drži oba = 1 (v. migracija + `Admin::booted`).

**Tabela `admin_alerts`:** operativna lista upozorenja (ne inbox); incident **SUCCESS posle `canceled`** upisuje se u **`admin_alerts`** preko **`AdminFiscalizationAlertService::notifyPaymentSuccessAfterCanceled`** (uz postojeći email).

### Dashboard `GET /admin` (`panel_admin.dashboard`)

Kontroler: **`WarningsController::index`**. Stranica ima tri bloka: **Upozorenja**, **Nedostupni dani i termini**, **Blokirani dani i termini** (meta refresh 300 s za operativni pregled).

**Blokirani dani i termini**

- Izvor: **`daily_parking_data.is_blocked = 1`**.
- Opseg: samo datumi koji postoje u tabeli i **`date >= danas`** (nema proizvoljnog „+90 dana“ skeniranja praznih dana).
- Grupisanje po danu; uzastopni blokirani slotovi (po rastućem `time_slot_id`, uzastopni celi brojevi) spajaju se u jedan raspon: **početak prvog termina – kraj poslednjeg** (parsiranje stringa `time_slot`, v. **`DaySlotRangeSummaryBuilder`**).
- Ako je blokiran **ceo katalog** slotova za taj dan → prikaz datuma sa oznakom **„— blokiran”** (bez liste intervala).
- Ne zavisi od free/plaćeno niti od broja rezervacija — opisuje samo administrativnu blok zonu. Link **Deblokiraj** vodi na **`panel_admin.blocking.day`**.

**Nedostupni dani i termini**

- Izvor istine: da li se termin **može kupiti** u smislu iste provere kao pri zaključavanju u checkout-u (**`CheckoutController::store`**, transakcija + `lockForUpdate`): za svaki slot iz **`list_of_time_slots`** nedostupan je ako **nema** reda u `daily_parking_data` za taj datum, ili je **`is_blocked`**, ili je **`availableCapacity() < 1`** (uključuje **`pending`**).
- Opseg datuma: kao i gore — **distinct datumi iz `daily_parking_data` sa `date >= danas`**.
- **Uključuje i blokirane** termine (oni su istovremeno i u sekciji Blokirani); ovo je zbir „trenutno se ne može kupiti“.
- Grupisanje i spajanje raspona: isti **`DaySlotRangeSummaryBuilder`**. Ceo dan nedostupan (svi slotovi kataloga) → **„— nedostupan”**.

**Zajednička logika spajanja**

- Klasa **`App\Services\AdminPanel\Blocking\DaySlotRangeSummaryBuilder`**: ulaz = pun skup slotova (redosled kao u `allSlots()`) + lista ID-jeva „označenih“ slotova; izlaz = **`is_full_day`** (ceo katalog pokriven) ili lista stringova raspona.

**Servis:** **`BlockingService::blockedDaySummaries()`**, **`unavailableForPurchaseDaySummaries()`**. Lista na stranici **Blokiranje** (`/admin/blokiranje`) koristi isti **`blockedDaySummaries()`**.

**Testovi:** `tests/Feature/AdminPanel/AdminWarningsDashboardTest.php`, `tests/Unit/DaySlotRangeSummaryBuilderTest.php`.

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

### 1.1 Besplatne rezervacije (admin panel) — implementirano

| Ruta | Namena |
|------|--------|
| `GET /admin/besplatne-rezervacije` | `panel_admin.free-reservations` — forma (korak kao gost + polja za snapshot). |
| `POST /admin/besplatne-rezervacije` | `panel_admin.free-reservations.store` — kreiranje. |

- **Kontroler:** `App\Http\Controllers\AdminPanel\FreeReservationController`; **validacija:** `AdminFreeReservationRequest`.
- **Podaci stranice / slotovi:** `ReservationBookingPageData::forAdminPanel()` — isti `buildSlotPayload` / `FreeReservationRules` kao gost; UI jezik fiksno **cg** (`App::setLocale('cg')` u kontroleru).
- **Bez `temp_data`:** `App\Services\AdminPanel\FreeReservation\AdminDirectFreeReservationService` u transakciji zaključava `daily_parking_data` po `whereDate` + `time_slot_id`, proverava `!is_blocked` i `availableCapacity() >= 1`, kreira `Reservation` (`status=free`, `created_by_admin=true`, `user_id=null`, `preferred_locale=cg`, `invoice_amount` preko `ReservationInvoiceAmount`), **increment `reserved`** po jedinstvenom slotu (isti ID jednom), zatim `SendFreeReservationConfirmationJob`.
- **Worklist:** `BlockZoneWorklistService::onReservationCreated($reservation, null)` ako postoji red po istom `merchant_transaction_id` (retko za novi UUID).
- **Konflikt termina:** `AdminFreeReservationSlotsUnavailableException` → redirect na istu stranu sa query parametrima za `name`, `country`, `license_plate`, `email`, `vehicle_type_id` (bez datuma/termina) + flash `error`.
- **Uspeh:** redirect na praznu stranu + flash `status`; forma bez starog unosa.

**`created_by_admin`:** u ovom toku uvek `true`; ostali tokovi i dalje `false` (v. §2 ispod).

**UI (label tipa vozila):** u dropdown-u za tip vozila prikazuje se lokalizovani naziv + opis + cena kao **`Naziv (Opis) - Cena`**. Opis dolazi iz `vehicle_type_translations.description` (fallback: bez opisa ako je NULL/prazan), a formatiranje je centralizovano u `VehicleType::formatLabel($locale, 'EUR')`.

**Tip rezervacije vs `merchant_transaction_id`:** `merchant_transaction_id` je korelacioni / idempotency ključ (v. **[project-conventions.md](./project-conventions.md)** §5); **ne** određuje da li je rezervacija plaćena, besplatna, gost, agencija ili admin-free — za to služe **`status`** i **`created_by_admin`**.

### 1.2 Pretraga i izmena rezervacija (admin panel) — implementirano

| Ruta | Namena |
|------|--------|
| `GET /admin/rezervacije` | `panel_admin.reservations` — pretraga nad tabelom **`reservations`** (bez `temp_data`). |
| `GET /admin/rezervacije/{reservation}/uredi` | `panel_admin.reservations.edit` — izmena samo **nerealizovanih** rezervacija (`PanelReservationListService::isRealized` = komplement od „upcoming“; kraj = kraj **pick-up** termina). |
| `PUT /admin/rezervacije/{reservation}` | `panel_admin.reservations.update` — transakcija, `lockForUpdate` na `daily_parking_data`, finalna validacija **`BlockReservationAdjustmentValidator::assertValidAfterLock`**, izmene `reserved`, update `reservations`, reset `invoice_sent_at` / `email_sent`, dispatch **`SendInvoiceEmailJob`** (paid) ili **`SendFreeReservationConfirmationJob`** (free). |
| `GET /admin/rezervacije/{reservation}/pdf` | `panel_admin.reservations.pdf` — PDF računa (paid) ili potvrde (free) preko postojećih generatora. |

- **Kontroler:** `App\Http\Controllers\AdminPanel\ReservationController`.
- **Pretraga:** `AdminReservationSearchService` — svi kriterijumi su **AND** između popunjenih polja; polje **MTID** traži **tačno poklapanje** — rezervacije sa **`merchant_transaction_id` = NULL** (ako postoje) ovim kriterijumom se ne nalaze.
- **Heuristika imena/emaila:** `AdminReservationSearchHeuristic` — jednostavne LIKE varijante (jedno izostavljeno slovo, zamena dva susedna; za ime normalizacija **doo** / **d.o.o.**).
- **Povratak sa edit strane:** query parametar **`rq`** čuva enkodiran prethodni query string pretrage; **`Odkaži`** i uspešan **`PUT`** vode na `GET /admin/rezervacije?{rq}`.
- **Izmena termina po tipu:** `AdminReservationSlotRules` — **paid** i **free + `created_by_admin`** mogu na bilo koje validne termine; **free bez admin kreacije** samo u besplatnom prozoru (`FreeReservationRules::isFreeReservation`). Status se **ne** menja u ovom toku.
- **Kategorija vozila:** u edit formi samo tipovi sa **`price` ≤** cene trenutnog tipa (`vehicle_types` po postojećem poretku cene).
- **Kalendar:** granice pretrage — `AdminReservationDateBounds` (min = najraniji datum u `reservations`, max = danas + 90 dana); edit — danas … danas + 90.
- **Email i slanje dokumenta:** račun (paid) i potvrda (free) uvek se šalju na **`reservations.email`** — to je snapshot na rezervaciji, isti izvor kao u PDF-u. **Admin** ima pravo da menja taj snapshot (uključujući email) u toku izmene rezervacije. **`SendInvoiceEmailJob`** i **`SendFreeReservationConfirmationJob`** koriste isključivo **`reservations.email`** kao primaoca; **`users.email`** se ne koristi za odredište čak i kada je **`user_id`** postavljen, da posle admin izmene poruka ne bi išla na zastarelu adresu naloga.

**Bez izmena na `temp_data`:** ovaj modul ne čita i ne piše `temp_data`.

**Testovi:** `tests/Feature/AdminPanel/AdminPanelReservationTest.php` (pretraga po MTID, 403 na edit za realizovanu, update + job, free + potvrda).

---

## 2. Blokiranje termina i dana

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Blokiranje/deblokiranje dana** | Admin blokira dan/termine bez menjanja `capacity/reserved/pending`. Blokada sprečava novu prodaju preko `daily_parking_data.is_blocked`. | `DailyParkingData.is_blocked` + `block_zone_worklist` |
| **Rezervacije u blok zoni (worklist)** | Slotovi sa postojećim `reserved>0` ili `pending>0` ne postaju odmah blokirani; ulaze u listu za ručno prilagođavanje. | `block_zone_worklist` |

Napomena: blokiranje je **odvojeno** od kapaciteta. `availableCapacity()` i `pending/reserved` semantika ostaju iste; UI/checkout samo dodatno tretira `is_blocked=1` kao nedostupno.

**UI (jasno razdvajanje Blokiraj / Deblokiraj):** na **`GET /admin/blokiranje`** u sekciji **Blokiraj** mogu se čekirati samo termini koji **nisu** već blokirani (već blokirani su prikazani kao informacija, bez `slot_ids[]`). Na **`GET /admin/blokiranje/dan/{date}`** (Deblokiraj) mogu se birati samo termini koji **jesu** blokirani; neblokirani su onemogućeni. Opcija **„Blokiraj ceo dan“** i dalje šalje kompletan skup slot ID-jeva; **`BlockingService::applyBlock`** i ranije preskače redove koji su već `is_blocked`.

Rute (admin panel):
- `GET /admin/blokiranje` (`panel_admin.blocking`)
- `POST /admin/blokiranje` (`panel_admin.blocking.apply`)
- `GET /admin/blokiranje/dan/{date}` (`panel_admin.blocking.day`)
- `POST /admin/blokiranje/dan/apply` (`panel_admin.blocking.unblock.apply`)
- `GET|POST /admin/blokiranje/worklist/{row}/prilagodi` (prilagođavanje rezervacije)

**`reservations.created_by_admin`:** boolean, default `false`. **`true`** samo za admin panel **Besplatne rezervacije** (`AdminDirectFreeReservationService`). Ostali tokovi eksplicitno postavljaju `false`. **Migracija:** `2026_04_11_120000_add_created_by_admin_to_reservations_table.php`.

**Prilagođavanje u blok zoni:** UI datum koristi prefiltar dana (minimum dva teorijski slobodna mesta tog dana — **nije** konačna garancija). Odlučujuća provera novih slotova (`!is_blocked`, `pending=0`, kapacitet; isti slot ID jednom) radi se **posle** `lockForUpdate` na relevantnim `daily_parking_data` redovima (`BlockingController` + `BlockReservationAdjustmentValidator`). Ako finalna validacija padne, nema delimičnih izmena. Posle uspešnog `Primeni` (blok/deblok) ili prilagođavanja redirect nosi `_fresh=timestamp` radi osvežavanja prikaza (bez auto-refresh tokom rada).

**Upit po datumu:** u modulu blokiranja/prilagođavanja, učitavanje i `lockForUpdate` nad `daily_parking_data` koristi **`whereDate('date', …)`** (ne striktno `where('date', …)`), da se datum uvek poklapa sa vrednošću u bazi i na SQLite-u.

**Testovi (izbor):** `tests/Feature/AdminPanel/AdminPanelAuthTest.php` (guard `panel_admin` vs `web`, 403, logout). `tests/Feature/AdminPanel/BlockReservationHardeningTest.php` (default kolone, post-lock odbijanje, blokiran novi slot bez delimičnih izmena, uspešan adjust + `_fresh`, deblok `_fresh`). `tests/Feature/AdminPanel/AdminWarningsDashboardTest.php` (dashboard nedostupni/blokirani). `tests/Feature/AdminPanel/AdminPanelFreeReservationTest.php` (besplatne rezervacije). `tests/Feature/AdminPanel/AdminPanelReservationTest.php` (admin pretraga/izmena rezervacija, §1.2).

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

**Račun / potvrda rezervacije (korisnik):** primalac = **`reservations.email`** (v. §1.2 — snapshot; admin može menjati).

---

## 5. Sistemska konfiguracija

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Broj slotova (kapacitet)** | Izmena vrednosti “broj dostupnih mesta” koja se koristi za nove dane/slotove. | `system_config`, polje `available_parking_slots` (integer). Koristi se u seederu i pri logici kapaciteta. |

Napomena: `system_config` ima `name` (unique) i `value` (integer). Za admin formu: prikaz/izmena reda gde je `name = 'available_parking_slots'`.

### 5.1 Podešavanja (admin panel) — implementirano

| Ruta | Namena |
|------|--------|
| `GET /admin/podesavanja` | `panel_admin.settings` — jedna stranica sa sekcijama Kapacitet i Izvještaji (email adrese). |
| `PUT /admin/podesavanja/capacity` | `panel_admin.settings.capacity.update` — validacija `1..99`, upis u `system_config.available_parking_slots`. Promena **ne važi retroaktivno** i primenjuje se za nove dane od **danas + 91 dan** (bez retroaktivnog update `daily_parking_data`). |
| `POST /admin/podesavanja/report-emails` | `panel_admin.settings.report-emails.store` — trim + lowercase + email sintaksa + duplicate zaštita, upis u `report_emails`. |
| `DELETE /admin/podesavanja/report-emails/{reportEmail}` | `panel_admin.settings.report-emails.destroy` — hard delete uz confirm UI. |

- **Kontroler:** `App\Http\Controllers\AdminPanel\SettingsController`.
- **Kapacitet (UX):** input je inicijalno read-only; `Promjeni` → editable + `Primjeni`/`Odkaži`. Success poruka sadrži konkretan datum važenja (danas + 91 dan).
- **Report emails (UX):** lista sortirana abecedno po emailu; `Dodaj email adresu` otvara formu; `Obriši` ima confirm modal.

**Testovi:** `tests/Feature/AdminPanel/AdminPanelSettingsTest.php`.

---

## 7. Analitika (admin panel) — implementirano

| Ruta | Namena |
|------|--------|
| `GET /admin/analitika` | `panel_admin.analytics` — dashboard sa filterima (period, include free), KPI i tabelama. |
| `GET /admin/analitika/pdf` | `panel_admin.analytics.pdf` — PDF export za aktivne filtere (isti dataset kao UI). |

- **Filteri:** `date_from`, `date_to` (zatvoren interval, `od <= do`), `include_free` (checkbox).
- **Source of truth:** rezervacije iz `reservations`, blokiranje iz `daily_parking_data.is_blocked`, operativni problemi iz `temp_data` i `post_fiscalization_data`.
- **Prihod:** suma `reservations.invoice_amount` za `status = paid` u periodu.
- **Zauzeti slotovi:** po rezervaciji 1 ako `drop_off_time_slot_id == pick_up_time_slot_id`, inače 2.
- **Popunjenost (slot-level):** \(occupied\_slots / (broj\_slotova \* broj\_dana)\).
- **Delovi dana:** grupisanje po početnom vremenu *drop-off* termina (00–07, 07–20, 20–24).
- **PDF:** `AdminAnalyticsPdfGenerator` (`DomPDF`) koristi view `pdf.admin-analytics-report` i isti dataset iz `AdminAnalyticsService`.

**Testovi:** `tests/Feature/AdminPanel/AdminPanelAnalyticsTest.php`.

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
