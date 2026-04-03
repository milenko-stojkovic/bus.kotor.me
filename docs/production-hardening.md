# Production hardening (referenca)

Kratak pregled odluka za plaćanje, fiskal, email i red. Operativna checklista: **`production-runbook.md`**.

---

## 1. HTTP timeout / retry

Helper: **`App\Support\HttpOutboundConfig`** spaja root vrednosti sa opcionim per-endpoint override-ima u **`config/http-outbound.php`**.

| Izlazni poziv | Konfiguracija | Retry |
|---------------|---------------|--------|
| **Bankart** create session | `HttpOutboundConfig::bankart('create_session')` — fallback na `bankart.connect_timeout` / `bankart.timeout` | **Nema** automatskog HTTP retry-a na debit. |
| **Bankart** status inquiry (buduće) | `HttpOutboundConfig::bankart('status_inquiry')` | Kada se implementira `PaymentStatusInquiryService::inquire()`. |
| **Fiskal** deposit | `HttpOutboundConfig::fiscal('deposit')` | **Jedan** unutrašnji retry para deposit+receipt na grešku **58**; deposit `Amount=0` je idempotentan. |
| **Fiskal** receipt | `HttpOutboundConfig::fiscal('receipt')` | Isto. |
| **Laravel jobovi** | N/A | Eksplicitan **`backoff()`**; idempotentnost: job komentari + `ShouldBeUnique` gde postoji. |

---

## 2. Ključni jobovi (tries / timeout / backoff)

| Job | tries | timeout (s) | backoff (s) |
|-----|-------|-------------|-------------|
| `PaymentCallbackJob` | 3 | 60 | 60, 300, 900 |
| `PaymentJob` | 3 | 60 | 30, 120, 300 |
| `ProcessReservationAfterPaymentJob` | 3 | 120 | 120, 600, 1800 |
| `SendInvoiceEmailJob` | 3 | 45 | 60, 180, 600 |
| `SendFreeReservationConfirmationJob` | 3 | 45 | 60, 180, 600 |

`failed()` na gore navedenima loguje u `payments` kanal kada su pokušaji istrošeni.

---

## 3. Log događaji (`storage/logs/payments.log`)

Strukturisani ključevi (gde je moguće: **`merchant_transaction_id`** + **`reservation_id`** / **`temp_data_id`** / **`user_id`**):

- `payment_reservation_created`, `payment_fiscal_success`, `post_fiscalization_enqueued`
- `payment_pending_too_long` — `temp_data` u **pending** duže od `payment.stale_pending_warn_after_minutes` (npr. 12); **nema promene statusa**; throttle keš po `temp_data_id` (6h)
- `invoice_email_sent` / `invoice_email_send_failed` / `invoice_email_job_exhausted`
- `free_reservation_email_sent` / `free_reservation_email_send_failed` / `free_reservation_email_job_exhausted`
- `payment_callback_job_exhausted`, `payment_job_exhausted`, `process_reservation_after_payment_job_exhausted`
- `queue_worker_booted` — jednom po PHP procesu queue workera u **production** (event `WorkerStarting`)
- `production_fake_driver_active` — fake bank/fiscal u production (throttle keš)

Callback prima i dalje: `Payment callback received` / `accepted` / itd.

---

## 4. „Stuck“ scenariji i recovery

| Scenario | Šta postoji | Napomena |
|----------|-------------|----------|
| `temp_data` dugo **pending** | `payment:check-pending-inquiry` → **`payment_pending_too_long`** | Status se **ne menja** automatski. Kada `PaymentStatusInquiryService::isImplemented()` bude **true**, ista komanda poziva `inquire()` posle `pending_inquiry_after_minutes`. |
| Plaćena rezervacija bez **fiscal_jir** | `post_fiscalization_data` + `post-fiscalization:retry` + admin | Nefiskalni email iz `ProcessReservationAfterPaymentJob`. |
| Email nije poslat | `invoice_sent_at`, `email_sent`, `invoice_email_*` | Worker + retry; **`failed()`** vraća **`email_sent`** na **`EMAIL_NOT_SENT`** (nema trajnog `EMAIL_SENDING`). |
| Gomilanje **post_fiscalization_data** | Retry komanda + admin | `next_retry_at`, `resolved_at`. |
| Job pao posle delimične obrade | Idempotentni koraci | Retry; `failed()` za ručnu proveru. |

---

## 5. Config / env

- U **production**, fake bank/fiscal → **`production_fake_driver_active`** (keš ~12h).
- **`APP_DEBUG=false`**, **`APP_URL`** HTTPS.
- **`QUEUE_CONNECTION`:** ne `sync` u produkciji.

---

## 6. `PaymentStatusInquiryService`

- **`isImplemented()`:** `false` na fake i real dok se ne poveže Bankart status API; tada postaviti **`true`** samo u real implementaciji.
- **`inquire()`:** poziva se **samo** ako je `isImplemented()` true; inače cron samo loguje stale pending (gore).
