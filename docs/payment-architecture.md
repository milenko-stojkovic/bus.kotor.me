# Payment arhitektura (redirect na banku, webhook + queue)

Korisnik se **odmah redirect-uje na bank payment page** nakon klika "Pay". Obrada rezultata plaćanja **nikad** u HTTP request-u – isključivo **webhook/callback** + **queue job**.

---

## Payment UX zahtev

1. User klikne "Pay" → **odmah redirect** na bank payment page.
2. **Flow u HTTP kontroleru:**
   - validira request
   - proverava dostupnost (kapacitet)
   - kreira **temp_data** (pending)
   - kreira **payment session** sa gatewayem (**sync**)
   - prima **payment_url**
   - **redirect** korisnika na **payment_url**
3. **Nema obrade statusa plaćanja** u HTTP request-u (success/fail obrada samo u webhook + job).

---

## Obrada rezultata (success / fail / late success)

- **Webhook / callback endpoint** – gateway poziva naš URL sa rezultatom.
- **Queue job** (PaymentCallbackJob) obavlja:
  - kreiranje rezervacije iz temp_data
  - fiskalizacija (PostFiscalizationJob)
  - email (cron SendReservationEmails)
  - retries, idempotentnost

Nikad kreiranje rezervacije ili fiskalizacija u HTTP request-u – samo validacija payload-a i **dispatch job-a**.

---

## Ako je gateway spor ili nedostupan

- Controller **ne kreira** rezervaciju.
- Controller vraća **"payment temporarily unavailable"** (503).
- Nema fallback sync plaćanja – korisnik može ponovo pokušati kasnije.

---

## Striktna pravila za HTTP controller (checkout)

Controller **samo**:

1. **Validira** podatke (CheckoutReservationRequest).
2. **Proverava dostupnost** (DailyParkingData za datum + termin, availableCapacity >= 1).
3. **Kreira** zapis u **temp_data** sa **status = pending**.
4. **Poziva** **PaymentService::createSession($temp)** (sync) – provider iza interfejsa.
5. Ako **payment_url** → **redirect** na payment_url.
6. Inače → **503** sa porukom (gateway spor/nedostupan).

Controller **nikad**:

- Ne obrađuje rezultat plaćanja (success/fail) – to radi webhook + job.
- Ne kreira rezervaciju ni ne poziva fiskalizaciju u request-u.

---

## Webhook endpoint

- **`POST /api/payment/callback`** – bank callback na **API ruti** (`routes/api.php` → `payment/callback`), **ne** na web.
- **Machine-to-machine only.** Nikad ne koristiti ovaj endpoint za frontend redirect ili UI flow. **Frontend NIKAD ne sme da poziva bank callback.**
- Nema web middleware (session, CSRF, redirects) – banka ne šalje cookies/CSRF; stateless.
- Controller: validacija potpisa, validacija payload-a, **dispatch PaymentCallbackJob(payload)**.
- Vraća samo **202 Accepted** ili **400 Bad Request**. Nikad redirect.
- User redirect se radi kasnije preko frontend polling-a /payment/result.
- Za test: fake bank koristi **poseban** endpoint POST /payment/fake-bank/complete (web), ne bank callback.

---

## Queue

- **Lokalno:** ili `php artisan queue:work`, ili u `.env` **`QUEUE_CONNECTION=sync`** da se jobovi izvrše odmah (preporuka za QA — v. `docs/project-conventions.md`).
- **Produkcija:** Supervisor (ili drugi process manager) drži `php artisan queue:work` uvek aktivan.

---

## Provider iza interfejsa

- **PaymentService** (interface): `createSession(TempData): PaymentSessionResult`, `pay(TempData): PaymentResult`.
- **PaymentSessionResult**: success, payment_url (za redirect), error_message (ako gateway nedostupan).
- **FakePaymentProvider**: createSession vraća URL na fake bank stranicu (/payment/fake-bank?tx=...); pay() za test state machine.
- **RealPaymentProvider**: createSession() poziva Bankart kada je `BANK_DRIVER=bankart` i `.env` kompletan (v. `.env.example`).
- Config: `config/payment.php` → driver iz `BANK_DRIVER` (npr. `fake` \| `bankart`).

---

## Flow (kratko)

