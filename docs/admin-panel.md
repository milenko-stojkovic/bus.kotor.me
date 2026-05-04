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

### Limo pickup događaji (read-only) — `GET /admin/limo` (`admin.limo.index`)

- **Middleware:** `auth:panel_admin` + `admin.panel` (kao ostali moduli glavnog admin panela; **nije** `limo.access` — pristup imaju samo `admins` sa `admin_access=1`, ne i „samo Limo” nalozi).
- **Kontroler:** `App\Http\Controllers\Admin\LimoController::index`; pogled `resources/views/admin/limo/index.blade.php`.
- **Izvor:** isključivo **`limo_pickup_events`** (bez rezervacija iz `reservations`).
- **Filter:** GET parametri **`date_from`** / **`date_to`** (opcioni); podrazumijevano oba = **današnji dan** u **`Europe/Podgorica`**; zatvoren interval **`[from, to]`** po koloni **`occurred_at`**; redosled **`occurred_at DESC`**.
- **Pregled:** agencija (snapshot), tablica, iznos, izvor (QR / tablica), status fiskalizacije, JIR kad postoji.
- **Namjerno nije uključeno:** izmjene, brisanje, retry fiskala, ponovno slanje emaila, incidenti, export.
- **Testovi:** `tests/Feature/Admin/LimoAdminIndexTest.php`.

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
- **Pristigli FZBR zahtjevi:** na dnu iste strane prikazuje se lista aktivnih zahtjeva iz **agency panela** (`/panel/fzbr`):
  - izvor istine: `free_reservation_requests` → `free_reservation_request_segments` → `free_reservation_request_vehicles` (+ `free_reservation_request_attachments`)
  - prikazuju se samo statusi: **`submitted`**, **`updated`** (ne prikazuje `fulfilled`/`rejected`)
  - sortiranje: `created_at DESC`
  - eager loading (bez N+1): `with(['segments.dropOffTimeSlot','segments.pickUpTimeSlot','segments.vehicles.vehicleType.translations','attachments'])`
  - **Dokumenta (private/local storage):** prilozi su u `free_reservation_request_attachments` i prikazuju se kao lista sa linkom za **preview** (admin-only ruta streamuje fajl inline).
  - **Retention:** posle **fulfill** / **reject** zahtjev se **ne briše**. Samo se setuje status (`fulfilled`/`rejected`) i uklanja se upozorenje (pointer).
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

## 4.1 Izvještaji (admin panel) — implementirano

- **Nema HTML preview-a**: stranica je samo dvokoračni izbor + PDF export u novom tabu.
- **Datum bounds (svi pickeri)**: min/max se računaju iz `reservations.created_at` (samo datum deo).
- **PDF**: uvek na `cg`, generiše se i kad nema podataka (nule/prazni redovi).
- **Zakazano slanje PDF izvještaja emailom (scheduler)**: komanda `reports:send-scheduled {daily|monthly|yearly}` šalje izvještaje svim primaocima iz `report_emails` (jedan email po primaocu). Idempotency preko `scheduled_report_deliveries`. U slučaju greške (generisanje PDF ili slanje) kreira se `admin_alerts` zapis koji se uklanja ručno.

| Ruta | Namena |
|------|--------|
| `GET /admin/izvestaji` | `panel_admin.reports` — izbor “Kada” + “Kakav”, zatim izbor opsega i dugmad `PDF` / `Odkaži`. |
| `GET /admin/izvestaji/pdf` | `panel_admin.reports.pdf` — PDF export (inline) za izabrani tip i opseg. |

- **Kontroler:** `App\Http\Controllers\AdminPanel\ReportsController`.
- **Validacija:** `App\Http\Requests\AdminPanel\AdminPanelReportPdfRequest`.
- **Bounds:** `App\Services\AdminPanel\Reports\AdminReportsCreatedAtBounds`.
- **Agregacija:** `App\Services\AdminPanel\Reports\AdminReportsService`.
- **PDF:** `App\Services\Pdf\AdminReportsPdfGenerator` koristi view `pdf.admin-report`.

