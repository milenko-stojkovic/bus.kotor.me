# Production hardening (referenca)

Kratak pregled odluka za plaćanje, fiskal, email i red. Operativna checklista: **`production-runbook.md`**.

---

## 1. HTTP timeout / retry

| Izlazni poziv | Connect / response timeout | Retry |
|---------------|----------------------------|--------|
| **Bankart** debit (create session) | `config/http-outbound.php` → `bankart` (`BANKART_HTTP_*` env) | **Nema** automatskog HTTP retry-a — drugi POST može imati nuspojave kod provajdera. |
| **Fiskal** (real i fake HTTP) | `http-outbound.fiscal` (`FISCAL_HTTP_*`) | **Jedan** unutrašnji retry para deposit+receipt kada provajder vrati **grešku 58** (nema depozita); deposit je idempotentan (`Amount=0`). |
| **Laravel jobovi** | N/A | Eksplicitan **`backoff()`** na ključnim jobovima; idempotentnost: vidi job komentare + `ShouldBeUnique` gde postoji. |

---

## 2. Ključni jobovi (tries / timeout / backoff)

| Job | tries | timeout (s) | backoff (s) |
|-----|-------|-------------|-------------|
| `PaymentCallbackJob` | 3 | 60 | 60, 300, 900 |
| `PaymentJob` | 3 | 60 | 30, 120, 300 |
| `ProcessReservationAfterPaymentJob` | 3 | 120 | 120, 600, 1800 |
| `SendInvoiceEmailJob` | 3 | 45 | 60, 180, 600 |
| `SendFreeReservationConfirmationJob` | 3 | 45 | 60, 180, 600 |

`failed()` na gore navedenima loguje u `payments` kanal kada su pokušaji istrošeni (osim gde je već bilo).

---

## 3. Log događaji (`storage/logs/payments.log`)

Strukturisani ključevi (uz `reservation_id` / `merchant_transaction_id` gde ima smisla):

- `payment_reservation_created` — rezervacija kreirana posle uspešnog plaćanja
- `payment_fiscal_success` — JIR upisan, sledi email
- `post_fiscalization_enqueued` — zapis u `post_fiscalization_data`, nefiskalni email
- `invoice_email_sent` / `invoice_email_send_failed` / `invoice_email_job_exhausted`
- `free_reservation_email_sent` / `free_reservation_email_send_failed` / `free_reservation_email_job_exhausted`
- `payment_callback_job_exhausted`, `payment_job_exhausted`, `process_reservation_after_payment_job_exhausted`
- `production_fake_driver_active` — upozorenje ako je u `production` aktivan fake bank/fiscal (najviše jednom na ~12h po kešu)

Callback prima i dalje: `Payment callback received` / `accepted` / itd. (postojeći tok).

---

## 4. „Stuck“ scenariji i recovery

| Scenario | Šta postoji | Napomena |
|----------|-------------|----------|
| `temp_data` dugo **pending** | Scheduler: `payment:check-pending-inquiry` | **Real** status inquiry još **TODO** u `RealPaymentStatusInquiryService` — cron neće pomoći dok se ne implementira HTTP poziv. |
| Plaćena rezervacija bez **fiscal_jir** | `post_fiscalization_data` + `post-fiscalization:retry` + admin akcije | Email sa nefiskalnim PDF i dalje ide iz `ProcessReservationAfterPaymentJob`. |
| Email nije poslat | `invoice_sent_at` null, `email_sent`, log `invoice_email_*` | Queue worker mora raditi; job retry + `failed()` resetuje `email_sent`. |
| Gomilanje **post_fiscalization_data** | Retry komanda + admin | Proveriti `next_retry_at`, `resolved_at`. |
| Job pao posle delimične obrade | Idempotentni koraci (callback unique, `invoice_sent_at`, fiscal check) | Retry joba; `failed()` loguje za ručnu proveru. |

---

## 5. Config / env

- U **production**, ako je `BANK_DRIVER` ili `FISCALIZATION_DRIVER` = `fake`, u log upisuje se **`production_fake_driver_active`** (ograničeno kešom).
- **`APP_DEBUG=false`**, **`APP_URL`** HTTPS — obavezno za ispravne redirecte i linkove.
- **`QUEUE_CONNECTION`:** ne `sync` za produkciju.
