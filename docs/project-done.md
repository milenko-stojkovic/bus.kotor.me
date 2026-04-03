# Project DONE (urađeno)

**Poslednje ažuriranje:** 2026-04-05  

Hronološki najnovije na vrhu unutar svake sekcije. Pri zatvaranju zadatka dodaj red sa **datumom** (`YYYY-MM-DD`) i kratak opis; istu stavku ukloni iz `docs/project-todo.md`.

---

## 2026-04 — Agency panel, besplatan checkout, dokumentacija

- **2026-04-05** — **Production hardening (plaćanje / fiskal / email):** `config/http-outbound.php` — connect + response timeout za Bankart i fiskal (env override); `connectTimeout` u `RealPaymentProvider` i `FiscalizationService`; dokumentovan retry (nema HTTP retry na Bankart debit; fiskal 58 jednom); job `backoff()` + `failed()` na `PaymentCallbackJob`, `PaymentJob`, `ProcessReservationAfterPaymentJob`; povećan timeout post-payment joba; email jobovi — backoff, duži timeout, log u `payments` (`invoice_email_*`, `free_reservation_email_*`, exhausted); `payment_reservation_created`, `payment_fiscal_success`, `post_fiscalization_enqueued`; production upozorenje za fake driver (keš); `RealPaymentStatusInquiryService` → `payment_status_inquiry_not_implemented` warning; **`docs/production-runbook.md`**, **`docs/production-hardening.md`**; `.env.example` napomene; indeks u `project-status-next-steps.md`.
- **2026-04-04** — **PDF/email bez tihog fallback-a + retry preko reda:** `PaidInvoicePdfGenerator`/`FreeReservationPdfGenerator` → `renderBinary` vraća `string` ili baca (nema `null` maskiranja); `SendInvoiceEmailJob` i `SendFreeReservationConfirmationJob` — DB lock + `Reservation::EMAIL_SENDING`, na grešku `EMAIL_NOT_SENT` + log sa `reservation_id` + **fail job** (Laravel `tries`); `failed()` čisti zaglavljeno stanje; `Reservation::EMAIL_*` konstante; migracije — SQLite guard komentari / grane (`temp_data`, `reservations` user/vehicle, `drop invoice_pdf_path` idempotentno); fiskal — komentari „fake MUST mirror real API contract“ u `FiscalizationService` + `FakeFiscalApiController`.
- **2026-04-03** — **`project-conventions.md` §3:** Vite `npm run build` vs dev, lokalni HTTPS/`APP_URL`; eksplicitno da agent uvek koristi **`.\laragon-artisan.ps1`** (ne gol `php artisan`); queue primer sa skriptom.
- **2026-04-02** — **invoice_amount + PDF on-demand:** snapshot iznosa u `reservations`; uklonjen `invoice_pdf_path` i disk `invoices`; `PaidInvoicePdfGenerator`/`FreeReservationPdfGenerator` → `renderBinary`; email koristi temp fajl; panel inline/stream bez storage.
- **2026-04-02** — **Panel PDF 404 (zamijenjeno gornjim):** ranije disk `invoices` i trajno čuvanje PDF-a.
- **2026-04-02** — **Mail/PDF hardening + fake fiskal realizam:** PDF se generiše on-the-fly za attachment (bez trajnog storage-a); `SendInvoiceEmailJob` anti-dup guard (DB lock + sending state) i ne šalje email bez validnog PDF-a; fake fiskal response vraća `Operator` i verifikacioni URL sa `ord`/`crtd` (Interni broj u PDF-u); usklađen `paid-invoice` tekst oslobođenja i QR blok (veći QR, bez praznih redova).
- **2026-04-02** — **Fake QA pojednostavljenje:** jedna forma na `/payment/fake-bank` (banka + fiskal, jedan POST); uklonjen poseban korak `/payment/fake-fiscal` i kolona `fiscal_interactive_pending`; `FakeBankCompleteController` nakon uspješnog callbacka (oba drivera fake) pokreće `ProcessReservationAfterPaymentJob` sa scenarijem iz forme; migracija drop kolone; ažurirani `payment-architecture.md`, `success-payment-pipeline.md`, `payment-callback-handling.md`, `fake-payment-and-fiscal-qa-checklist.md`, `README.md`.
- **2026-04-01** — **Checkout ishod (UX):** `CheckoutResultFlash`, session **`checkout_banner`**, grupa **`checkout_result`** u `UiTranslationsSeeder`; banner partial na `guest.reserve` / `panel.reservations`; `PaymentReturnController` redirect + flash za success/failed/late_success; `PaymentResultResolver` (`fiscal_complete`, `resolution_reason`, redirect na `guest.reserve` / `panel.reservations`). Dokumentacija: `project-conventions.md` §5, `payment-callback-handling.md`, `success-payment-pipeline.md`, QA checklist-ovi.
- **2026-04-01** — **`/payment/return` layout:** pending ekran — **`x-guest-layout`** vs **`x-app-layout`** prema `auth()->check()`; zajednički sadržaj u `payment/partials/return-pending-body.blade.php`.
- **2026-04-01** — **Statistic tab:** `PanelStatisticsService` (total paid na plaćenim realized, broj posjeta, tabela po tablicama/kategoriji); `ui_translations` grupa **`statistics`**; ažuriran `docs/agency-panel.md`.
- **2026-04-01** — **`docs/agency-panel.md`**: rute `/panel`, upcoming/realized, promena vozila, User tab, brisanje naloga; indeks u `project-status-next-steps.md` i pokazivač u `project-conventions.md` §8.
- **2026-04-01** — **Besplatne rezervacije:** `FreeReservationRules`, `PaymentSuccessHandler` sa granom bez fiskala, `SendFreeReservationConfirmationJob`, `reservations.status = free`; promena vozila na plaćenoj rezervaciji resetuje `invoice_sent_at`/`email_sent` i ponovo dispatchuje `SendInvoiceEmailJob` (PDF on-demand).
- **2026-04-01** — **Agency panel R3:** `PanelReservationListService`, upcoming/realized tabele, PATCH vozila, PDF inline `panel.reservations.invoice.view`.
- **2026-04-01** — **User tab:** jedna forma (`panel/partials/user-settings-form`), `ProfileUpdateRequest` + lozinka, `UiText` grupa `user`, delete account CG kroz `ui_translations`.
- **2026-04-01** — **`laragon-php.ps1`** + `laragon-artisan.ps1` refaktor (PHP putanja na Windowsu); dokumentovano u `project-conventions.md` §3.

