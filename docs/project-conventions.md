# Konvencije projekta (bus.kotor.me)

**Poslednje ažuriranje:** 2026-04-03  

Za AI i ljude: držati se ovoga pri novim izmenama da ostane konzistentno.

---

## 0. Princip: dokumentacija = izvor istine, ne zabune

- **Cilj:** ono što piše u `docs/` treba da bude **proverljivo** i **usklađeno** sa stvarnim ponašanjem aplikacije. Dokument koji kontradiktuje kod ili drugi doc je **greška** dok se ne ispravi.
- **Hijerarhija:** za tačan tehnički detalj (ruta, status u bazi, redosled jobova) **kod u repou je presudan**. Tematski fajlovi u `docs/` su „izvor istine“ za ljude i AI **samo ako redovno prate taj kod**. Kad promeniš kod — u istom PR-u ili odmah posle ažuriraj pogođeni `.md` (ili eksplicitno napiši u `project-todo.md` da doc kasni, ako mora).
- **Kontradikcija doc ↔ kod:** ne ostavljati oba stanja; **ili** ispravi dokumentaciju **ili** vrati/popravi kod. Izbegavati **nejasno mešanje** starog i novog u istoj rečenici bez oznake šta još važi.
- **Sumnja:** ako nisi siguran šta važi, proveri kod (`routes/`, kontroler, model). Ne nagađaj u doc-u. Ako nešto nije implementirano, u doc-u jasno napiši **„nije implementirano / stub“** i na šta se odnosi (npr. ime klase).
- **Meta-dokumenti** (`handoff-new-chat.md`, `project-todo.md`, `project-done.md`, ovaj fajl, `project-status-next-steps.md`) opisuju proces i konvencije; **ne dupliraju** dugačke tehničke specifikacije — za to služe tematski fajlovi iz indeksa u `project-status-next-steps.md`.

### 0.1 Evolucija u dokumentu („bilo → sada“) — dozvoljena notacija

Ponekad je korisno u **istom** tematskom `.md` fajlu zabeležiti i **istoriju odluke**, ne samo trenutno stanje. To **nije** zabuna ako je struktura jasna.

Preporučeni oblik (naslovi ili bold oznake moraju biti eksplicitni):

1. **Rešenje je bilo ovako (zastarelo / pre promene):** … kratko šta je važilo ranije (ruta, klasa, pravilo).
2. **Nakon** (pravila, zahteva, PR-a, datuma — šta je pokrenulo promenu): … jedna rečenica konteksta.
3. **Rešenje sada izgleda ovako (važeće):** … šta **trenutno** važi i mora da se slaže sa kodom.

**Pravila za ovu notaciju:**

- Blok **„sada / važeće“** je ono što AI i novi saradnik tretiraju kao **operativnu istinu**; mora da odgovara kodu.
- Blok **„bilo / zastarelo“** služi **samo** za uvid u razvoj i odbacivanje loših koncepata — ne implementirati po njemu.
- Kad zastareli opis više niko ne koristi, može se **skratiti** (npr. jedna rečenica + „v. git istoriju“) da doc ne raste bez kontrole.
- U `project-done.md` često je dovoljna **jedna** rečenica po promeni; duboki „pre/posle“ zapis ostaje u tematskom fajlu gde ima smisla.

---

## 1. Jezik i tekstovi

- **`ui_translations`** (grupa + ključ + `locale` + `text`): za **kratke** UI stringove (naslovi, labele, dugmad, kratke poruke, auth kratki tekstovi).
- **Blade partiali / markdown u `docs/`**: za **dugačke** tekstove (uslovi korišćenja, politika privatnosti, duga uputstva). Ne stavljati ceo pravni tekst u `ui_translations`.
- Pristup u Blade-u: **`App\Support\UiText::t('group', 'key', $fallback)`**; novi ključevi kroz **`UiTranslationsSeeder`** sa `upsert` (bez dupliranja redova).
- **Korisnik / mail locale:** za auth mailove koristiti **`$user->lang`** (`cg` / `en`); verify-email ekran treba da prati isti princip gde je korisnik ulogovan.

---

## 2. Mail