**Po uplati:** samo `paid` rezervacije, opseg po `reservations.created_at` (datum), `Ukupan prihod = sum(invoice_amount)`, `Broj transakcija = count(paid)`.

**Po realizaciji:** realizovane rezervacije po važećoj sistemskoj definiciji (istekao `Vrijeme odlaska`), opseg po `reservations.reservation_date` (jer realizacija je u okviru istog dana), `Ukupan prihod` se sabira samo za `paid`, a `Broj realizovanih` broji sve realizovane (paid + free).

**Po tipu vozila:** koristi realizovane rezervacije (bez obzira na status), opseg po `reservation_date`, prikaz 4 fiksna reda + `Ukupno` (naziv na `cg`: name + (description), bez cijene). I bez podataka redovi ostaju sa nulama.

### 4.1.1 Izvještaj: Obaveze po avansima (snapshot) — implementirano (feature-flag)

**Svrha:** formalni snapshot izvještaj “stanje obaveza po avansima” na izabrani dan (npr. kraj fiskalne godine).

**Feature flag:** `config('features.advance_payments')` (ako je OFF → opcija nije vidljiva + endpoint vraća 404).

**Kakav:** `advance_obligations` (dozvoljen samo `when=daily`)

**Računanje (source-of-truth):** isključivo `agency_advance_transactions` (ledger), filter `created_at <= endOfDay(date)`:
- uplaćeno: SUM(type=topup)
- iskorišćeno: ABS(SUM(type=usage))
- korekcije: SUM(type=correction)
- preostalo/obaveza: SUM(svih amount)

PDF naslov: `Izvještaj o obavezama po osnovu avansnih uplata na dan DD.MM.YYYY.`

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
- **Datum od (min):** najstariji datum **realizovane** rezervacije (fallback: najstariji `reservation_date`, pa danas ako nema rezervacija).
- **Datum do (max):** danas + 90 dana.
- **Source of truth:** rezervacije iz `reservations`, blokiranje iz `daily_parking_data.is_blocked`, operativni problemi iz `temp_data` i `post_fiscalization_data`; **Limo** iz `limo_pickup_events` (posebno; nije rezervacija).
- **Prihod (rezervacije):** suma `reservations.invoice_amount` za `status = paid` u periodu (KPI: **Prihod od rezervacija (paid)**). **Ne** uključuje Limo.
- **Limo servis (poseban blok + KPI):** period po **`occurred_at`**, zatvoren interval, vremenska zona **Europe/Podgorica** (start dana / kraj dana). U obzir: `status IN (pending_fiscal, fiscalized, fiscal_failed)`; **`incident` isključen** (nema prihoda u analitici). Prihod = **SUM(`amount_snapshot`)**; brojevi po izvoru (**QR** / **tablica**) i po fiskalnom statusu. Limo **ne ulazi** u broj rezervacija, zauzete slotove, tipove vozila, agencijske rezervacijske statistike niti trend rezervacija — ostaje odvojeno.
- **Ukupan prihod (rezervacije + Limo):** zbir KPI prihoda od plaćenih rezervacija i Limo prihoda za isti izabrani period (jasno označeno u UI i PDF).
- **Zauzeti slotovi:** isključivo od rezervacija — po rezervaciji 1 ako `drop_off_time_slot_id == pick_up_time_slot_id`, inače 2.
- **Popunjenost (slot-level):** \(occupied\_slots / (broj\_slotova \* broj\_dana)\).
- **Delovi dana:** grupisanje po početnom vremenu *drop-off* termina (00–07, 07–20, 20–24).
- **Analiza po agencijama:** pregled prihoda, rezervacija i zauzetosti po registrovanim korisnicima (`reservations.user_id`), sortirano po prihodu opadajuće. Prihod = suma `invoice_amount` za `paid`; free se prikazuje kao posebna kolona i procenat.
- **Admin free (FZBR) po agencijama:** poseban prikaz besplatnih rezervacija koje su kreirali administratori (`reservations.status = free` + `created_by_admin = true`), grupisano po agencijama (`user_id`), uz “Bez agencije” za `user_id = null`. Ne utiče na KPI i ne zavisi od `include_free`.
- **Operativni indikatori (ops):**
  - **Paid rezervacije u free terminima**: broj `paid` rezervacija čiji su i drop-off i pick-up u “free zonama” (00–07 ili 20–24).
  - **Duplo plaćanje istog termina**: broj **parova** `paid` rezervacija za isti datum i iste tablice sa bar jednim zajedničkim slotom (drop/pick presek). `include_free` ne utiče (računa se samo `paid`).
