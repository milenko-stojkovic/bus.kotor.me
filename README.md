<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Project docs (status, TODO, AI handoff)

- **New Cursor chat:** start with [`docs/handoff-new-chat.md`](docs/handoff-new-chat.md) (copy-paste first message template).
- **TODO / DONE / conventions:** [`docs/project-todo.md`](docs/project-todo.md), [`docs/project-done.md`](docs/project-done.md), [`docs/project-conventions.md`](docs/project-conventions.md) — index: [`docs/project-status-next-steps.md`](docs/project-status-next-steps.md).

## Payment & fiscalization: fake (local) vs real (production)

### Korišćenje fake flow-a lokalno

Za lokalni razvoj i testiranje koriste se simulirani banka i fiskalni servis:

1. U `.env` postavi:
   ```env
   BANK_DRIVER=fake
   FISCALIZATION_DRIVER=fake
   ```
2. Pokreni queue worker: `php artisan queue:work` (obrada callbacka i fiskalizacije ide preko jobova).
3. **Plaćanje:** nakon klika „Plati“ korisnik se prebacuje na fake bank stranicu (`/payment/fake-bank?tx=...`). Klik na **Success** ili **Fail** šalje simulirani callback; možeš i direktno otvoriti `GET /fake-bank/complete?status=success|error|cancel&tx={merchant_transaction_id}` za brzi test.
4. **Fiskalizacija:** kada je `FISCALIZATION_DRIVER=fake`, aplikacija šalje zahtjev na sopstveni endpoint `POST /api/fake-fiscalization`; odgovor simulira uspeh (ili grešku ako u payload-u pošalješ `forceFail=true`). Lokalno možeš koristiti i **FakeFiscalApiController** (rute `POST /api/efiscal/deposit`, `POST /api/efiscal/fiscalReceipt`) – isti fake API kao eksterni servis.

Rute za test: `POST /payment/fake-bank/complete` (form), `GET /fake-bank/complete?status=...&tx=...`, `POST /api/fake-fiscalization`, `POST /api/efiscal/deposit`, `POST /api/efiscal/fiscalReceipt`. Pravi bank callback je `POST /api/payment/callback` – frontend ga ne poziva.

**Fiskalizacija – driver:**

- **Lokalno:** `FISCALIZATION_DRIVER=fake` → koristi se fake fiskal API (FakeFiscalizationController ili FakeFiscalApiController na `/api/efiscal/*`). Postavi `FISCAL_API_URL=http://localhost/api` i `FISCAL_API_TOKEN=fake-token` u `.env.example` ako pozivaš efiscal rute.
- **Produkcija:** `FISCALIZATION_DRIVER=real` → koristi se pravi fiskalni servis. U `.env` postavi `FISCAL_API_URL` i `FISCAL_API_TOKEN` na vrednosti od dobavljača.

### Prebacivanje na realni Bankart u produkciji

Za produkciju sa pravim plaćanjem i fiskalizacijom:

1. U `.env` na serveru postavi:
   ```env
   BANK_DRIVER=bankart
   FISCALIZATION_DRIVER=real
   BANKART_SHARED_SECRET=<vrednost_od_banke>
   ```
   Dodatno (prema dogovoru sa Bankartom): `BANKART_API_URL`, `BANKART_API_USERNAME`, `BANKART_API_PASSWORD`, `BANKART_API_KEY`, itd. za iniciranje plaćanja.

2. **Callback:** Bankart šalje rezultat plaćanja na `POST /api/payment/callback`. Potpis se proverava pomoću `BANKART_SHARED_SECRET` (HMAC); path mora biti tačno `/api/payment/callback`. Ne koristi se session/cookie – endpoint je machine-to-machine.

3. **Fiskalizacija:** kada je `FISCALIZATION_DRIVER=real`, `FiscalizationService` poziva pravi fiskalni API preko `config('services.fiscal.api_url')` i `config('services.fiscal.api_token')`. Kada je `fake`, koristi se FakeFiscalApiController (rute `/api/efiscal/deposit`, `/api/efiscal/fiscalReceipt`) ili postojeći `POST /api/fake-fiscalization`.

4. Queue worker mora biti aktivan u produkciji (npr. Supervisor) da se `PaymentCallbackJob` i posle plaćanja fiskalizacija i email obrade.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