- **Default SMTP** (`MAIL_*`): **`bus@kotor.me`** — korisnička komunikacija, računi, admin alerti koji nisu „no reply“.
- **Mailer `noreply`** (`MAIL_NOREPLY_*`, `MAIL_FROM_NOREPLY_*`): verifikacija emaila, reset lozinke — vidi `config/mail.php`.

### PDF računi i potvrde (izdavač: Opština Kotor)

- **Iznos na plaćenom računu** u PDF-u dolazi isključivo iz **`reservations.invoice_amount`** (snapshot pri kreiranju rezervacije), ne iz trenutne **`vehicle_types.price`**. PDF se generiše na zahtev (email ili panel); nema trajnog čuvanja u **`storage/app/invoices`**.
- Tekst u PDF šablonima (**fiskalni račun**, **nefiskalni račun**, **besplatna potvrda**) je **isključivo na crnogorskom (cg, latinica)** — **nema en varijante** u samom dokumentu; smisao je zvaničnog izdavača u Crnoj Gori.
- **Fiskalni račun** (`pdf/paid-invoice`, `isFiscal`): donji pravni red *„Ovaj račun je generisan automatski i važi kao fiskalni dokument.“*
- **Nefiskalni račun** (isti šablon, `isFiscal = false`): *„Ova potvrda je automatski generisana od strane sistema Opštine Kotor.“* (nije fiskalni dokument u tom smislu).
- **Besplatna potvrda** (`pdf/free-reservation-confirmation`): isti potvrdni tekst u podnožju (bez rečenice o fiskalnom dokumentu).

---

## 3. Lokalni razvoj (Windows / Laragon)

