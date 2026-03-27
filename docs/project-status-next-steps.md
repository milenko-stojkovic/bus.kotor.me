# Status projekta i sledeći koraci

Poslednje ažuriranje: 2026-02-22

Ovaj dokument služi kao brz podsetnik "šta je gotovo" i "šta je ostalo" da bi nastavak rada bio lakši.

## 1) Šta je već urađeno

- Implementiran kompletan payment V1 tok: checkout, redirect na banku, callback obrada, success/failed grane.
- Uveden `merchant_transaction_id` (backend generisanje i mapiranje callback-a preko njega).
- Uveden `retry_token` za guest korisnike i retry API:
  - `GET /api/reservations/retry/{retry_token}`
  - redirect kod neuspeha na `/reservations?retry_token=...`
- Uveden state handling za:
  - `pending -> processed`
  - `pending -> canceled/expired`
  - `pending -> late_success`
- Fiskalizacija i fallback:
  - success ide kroz obradu rezervacije + fiskalizaciju
  - ako fiskalizacija padne, čuva se post-fiscalization podatak i šalje nefiskalizovan račun
  - postoji retry mehanizam za naknadnu fiskalizaciju
- Email i lokalizacija:
  - slanje računa kroz job
  - jezik maila po korisniku / guest locale
- Audit/logging pokriven za ključne payment faze.

## 2) Implementirani zahtevi (trenutno stanje)

## Payment/failover zahtevi

- Backend generisanje `merchant_transaction_id`: implementirano.
- Callback preko API rute (`/api/payment/callback`): implementirano.
- Retry flow za guest korisnika bez ponovnog unosa forme: implementirano.
- Oslobađanje soft lock-a pri canceled/error granama: implementirano.
- Snapshot podaci upisani u rezervaciju (ne zavise od kasnijih izmena korisnika): implementirano.

## Fake test zahtevi

- Fake bank tok (success/fail/cancel) za lokalno testiranje: implementirano.
- Fake fiskal kontroler:
  - payload `forceFail=true`: implementirano
  - header `X-Fake-Fail: 1`: implementirano
  - header `X-Fake-Timeout: 1`: implementirano (`sleep(10)` + HTTP 504)

## 3) Poznata odstupanja / otvorene tačke

- `createSession` je i dalje sync u web request-u (nije full async init preko queue job-a).
- `RealCallbackSignatureValidator` je ključna produkcijska tačka: proveriti da je HMAC/provera potpisa 100% po specifikaciji banke.
- `late_success` automatsko finalizovanje rezervacije nije potpuno zatvoreno (deo logike je ostavljen za sledeću fazu/manual review).

## 4) Šta dalje treba raditi (prioriteti)

1. Produkcijska sigurnost callback-a:
   - potvrditi/implementirati finalnu proveru potpisa po bank spec-u
   - end-to-end test sa real callback primerima
2. Late success automatizacija:
   - definisati pravila kada se rezervacija automatski kreira
   - gde ide manual review/incident kada ne može automatski
3. Operativni hardening:
   - proveriti timeout/retry politike za spoljne servise
   - dopuniti monitoring i alarme za payment/fiskalizaciju
4. Po potrebi (ako UX traži):
   - migracija sa sync createSession na async init flow

5. Fiskal račun nakon uspešnog retry-a (TODO, ne implementirati sada):
   - Kada naknadna fiskalizacija uspe (cron `post-fiscalization:retry` ili admin retry), formalizovati generisanje **kompletnog fiskalizovanog računa (PDF)**.
   - Poslati korisniku **kompletan fiskalizovani račun** (mail) nakon uspešnog retry-a.
   - Jasno razdvojiti nefiskalni fallback dokument (poslat odmah nakon plaćanja) od naknadno poslatog fiskalizovanog računa.
   - Ne blokirati slanje fiskalnog računa ako je ranije poslat nefiskalni fallback.

## 5) Brzi test checklist (lokalno)

- `BANK_DRIVER=fake`, `FISCALIZATION_DRIVER=fake`
- `php artisan queue:work`
- Proći:
  - success scenario
  - fail/cancel scenario + retry preko `retry_token`
  - fake fiskal fail (`X-Fake-Fail: 1`)
  - fake fiskal timeout (`X-Fake-Timeout: 1`)

## 6) Real callback E2E - uslov za smislen test

- Puni real callback E2E test nema smisla sa lokalnog računara, jer banka traži da real payment/callback pozivi dolaze sa servera gde je hostovan `bus.kotor.me`.
- Sandbox u klasičnom smislu nije dostupan; test zavisi od bankinog simulation moda.
- Zato real callback E2E potvrdu planirati tek kada su ispunjena oba uslova:
  1. V2 aplikacija je podignuta na web server (hostovani domen),
  2. banka ponovo aktivira `-sim` nalog / simulation mode.
- Tek tada validirati kompletan tok: real init -> real callback (signature + payload shape) -> V2 state machine -> reservation status.
