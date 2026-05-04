# Project TODO (otvoreno)

**Poslednje ažuriranje:** 2026-05-01  

Stavke su prioritetne grupe. Kada nešto **završiš**, premesti opis u `docs/project-done.md` i ukloni odavde.

---

## Limo service

Inicijalna specifikacija: **[limo-service.md](./limo-service.md)**.

- [ ] Implementirati **DB foundation** za Limo (tabele po specifikaciji, bez preskakanja audit/fiskal polja gdje treba).
- [ ] **Agency panel:** QR generisanje, lista aktivnih QR za dan, PDF/print za QR (`/panel/limo`).
- [ ] **Limo evidenter** modul (predlog prefiksa `/limo`, PWA-first, API-friendly).
- [ ] **QR pickup:** validacija, kreiranje pickup eventa, advance usage ledger, brisanje aktivnog tokena.
- [ ] **Fallback tablica:** OCR ili ručni unos, dokaz foto, kreiranje eventa i advance usage gdje je primjenjivo.
- [ ] **Limo fiskalizacija:** pipeline, PDF, email agenciji (po pravilima iz `limo-service.md`).
- [ ] **Admin analytics:** uključiti Limo prihod i pregled pickup događaja.
- [ ] **Komunalna policija:** definisati workflow incident izvještavanja (ko šalje, format, email) — v. TODO u `limo-service.md`.
- [ ] **Native Android:** odluka nakon PWA field testa da li je potreban poseban klijent.

---

## 1. Produkcija / Bankart

- [ ] Finalna provera **HMAC / potpisa** callback-a po specifikaciji banke (`RealCallbackSignatureValidator` i srodnо).
- [ ] **E2E** sa realnim callback primerima na **hostovanom** okruženju (banka / simulation mod) — v. `docs/project-status-next-steps.md` § Real E2E.

## 2. `late_success` automatska obrada

- [ ] Komanda `reservations:assign-late-success` je **stub** — definisati pravila: kada auto-kreirati rezervaciju iz `temp_data`, kada ostaviti incident / admin (`AssignLateSuccessReservations.php`).

## 3. Operativno / audit

- [ ] Politika **retencije** `temp_data` (mapping `resolution_reason` → bucket, cleanup job ili SQL pravilo).
- [ ] Politika za **`notify_admin`** iz `ErrorClassifier` (kada slati alarm, kanal: mail/slack). *(Ovo je odvojeno od postojećeg **`AdminFiscalizationAlertService`**: fiskal alerti + email za kontradiktorni SUCCESS posle `canceled` — v. `docs/payment-state-machine.md`.)*
- [ ] Verifikacija lifecycle-a `temp_data` / `retry_token` u rubnim slučajevima.
- [ ] Gde trajno čuvati **fiskalnu klasifikaciju** za audit (postojeće tabele, bez novih ako moguće).

## 4. Fiskalni račun posle retry-a (ne žuriti implementaciju)

- [ ] Posle uspešne **naknadne fiskalizacije**: generisati **kompletan fiskalizovani PDF**, poslati korisniku; jasno razdvojiti od ranije poslatog **nefiskalnog** fallbacka; ne blokirati fiskal ako je nefiskal već poslat.

## 5. Opciono

- [ ] Migracija **`createSession`** sa sync web requesta na async init preko queue-a.

## 6. Future mobile platform readiness (plan / bez izmena koda sada)

- [ ] **Android (agencije):** planirati Android aplikaciju za agency tokove (ulogovani `/panel`).
- [ ] **Android (admin + control):** planirati Android aplikaciju za admin panel i control funkcionalnosti.
- [ ] **Control panel:** plan je da control panel bude **samo na Android-u**.
- [ ] **iPhone (opciono):** eventualno samo za agencije i samo ako bude traženo (nije prioritet sada).
- [ ] **Pre mobile produkcije:** uraditi poseban audit backend-a (kontroleri/Blade/session/redirect) i identifikovati mesta koja su previše web-specifična ili nisu API-friendly; definisati šta treba izvesti u čiste servise/API tokove za mobile klijente.
- [ ] **Ograničenje ovog TODO-a:** u ovoj fazi **NE** menjati PHP kod, rute, validaciju, poslovnu logiku niti uvoditi API — samo plan i evidencija.