- **`php` često nije u PATH-u** u Cursor terminalu. Za **`php artisan`** koristi **jedno od**:
  1. **Skripta u rootu repoa:** `.\laragon-artisan.ps1 <artisan-arg>...` — bira najnoviji `php.exe` pod `C:\laragon\bin\php\`. Za test suite: **`.\laragon-artisan.ps1 test`** (ekvivalent `php artisan test`).
  2. **Eksplicitna putanja** (primer sa ovog računara; folder verzije proveri sa `dir C:\laragon\bin\php`):  
     `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan ...`
- **PowerShell u Cursoru:** ne koristiti `&&` za lančanje komandi (stariji PS); koristiti `;` ili posebne linije. Iz korena repoa: `Set-Location c:\laragon\www\bus.kotor.me; .\laragon-artisan.ps1 test`.
- **Sintaksa (`php -l`):** ne pokretati gol `php -l` ako Windows nudi „Open with…“ — koristi **`.\laragon-php.ps1 -l putanja\do\fajla.php`** (ista Laragon putanja kao za artisan).
- **AI / automatizacija:** u ovom projektu na Windowsu **ne oslanjati se** na gol `php` / `php artisan` u shellu dok se ne potvrdi da `php` postoji u PATH-u; preferirati **`.\laragon-php.ps1`**, **`.\laragon-artisan.ps1`**, ili punu putanju iznad.
- **Queue:** za lokalni QA bez workera, **`QUEUE_CONNECTION=sync`** u `.env`; inače `database` + `php artisan queue:work` (i tu istu PHP putanju ako treba).

### Frontend (Vite / Tailwind) — build, ne zavisnost od dev servera

- **`npm run build`** generiše **`public/build/*`**. Laravel tada učitava **statičke** CSS/JS; UI treba da izgleda **isto kao u dev modu** i da **radi bez** pokrenutog Vite servera.
- **`npm run dev`** (Vite na npr. `localhost:5173`) je samo za **aktivan** rad na `resources/css` / `resources/js` / utility klasama u Blade-u. Ako nešto radi **samo** uz `npm run dev`, a bez builda ne — to je **bug**.
- Za lokalni **production-like** test: posle izmena fronta uraditi **`npm run build`**, ne oslanjati se na trajno držanje `npm run dev`.

### Lokalni HTTPS (Laragon)

- Aplikacija se može servirati preko **HTTPS** (npr. **`https://bus.kotor.me.test`**, Apache SSL + Laragon cert).
- U **`.env`** postaviti **`APP_URL=https://bus.kotor.me.test`** (ili odgovarajući HTTPS host) da **`url()` / `route()` / asseti** budu **HTTPS-safe**.
- Izbegavati **hardcoded `http://`** u kodu gde to utiče na korisničke linkove ili očekivanja okruženja.

### Minimalni `.env` za lokalni QA (fake tok, bez workera)

```env
BANK_DRIVER=fake
FISCALIZATION_DRIVER=fake
QUEUE_CONNECTION=sync
```

Posle izmene `.env`: `.\laragon-artisan.ps1 config:clear` (ili ista PHP putanja + `artisan config:clear`). Za izmene u Tailwind/JS vidi podsekciju **Frontend** iznad (`npm run dev` tokom rada, **`npm run build` pre provere bez Vite-a ili pre deploy-a).

---

## 4. Rute i redirect (Laravel)

- Za relativne URL-ove (CSRF / različiti host test domen):  
  `redirect()->to(route('ime.rute', [], false))` — **ne** `redirect()->route(..., [], false)` (može dati nevalidan status).

---

## 5. Payment / parking

- **`temp_data`:** životni ciklus plaćanja i audit; ne brisati pri grešci bez operativnog pravila.
- **`daily_parking_data`:** uvek paziti na **oba** slota (`drop_off_time_slot_id`, `pick_up_time_slot_id`); ako su isti ID, brojač jednom.
- **Idempotency:** ključ `merchant_transaction_id`.
- **Korisnički ishod plaćanja / besplatnog checkout-a (flash):** session ključ **`checkout_banner`** — niz `level` (`success` | `info` | `error`), `title_key`, `message_key`, `group` (uglavnom **`checkout_result`**). Mapiranje ishoda: **`App\Support\CheckoutResultFlash`**; tekstovi u **`ui_translations`** grupi **`checkout_result`** (seed **`UiTranslationsSeeder`**). Prikaz: **`resources/views/partials/checkout-result-banner.blade.php`** na **`guest.reserve`** i **`panel.reservations`**.
- **Redirect posle završnog statusa:** **`PaymentReturnController`** za **`success` / `failed` / `late_success`** šalje korisnika na **`guest.reserve`** (gost) ili **`panel.reservations`** (ulogovan), sa odgovarajućim **`checkout_banner`**. **`GET /payment/return`** na ekranu zadržava samo **`pending`** (tekst + polling na **`/payment/result`**); layout: **`x-guest-layout`** ako nema sesije, **`x-app-layout`** ako je korisnik ulogovan (`resources/views/payment/return.blade.php` + **`payment/partials/return-pending-body.blade.php`**).

---

## 6. Front (Vite / Tailwind)

- Stilovi se učitavaju preko **`@vite`** u layoutu.
- **Password polje + oko:** overlay stilovi su u **`resources/views/partials/password-field-overlay-styles.blade.php`** (uključeno u guest i app layout) da layout radi i kada **`public/build` nije sveže** generisan; ipak za izmene Tailwind utility-ja u `.blade` fajlovima povremeno pokreni **`npm run dev`** ili **`npm run build`**.

---

## 7. Ažuriranje TODO / DONE

- Otvoreno: **`docs/project-todo.md`**
- Završeno: **`docs/project-done.md`**
- Nova sesija u Cursoru: **`docs/handoff-new-chat.md`**

---

## 8. Tematska dokumentacija u `docs/`

U **`docs/`** postoje dubiji opisi po domenima (payment, fiskal, cron, auth, admin, fake vs real, itd.). Oni moraju da poštuju **§ 0** (nema kontradikcije sa kodom).

**Indeks:** `docs/project-status-next-steps.md` → „Ostala dokumentacija“. Primer česte greške: zastareo URL **`/api/payments/callback`** — u aplikaciji je **`POST /api/payment/callback`**.

**Agency panel** (`/panel`, rezervacije, upcoming/realized, korisnik): **[agency-panel.md](./agency-panel.md)**.

---

## 9. Agency panel (kratko)

- Rute pod prefiksom **`/panel`**, vidi **`docs/agency-panel.md`** (rezervacije, vozila, upcoming/realized, korisnik, invoice).
- Korisnički tab: **`/panel/user`** — forma u `panel/partials/user-settings-form.blade.php`, **`PATCH /profile`**; brisanje naloga koristi **`user.delete_account_*`** u **`ui_translations`**.
