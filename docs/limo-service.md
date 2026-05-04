# Limo service – initial specification

**Dokument:** izvor istine za Limo uslugu *prije* implementacije. Ovo je inicijalna specifikacija; migracije, modeli i kod još nisu obavezni.

**Poslednje ažuriranje:** 2026-05-05

**Povezano:** v. [project-todo.md](./project-todo.md) (sekcija *Limo service*).

---

## Scope

Limo service je dostupan **samo ulogovanim agencijama**.

**Gosti ne mogu** koristiti Limo service.

Limo service **zavisi od avansa**:

- vidljivo/upotrebljivo samo kada `config('features.advance_payments') === true`
- koristi postojeći **agency advance ledger**
- **negativan saldo avansa nije dozvoljen**

**Limo service nije rezervacija.** Ne koristi:

- `temp_data`
- `reservations`
- `daily_parking_data`
- parking slotove
- besplatne termine
- radno vrijeme / working-hours logiku

---

## Vehicle scope

Za Limo se koristi samo kategorija putničkog vozila: **„Putničko vozilo (4+1 do 7+1 sjedišta)”**.

- **Cijena:** trenutno **15,00 EUR po pickup-u**.
- Ova kategorija vozila **uklanja se iz regularne Bus Kotor ponude rezervacija** i koristi se **isključivo** kroz Limo service.
- **Cijena mora biti snapshotovana** pri svakom realizovanom Limo pickup-u (zbog budućih promjena cjenovnika).

---

## Financial model

Agencije dopunjavaju avans postojećim advance sistemom.

**Isti advance saldo** može se koristiti za:

- plaćanje regularne Bus Kotor rezervacije iz avansa
- **korišćenje Limo pickup-a**

Realizovan Limo pickup:

- kreira **Limo pickup događaj**
- **smanjuje** agency advance ledger za **snapshotovanu** cijenu Limo pickup-a
- **pokreće fiskalizaciju** za taj Limo pickup
- šalje **fiskalni PDF / email** agenciji

**Advance topup sam po sebi se ne fiskalizuje.** Fiskalizacija nastupa kada se Limo pickup **stvarno iskoristi**.

---

## QR model

Agencije mogu generisati **privremene QR kodove za tekući dan**.

Pravila QR-a:

- QR pripada **agenciji**
- QR **nije vezan za vozilo**
- QR važi **samo za svoj datum**
- QR predstavlja **jedan mogući pickup**
- QR **nema financijski efekat** dok ga Limo evidenter **ne potvrdi**
- QR se može upotrijebiti **samo jednom**
- nakon uspješne upotrebe QR se **uklanja** iz liste aktivnih privremenih QR-ova
- **stari nekorišćeni** privremeni QR kodovi brišu se **na početku sljedećeg dana**

**Početni dnevni limit:**

- maks. **20 generisanih QR kodova po agenciji po danu**
- limit treba da broji **i** aktivne privremene QR kodove **i** već iskorišćene QR pickup događaje za isti dan

Generisanje QR-a je dozvoljeno samo ako:

- advance feature je **uključen**
- agencija ima **advance saldo ≥ trenutnoj cijeni Limo pickup-a**
- **dnevni limit QR-a** nije prekoračen

---

## Limo evidenter

Limo evidenter je **odvojena operativna uloga / modul**.

**Ne** koristiti postojeći Control panel „kao što jeste“ — trenutni Control panel je pasivan.

**Predlog korisničkog naziva:** „Limo evidencija“

**Predlog tehničkog prefiksa ruta:** `/limo`

**Platformska odluka za prvu implementaciju:**

- **Laravel web / PWA-first** unutar istog projekta
- backend treba da bude **API-friendly** da se kasnije može dodati native Android ako zatreba

---

## Pickup flow – QR

Kada Limo evidenter skenira **važeći QR**:

Sistem validira:

- QR postoji u tabeli **aktivnih privremenih QR tokena**
- datum QR-a je **danas**
- agencija i dalje ima **dovoljno advance salda**
- pravila dnevne upotrebe / limita su zadovoljena

Na potvrdi:

- kreira se **Limo pickup event**
- **snapshot** iznosa
- kreira se red u `agency_advance_transactions` tipa **usage** sa iznosom **−snapshot**
- briše se aktivni QR token
- pokreće se **Limo fiskalizacioni pipeline**

**Ne** kreirati `Reservation`. **Ne** koristiti `temp_data`. **Ne** dirati `daily_parking_data`.

---

## Pickup flow – license plate fallback

Ako vozač **nema QR**:

Limo evidenter fotografiše registarsku tablicu.

Sistem može koristiti **OCR** ili **ručni unos** tablice.

Ako tablica odgovara **aktivnom vozilu agencije** i agencija ima **dovoljno advance salda**:

- kreira se Limo pickup event
- snapshot iznosa
- kreira se advance usage red
- pokreće se fiskalizacija

**Vozilo** je potrebno samo u ovom fallback toku. **QR tok ne veže vozilo.**

---

## Incident flow

Ako:

