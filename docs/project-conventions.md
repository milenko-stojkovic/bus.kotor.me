# Konvencije projekta (bus.kotor.me)

**Poslednje ažuriranje:** 2026-03-31  

Za AI i ljude: držati se ovoga pri novim izmenama da ostane konzistentno.

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

---

## 3. Lokalni razvoj (Windows / Laragon)

- Za **`php artisan`**, ako shell ne pronalazi PHP, koristi punu putanju, npr.  
  `C:\laragon\bin\php\php-8.3.xx-Win32-vs16-x64\php.exe artisan ...`
- **Queue:** za lokalni QA bez workera, **`QUEUE_CONNECTION=sync`** u `.env`; inače `database` + `php artisan queue:work`.

### Minimalni `.env` za lokalni QA (fake tok, bez workera)

```env
BANK_DRIVER=fake
FISCALIZATION_DRIVER=fake
QUEUE_CONNECTION=sync
```

Posle izmene `.env`: `php artisan config:clear`. Za izmene u Tailwind/JS i dalje `npm run dev` ili `npm run build` kada menjaš utility klase u Blade-u.

---

## 4. Rute i redirect (Laravel)

- Za relativne URL-ove (CSRF / različiti host test domen):  
  `redirect()->to(route('ime.rute', [], false))` — **ne** `redirect()->route(..., [], false)` (može dati nevalidan status).

---

## 5. Payment / parking

- **`temp_data`:** životni ciklus plaćanja i audit; ne brisati pri grešci bez operativnog pravila.
- **`daily_parking_data`:** uvek paziti na **oba** slota (`drop_off_time_slot_id`, `pick_up_time_slot_id`); ako su isti ID, brojač jednom.
- **Idempotency:** ključ `merchant_transaction_id`.

---

## 6. Front (Vite / Tailwind)

- Stilovi se učitavaju preko **`@vite`** u layoutu.
- **Password polje + oko:** overlay stilovi su u **`resources/views/partials/password-field-overlay-styles.blade.php`** (uključeno u guest i app layout) da layout radi i kada **`public/build` nije sveže** generisan; ipak za izmene Tailwind utility-ja u `.blade` fajlovima povremeno pokreni **`npm run dev`** ili **`npm run build`**.

---

## 7. Ažuriranje TODO / DONE

- Otvoreno: **`docs/project-todo.md`**
- Završeno: **`docs/project-done.md`**
- Nova sesija u Cursoru: **`docs/handoff-new-chat.md`**