1. **POST /checkout** → validacija, dostupnost, temp_data (pending), createSession(sync) → **redirect na payment_url** ili 503.
2. Korisnik plaća na bank stranici (ili na fake bank stranici bira Success/Fail).
3. Gateway (ili fake bank form) šalje **`POST /api/payment/callback`** sa merchant_transaction_id i status.
4. Webhook: validacija → **PaymentCallbackJob::dispatch(payload)** → 202.
5. **PaymentCallbackJob**: na success → Reservation, **temp_data.status = processed** (red se **ne briše** — audit); **`ProcessReservationAfterPaymentJob`** se šalje iz handlera osim kada su **`BANK_DRIVER=fake`** i **`FISCALIZATION_DRIVER=fake`** — tada ga odmah nakon callbacka u istom HTTP zahtjevu pokreće **`FakeBankCompleteController`** sa fiskal scenarijem iz kombinovane QA forme (v. ispod). Na failed → ažuriranje `temp_data` + **ErrorClassifier**; na timeout → `late_success` gde je predviđeno.
6. UI može koristiti **GET /reservation-status/{merchant_transaction_id}** (polling) za status.

---

## Fake QA stranica (test — banka + fiskal u jednom koraku)

- **GET /payment/fake-bank?tx={merchant_transaction_id}** — jedna forma: **bank_scenario** (A) + **fiscal_scenario** (B, aktivno samo kad je banka success), jedan **POST /payment/fake-bank/complete**.
- **GET /fake-bank/complete?tx=...&scenario=...&fiscal_scenario=...** — backward compat (bez `fiscal_scenario` podrazumijeva fiskal **success**).
- **Frontend NIKAD ne poziva** `POST /api/payment/callback`.
- Nakon complete: redirect na **`/payment/return?merchant_transaction_id=...`**, zatim uobičajeni banner + redirect na guest/panel.

---

## Fajlovi

| Fajl | Namena |
|------|--------|
| `App\Contracts\PaymentService` | Interface – createSession (sync), pay (za job). |
| `App\Contracts\PaymentSessionResult` | success, payment_url, error_message. |
| `App\Contracts\PaymentResult` | success / failed / timeout (za pay()). |
| `App\Services\Payment\FakePaymentProvider` | createSession → fake bank URL; pay() simulacija. |
| `App\Services\Payment\RealPaymentProvider` | createSession → Bankart (real). |
| `App\Jobs\PaymentCallbackJob` | Idempotentan po merchant_transaction_id; rezervacija, fiskalizacija, status. |
| `App\Jobs\ProcessReservationAfterPaymentJob` | Fiskalizacija + PDF/email posle uspešnog plaćanja (v. success-payment-pipeline.md). |
| `App\Http\Controllers\CheckoutController` | Validacija, dostupnost, temp_data, createSession, redirect ili 503. |
| `App\Http\Controllers\Api\PaymentCallbackController` | API callback: validacija potpisa + payload, dispatch job, 202/400. |
| `config/payment.php` | Bankart/fake driver preko `BANK_DRIVER` i povezane env varijable. |
| `App\Http\Controllers\ReservationStatusController` | Polling: GET po merchant_transaction_id. |
| `App\Http\Controllers\FakeBankCompleteController` | Samo test: POST kombinovana forma + GET complete; `dispatchSync` callback; kad su oba drivera fake i bank success → `ProcessReservationAfterPaymentJob` sa izabranim fiskal scenarijem. |

---

## Success payment pipeline (fiskalizacija → PDF → email)

V. **docs/success-payment-pipeline.md**: pending → processed → create reservation (bez fiscal polja) → FiscalizeReservationJob → na uspeh fiscal_* + GenerateInvoicePdfJob + SendInvoiceEmailJob; mail sa bus@kotor.me.

---

## Callback handling (CANCEL/ERROR, idempotency, redirect)

V. **docs/payment-callback-handling.md**: validacija potpisa (400 + log), idempotentnost (final status), CANCEL/ERROR → failed + raw payload + oslobodi soft-lock, PaymentFailed event, redirect guest/auth, GET /payment/result.

---

## Concurrency

V. **docs/payment-concurrency.md**: jedinstven merchant_transaction_id, validacija potpisa u callback-u, idempotentnost job-a, nema deljenog stanja, paralelna plaćanja.

---

## Provera (checklist)

- [x] User se redirect-uje na bank payment page odmah nakon "Pay".
- [x] Controller: validacija, provera dostupnosti, temp_data (pending), createSession (sync), redirect ili 503.
- [x] Nema obrade statusa plaćanja u HTTP request-u.
- [x] Rezultat plaćanja: webhook/callback + PaymentCallbackJob (rezervacija, fiskalizacija, email, retries).
- [x] Ako gateway spor/nedostupan: 503, bez kreiranja rezervacije.
- [x] Payment provider iza interfejsa (PaymentService, Fake/Real).
- [x] Queue obavezna za PaymentCallbackJob.