- **PDF:** `AdminAnalyticsPdfGenerator` (`DomPDF`) koristi view `pdf.admin-analytics-report` i isti dataset iz `AdminAnalyticsService`.

**Testovi:** `tests/Feature/AdminPanel/AdminPanelAnalyticsTest.php`.

### 7.1 Stanje avansa po agencijama (Analitika) — implementirano (feature-flag)

UI sekcija na dnu Analytics strane: **“Stanje avansa po agencijama”** (samo kada je `advance_payments` ON).

- **Ukupno stanje avansa:** SUM(agency_advance_transactions.amount) preko svih agencija
- Tabela po agencijama (samo agencije koje imaju bar 1 ledger red):
  - Uplaćeno ukupno (type=topup)
  - Iskorišćeno ukupno (ABS(type=usage))
  - Korekcije ukupno (type=correction)
  - Trenutno stanje (SUM)
  - Poslednja aktivnost (MAX(created_at))
  - Link na detalj agencije (`panel_admin.agencies.show`)

---

## 8. Uvid (admin panel) — implementirano

Read-only modul, payment-centric (osnovna jedinica prikaza je `merchant_transaction_id`).

- **Source of truth (search):** `temp_data`
- **Dopuna:** `reservations` se pridružuje po istom MTID (ako postoji)
- **Admin-free rezervacije:** ne pripadaju payment lifecycle-u; ako admin unese MTID koji postoji samo kao admin-free rezervacija, prikazuje se kratka napomena (bez payment detalja).
- **Log timeline:** parsirana lista događaja iz `payments-YYYY-MM-DD.log` (samo linije koje eksplicitno sadrže MTID). Retention je usklađen sa `config('logging.channels.payments.days')`. Ako nema dostupnih logova u retention periodu: prikazuje se poruka *„Detaljni payment logovi nisu dostupni u retention periodu.“*
- **Search UX:** polja `Država`, `Status (temp_data)` i `Resolution reason` su dropdown; `Tablica` se normalizuje na `A–Z0–9` (ALL CAPS); datumi se prikazuju kao `DD.MM.YYYY.`.
- **Navigacija:** povratak sa detalja na listu čuva query string (`Nazad` vraća prethodne rezultate pretrage).

| Ruta | Namena |
|------|--------|
| `GET /admin/uvid` | `panel_admin.insight` — search/list nad `temp_data` (AND logika za popunjene kriterijume) + link `Detalji`. |
| `GET /admin/uvid/{merchantTransactionId}` | `panel_admin.insight.show` — detalj case-a (temp_data + rezervacija + timeline + Copy details). |

- **Kontroler:** `App\Http\Controllers\AdminPanel\InsightController`.
- **Validacija:** `App\Http\Requests\AdminPanel\AdminPanelInsightSearchRequest`.
- **Servis:** `App\Services\AdminPanel\Insight\AdminInsightService`.
- **Timeline parser:** `App\Services\AdminPanel\Insight\PaymentLogTimelineService`.

**Testovi:** `tests/Feature/AdminPanel/AdminPanelInsightTest.php`.

## 6. Istorija plaćanja (registrovani korisnici)

| Funkcionalnost | Opis | Modeli / tabele |
|----------------|------|------------------|
| **Pristup history plaćanja** | Admin vidi istoriju rezervacija za registrovane korisnike (po `user_id`). | `Reservation` gde je `user_id` set; prikaz po user-u (npr. izbor korisnika ili pregled jednog korisnika). |

---

## 9. Agencije (admin panel) — implementirano (feature-flag delovi)

Rute:
- `GET /admin/agencije` → `panel_admin.agencies.index`
- `GET /admin/agencije/{user}` → `panel_admin.agencies.show`