- nema važećeg QR-a
- **i** tablica se ne može upariti sa aktivnim vozilom agencije
- **ili** ne postoji naplativa advance putanja

onda kreirati **incident-style** Limo pickup event sa dokazima:

- fotografija
- server timestamp
- GPS lokacija
- Limo evidenter
- informacije o uređaju
- opcioni tekst tablice ako postoji

**Za sada:**

- **ne** slati automatski Komunalnoj policiji
- **ne** implementirati workflow izvještavanja
- **ne** slati email na `komunalna.policija@kotor.me`

**TODO:**

- ko šalje incident izvještaje
- format izvještaja
- da li Admin ili Limo evidenter šalje
- workflow „prijavljeno / zatvoreno“

---

## Evidence / audit

Svaki realizovan ili incident pickup treba čuvati:

- agenciju kada je poznata
- izvor: `qr` / `plate` / `incident`
- timestamp sa servera
- GPS lokacija kada je dostupna
- Limo evidenter
- informacije o uređaju
- foto dokaz gdje je primjenjivo
- advance saldo prije/poslije ako je praktično
- snapshotovan iznos

Događaji su u poslovnom smislu **append-only**. Izbjegavati destruktivne izmjene dokaza.

---

## Predložene tabele (samo dokumentacija)

**Ne kreirati migracije dok se ne dogovori implementacija.**

### `limo_qr_tokens`

Privremeni aktivni QR tokeni.

Predložena polja:

- `id`
- `agency_user_id`
- `token_hash`
- `valid_on`
- `created_at`
- `updated_at`

Bez polja statusa. Iskorišćeni tokeni se brišu iz ove tabele; podaci o korišćenju ostaju kroz `limo_pickup_events`.

### `limo_pickup_events`

Izvor istine za realizovane Limo prodaje i incidente.

Predložena polja:

- `id`
- `merchant_transaction_id` (string, **unique**) — stabilan korelacioni / identifikator za fiskal, PDF, email, logove i admin pretragu; generiše se pri kreiranju događaja. **Nije** vezan za `temp_data` niti `reservations` i ne učestvuje u payment state machine-u rezervacija.
- `agency_user_id` (nullable za incident / nepoznato)
- `agency_name_snapshot` (nullable) — audit snapshot imena agencije u trenutku događaja; postavlja se jednom pri kreiranju kada je agencija poznata; **ne** izračunavati kasnije iz `users`
- `agency_email_snapshot` (nullable) — audit snapshot emaila agencije u trenutku događaja; ista pravila kao za ime
- `source`: `qr` / `plate` / `incident`
- `qr_token_hash` (nullable)
- `qr_valid_on` (nullable)
- `vehicle_id` (nullable)
- `license_plate_snapshot` (nullable)
- `amount_snapshot` `decimal(10,2)`
- `occurred_at`
- `gps_lat` (nullable)
- `gps_lng` (nullable)
- `recorded_by_limo_admin_id`
- `device_info` (nullable)
- `status`: npr. `pending_fiscal` / `fiscalized` / `fiscal_failed` / `incident`
- fiskalna polja slična rezervacijama gdje treba
- `created_at`
- `updated_at`

Polje valute za sada **nije** u planu.

**Audit identiteta agencije:** `agency_user_id` može postati `null` ako se korisnik obriše (`nullOnDelete`), ali istorija događaja mora ostati čitljiva — zato se uz poznatu agenciju na kreiranju unose `agency_name_snapshot` i `agency_email_snapshot`. Za incidente bez poznate agencije snapshot polja ostaju `null`.

### `limo_pickup_photos`

Foto dokazi.

Predložena polja:

- `id`
- `limo_pickup_event_id`
- `path`
- `type`: `plate` / `context`
- `created_at`
- `updated_at`

Fajlove na produkciji inicijalno držati na serveru, **privatno / ne-javno** skladište.

---

## Agency panel

Buduća sekcija (nakon implementacije):

- ruta: **`/panel/limo`**

Vidljivo samo kada je `advance_payments` uključen.

Agencija treba da vidi:

- trenutnu dostupnost Limo / avansa
- aktivne privremene QR kodove za **tekući dan**
- dugme za generisanje novog privremenog QR-a
- prikaz detalja QR-a
- PDF / print za QR
- istoriju realizovanih Limo pickup-a

Ako je QR iskorišćen, nestaje iz liste aktivnih QR-ova.

---

## Admin panel / analytics

Limo treba da se pojavi u Admin analitici.

Minimum:

- Limo prihod uključen u ukupni / ostali prihod u izvještajima
- admin može pregledati Limo pickup događaje
- incidenti / slanje Komunalnoj policiji = **TODO** dok se ne potvrdi poslovni i pravni proces

---

## Explicit non-goals (inicijalna implementacija)

**Ne implementirati još:**

- native Android aplikaciju
- automatski email Komunalnoj policiji
- workflow incident izvještavanja
- negativan advance saldo
- vezivanje vozila za QR
- Limo za goste
- Limo preko rezervacija / `temp_data`
- offline-first sinhronizaciju
