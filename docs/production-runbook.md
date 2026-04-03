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
- [ ] Opciono: `BANKART_HTTP_CONNECT_TIMEOUT`, `BANKART_HTTP_TIMEOUT`, `FISCAL_HTTP_CONNECT_TIMEOUT`, `FISCAL_HTTP_TIMEOUT` (vidi `config/http-outbound.php`).

---

## Deploy komande (sa servera, iz roota aplikacije)

```bash
php artisan migrate --force
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

- [ ] **`storage/` i `bootstrap/cache/`** — vlasnik procesa koji pokreće PHP (npr. `www-data`) mora moći da piše (`storage/logs`, `framework/cache`, `framework/views`).

---

## Queue worker

- [ ] Pokrenuti **`php artisan queue:work`** (ili systemd/supervisor) sa istim `APP_ENV` / `.env` kao web.
- [ ] Posle izmene koda: **restart worker-a** (inace stari kod u memoriji).
- [ ] Worker timeout na procesu treba da bude **veći** od najdužeg joba (npr. ≥ **130s** zbog `ProcessReservationAfterPaymentJob`).

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