Stranice su read-only za većinu avans podataka; napredne akcije su iza feature flag-a.

### 9.1 Lista agencija

Kolone (izbor):
- ime, email, datum registracije
- broj rezervacija
- **saldo avansa** (SQL SUM preko `agency_advance_transactions`) — prikazuje se samo ako je `advance_payments` ON

### 9.2 Detalj agencije

Kada je `advance_payments` ON, prikazuje:
- trenutno stanje avansa (`AgencyAdvanceService::balance`)
- ledger istoriju (`agency_advance_transactions`)
- topup istoriju (`agency_advance_topups`)

### 9.3 Admin korekcija avansa

Na detalju agencije admin može dodati korekciju:
- kreira se novi ledger red `agency_advance_transactions` sa `type=correction` i `note=razlog`
- negativna korekcija ne sme spustiti saldo ispod 0 (konzervativno pravilo)

Ruta:
- `POST /admin/agencije/{user}/avans/korekcija` → `panel_admin.agencies.advance.correction.store`

### 9.4 Retry slanja potvrde topup-a

U “Topup istorija” tabeli se prikazuje kolona “Potvrda”:
- ako je topup `paid` a `confirmation_sent_at` je null → dugme “Pošalji potvrdu ponovo”

Ruta:
- `POST /admin/agencije/{user}/avans/topups/{topup}/confirmation/resend` → `panel_admin.agencies.advance.topups.confirmation.resend`

Idempotency i evidencija slanja su u `agency_advance_topups.confirmation_sent_at` / `confirmation_email`.

### 9.5 Zahtjevi za promjenu kategorije vozila

Ovaj workflow postoji da bi se sprečila zloupotreba ponovnog unosa iste registarske tablice sa drugom (npr. jeftinijom) kategorijom bez provjere dokumentacije. Formulisan je kao neutralna provjera dokaza (slika/PDF).

**Gdje se vidi:**

- Admin vidi **pending** zahtjeve na detalju agencije: **Admin → Agencije → detalj agencije**.
- Warning u **Upozorenja / Informacije** je samo **pointer** (operativni podsjetnik).

**Source of truth:**

- tabela **`vehicle_category_change_requests`**
- dokumenti su u **private/local storage** (nisu public)

**Dokument preview:**

- admin-only ruta streamuje dokument inline (image/PDF) iz private storage-a.

**Approve (Prihvati):**

- zahtjev mora biti `pending`
- reaktivira postojeće `removed` vozilo (ne kreira novo):
  - `vehicle_type_id = requested_vehicle_type_id`
  - `vehicles.status = active`
- `vehicle_category_change_requests.status = approved`
- upisuje se `reviewed_by_admin_id` i `reviewed_at`
- uklanja se warning iz Upozorenja / Informacije

**Reject (Odbij):**

- `vehicle_category_change_requests.status = rejected`
- upisuje se `reviewed_by_admin_id` i `reviewed_at`
- vozilo ostaje `removed`
- uklanja se warning iz Upozorenja / Informacije

**Retention:**

- zahtjevi se ne brišu (ostaju `approved/rejected`)
- dokumenti ostaju u storage-u

Guest rezervacije (`user_id` = null) nemaju “istoriju po korisniku”; mogu se pretraživati po email-u, tablici, datumu itd.

---

## 7. Tehnički predlog (Laravel)

- **Rute:** sve pod prefiksom npr. `admin/`, middleware `auth` + admin guard ili `role:admin`.
- **Kontroleri:** npr. `App\Http\Controllers\Admin\ReservationController`, `TempDataController`, `ReportEmailController`, `SystemConfigController`, `DailyParkingDataController` (blokiranje).
- **Modeli:** `Reservation`, `TempData`, `DailyParkingData`, `ListOfTimeSlot`, `ReportEmail`; za `system_config` opciono model `SystemConfig` sa helperom za `available_parking_slots`.
- **Politike:** provera da je trenutni user admin pre pristupa ovim akcijama.

Ovaj dokument služi kao referenca za implementaciju admin panela; pojedinačne funkcionalnosti mogu se realizovati korak po korak.