## 2026-03 — UI, auth, dokumentacija pracenja

- **2026-03-31** — Dokumentacija za handoff: `handoff-new-chat.md`, podela **TODO** / **DONE** / **conventions**, indeks u `project-status-next-steps.md`; minimalni lokalni QA `.env` blok u `project-conventions.md`.
- **2026-03-31** — Revizija tematskih `docs/*.md`: ispravljen URL callback-a na **`POST /api/payment/callback`**, usklađen `temp_data` workflow (nema brisanja na success), scheduler **`parking:sync-days`**, dual-slot soft lock u callback doc, queue `sync` za QA, admin late-success implementacija, `project-conventions` §8 o održavanju dubljih docs.
- **2026-03-31** — U `project-conventions.md` dodat **§ 0** (dokumentacija = izvor istine, ne zabune; hijerarhija kod ↔ doc; obaveza pri kontradikciji); usklađeni `handoff-new-chat.md` i `project-status-next-steps.md`.
- **2026-03-31** — **`project-conventions.md` § 0.1:** dozvoljena notacija „rešenje je bilo ovako → nakon … → sada izgleda ovako“ za evoluciju bez zabune; u `handoff` tabela održavanja dopunjena.
- **2026-03** — **Password eye** unutar polja: grid overlay CSS u `partials/password-field-overlay-styles.blade.php` (guest + app layout); radi bez obaveznog `npm run build`.
- **2026-03** — **Guest layout** kartica proširena (`sm:max-w-lg`); password polja sa zajedničkim partialom `auth/partials/password-eye-toggle-button.blade.php`.
- **2026-03** — **Verifikacija emaila** i verify-email ekran po **`user->lang`**; `NoreplyVerifyEmail`; `UiText` ključevi u seederu.
- **2026-03** — **Dual mail**: default `bus@kotor.me`, `noreply` mailer za auth (`config/mail.php`, `.env.example`).
- **2026-03** — **Agencije**: registracija (name, country, email, password), `users.lang` iz locale-a, uklonjen `company_name`, panel tabovi (rezervacije / korisnik / vozni park / istorija plaćanja), redirect posle login/verify.
- **2026-03** — **ui_translations** za V2 UI + auth kratke stringove; pravilo: kratko u DB, dugi pravni tekst u partialima (`project-conventions.md`).

