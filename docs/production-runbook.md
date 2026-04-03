# Production runbook (kratka checklist)

Operativni koraci posle deploy-a ili pri prvom puštanju u produkciju. Detalji tokova: `success-payment-pipeline.md`, `payment-architecture.md`.

---

## Pre deploy-a

- [ ] **`.env` na serveru:** `APP_ENV=production`, `APP_DEBUG=false`, **`APP_URL`** = javni HTTPS URL (isti kao u browseru).
- [ ] **`BANK_DRIVER=bankart`**, **`FISCALIZATION_DRIVER=real`** (ne ostavljati `fake` u produkciji).
- [ ] Bankart: `BANKART_*` popunjeno; fiskal: `FISCAL_API_URL`, `FISCAL_API_TOKEN`, `FISCAL_ENU_IDENTIFIER`, `FISCAL_USER_CODE`, `FISCAL_USER_NAME`.
- [ ] **`QUEUE_CONNECTION=database`** (ili redis) — ne `sync` u produkciji za callback/fiskal/email.
- [ ] **`SESSION_SECURE_COOKIE=true`** ako je sajt isključivo preko HTTPS.
- [ ] Mail: `MAIL_*` / `MAIL_NOREPLY_*` za stvarni SMTP; testirati slanje na stagingu.
- [ ] Opciono: timeout env za Bankart/fiskal — vidi `config/http-outbound.php` (uključujući per-endpoint: `BANKART_CREATE_SESSION_*`, `FISCAL_DEPOSIT_*`, `FISCAL_RECEIPT_*`, budući `BANKART_STATUS_INQUIRY_*`).
- [ ] `PAYMENT_STALE_PENDING_WARN_AFTER_MINUTES` — prag za log `payment_pending_too_long` (default 12).

---

## Deploy komande (sa servera, iz roota aplikacije)

```bash
php artisan migrate --force
npm ci && npm run build
php artisan queue:restart
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

- [ ] **`storage/` i `bootstrap/cache/`** — vlasnik procesa koji pokreće PHP (npr. `www-data`) mora moći da piše (`storage/logs`, `framework/cache`, `framework/views`).

---

## Queue worker

- [ ] Pokrenuti **`php artisan queue:work`** (ili systemd/supervisor) sa istim `APP_ENV` / `.env` kao web.
- [ ] **Restart policy:** proces mora da se podigne ponovo posle pada (systemd `Restart=always` ili ekvivalent).
- [ ] **Memorija:** ograniči `--memory` (npr. `php artisan queue:work --memory=512`) da worker ne raste beskonačno; kombinuj sa restart policy.
- [ ] Posle **deploy-a:** **`php artisan queue:restart`** (ili potpuni restart servisa) — signal svim workerima da završe trenutni job i učitaju novi kod.
- [ ] Timeout **supervisor/systemd** oko workera treba da bude **veći** od najdužeg joba (npr. ≥ **130s** zbog `ProcessReservationAfterPaymentJob`).
- [ ] **Email `EMAIL_SENDING`:** ako job baci izuzetak ili istroši retry-eve, `failed()` / `catch` vraća **`email_sent`** na **`EMAIL_NOT_SENT`** — nema trajnog „zaglavljenog“ slanja u bazi.

### Kako proveriti da worker radi

- Proces: `ps aux | grep "queue:work"` (ili lista servisa u systemd).
- Posle starta u **production** u `payments.log` treba **jednom** po procesu: **`queue_worker_booted`** (PID u kontekstu).
- Test job: npr. kratki test checkout sa queue ≠ `sync` i provera da se job obradi (`jobs` tabela se čisti ili `failed_jobs` ostaje prazno).

---

## Posle deploy-a (smoke)

- [ ] **`GET /up`** (health) — 200.
- [ ] Jedan test checkout (staging ili mali iznos): callback → rezervacija u bazi → `payments.log` bez greške.
- [ ] **`storage/logs/payments.log`** — proveriti `payment_reservation_created`, po potrebi `payment_fiscal_success` / `invoice_email_sent`.

---

## Česti problemi

| Simptom | Provera |
|--------|---------|
| Mejl ne stiže | `QUEUE_CONNECTION`, da li worker radi; `invoice_sent_at` / log `invoice_email_*` |
| Fiskal ne prolazi | `post_fiscalization_data`, komanda `post-fiscalization:retry`, `payments.log` |
| Callback ne radi | URL banke → `POST /api/payment/callback`, potpis, `APP_URL` |

---

## Dokumentacija vezana za hardening

- **`docs/production-hardening.md`** — timeout/retry politika, „stuck“ scenariji, log eventi.