## 2026-03 — V2 gost i plaćanje

- **2026-03** — **Predsoblje** `GET /`, guest forma `GET /guest/reserve` (`LandingController`, `GuestReservationController`).
- **2026-03** — Guest polje **name** (bivši snapshot `company_name`) kroz checkout i validaciju; usklađenost sa `daily_parking_data`.
- **2026-03** — **Terms** modal + lokacije (Google maps linkovi); terms partiali `terms_cg` / `terms_en` sa `mailto:bus@kotor.me`.
- **2026-03** — **`daily_parking_data`**: `pending`/`reserved` za **oba** slota (drop_off + pick_up); `CheckoutController`, `PaymentSuccessHandler`, `ExpirePendingReservations`.
- **2026-03** — Komanda **`parking:sync-days`** (sync dana + brisanje prošlih), u scheduleru.
- **2026-03** — **Fake bank** complete tok sa real-like payloadom / queue sync napomena. *(Od 2026-04-02: fiskal scenariji na istoj stranici kao banka — v. stavku 2026-04-02.)*
- **2026-03** — **Post-fiscalization** pravila: `post_fiscalization_data`, `admin_notified_at`, `AdminFiscalizationAlertService`, retry komanda proširena.

## Ranije (payment / fiskal / backend)

- **ErrorClassifier** za plaćanje i fiskal; integracija u `PaymentCallbackJob`, `FiscalizationService`, mapiranje `resolution_reason` → `user_message_key` (seed `UiTranslationsSeeder`).
- **Callback 400** hardening: audit u `temp_data` kad je moguće izvući `merchant_transaction_id` (`PaymentCallbackController`).
- **Real Bankart** `createSession` (V1-style potpis/payload), `BANKART_SEND_CUSTOMER` flag.
- **Fiskal**: `DocumentNumber` iz `system_config` sa zaključavanjem; fake tok kroz deposit + fiscal-receipt kao real.
- **Fake fiscal API**: `X-Fake-Fail`, `X-Fake-Timeout` (504).
- **Admin** `late_manual_review`: `LateSuccessController` (lista, detalj, force create → rezervacija + job, reject).
- Payment V1 tok: checkout, redirect, callback, success/fail, `merchant_transaction_id`, `retry_token`, guest retry API.
- Dokumenti: `fake-vs-real-contract-parity.md`, `fake-payment-and-fiscal-qa-checklist.md`, `scheduled-tasks-overview.md`, itd.

---

## Ključni fajlovi (referenca, ne potpuna lista)

| Oblast | Lokacije |
|--------|----------|
| Payment callback job | `app/Jobs/PaymentCallbackJob.php` |
| Uspeh plaćanja / soft lock | `app/Services/Payment/PaymentSuccessHandler.php` |
| Checkout / pending | `app/Http/Controllers/CheckoutController.php` |
| Greške / klasifikacija | `app/Services/Payment/ErrorClassifier.php` |
| Fiskal | `app/Services/FiscalizationService.php` |
| UI tekst | `app/Support/UiText.php`, `database/seeders/UiTranslationsSeeder.php` |
| Checkout flash / plaćanje UX | `app/Support/CheckoutResultFlash.php`, `PaymentReturnController`, `partials/checkout-result-banner.blade.php` |
| Noreply notifikacije | `app/Notifications/NoreplyVerifyEmail.php`, `NoreplyResetPassword.php` |
| Agency panel (dok.) | `docs/agency-panel.md` |
| Besplatan termin / checkout | `app/Services/Reservation/FreeReservationRules.php`, `app/Jobs/SendFreeReservationConfirmationJob.php` |
| Panel upcoming/realized | `app/Services/Reservation/PanelReservationListService.php`, `app/Http/Controllers/PanelController.php` |
| Panel statistika | `app/Services/Reservation/PanelStatisticsService.php` |
| Profil (panel user) | `app/Http/Controllers/ProfileController.php`, `resources/views/panel/partials/user-settings-form.blade.php` |
