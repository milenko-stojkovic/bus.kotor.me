# Payment callback handling

**Bank callback mora biti na API ruti (POST /api/payments/callback), ne na web.** Razlozi: banka ne šalje cookies/CSRF; web middleware (session, redirects) može odbiti ili pokvariti callback (dokazano u produkciji V1). Callback je stateless; vraća samo 200/202/400; redirect korisnika kasnije preko frontend polling-a /payment/result.

**Nikad ne koristiti bank callback za frontend redirect ili UI flow. Bank callback je isključivo machine-to-machine.** Frontend NIKAD ne sme da poziva POST /api/payments/callback. Za test (fake bank) koristi se poseban endpoint: POST /payment/fake-bank/complete.

Pravila za validaciju callback-a, idempotentnost, CANCEL/ERROR, notifikacije i redirect.

---

## 1. Validacija callback-a (uvek prvo)

- **Validirati potpis banke** (signature / hash) pre bilo kakve obrade.
- Ako validacija padne → **logovati** (payments channel) i **prekinuti obradu** → **HTTP 400**.

---

## 2. Idempotentnost (callback može stići više puta)

- Ako je **temp_data.status** već **failed** ili **processed** → prekinuti obradu (return).
- **Nikad ne brisati temp_data fizički** (audit trail); samo menjati status.
- Cron CleanupOldTempData ne briše temp_data – redovi se zadržavaju.

---

## 3. CANCEL / ERROR logika (čišćenje)

Ako je status **CANCEL** ili **ERROR** (ili **failed**):

- **temp_data.status** = `failed`
- Sačuvati:
  - **raw_callback_payload** (JSON) u temp_data
  - **callback_error_code** i **callback_error_reason** (ako banka šalje)
- **NE** kreirati reservation
- **Osloboditi termin** (soft-lock): decrement `daily_parking_data.pending` za (reservation_date, drop_off_time_slot_id)
- Emitovati **PaymentFailed** event (log + opciono email)

TempData ostaje u bazi radi audit trail-a; više se ne može koristiti za retry bez nove inicijacije plaćanja.

---

## 4. Obaveštavanje korisnika

- **Unified notifier:** poruka za failed: *"Plaćanje je otkazano ili nije uspelo. Rezervacija nije sačuvana."*
- **Web:** frontend dobija poruku preko **GET /payment/result?merchant_transaction_id=...** (JSON `message`) i prikazuje je (flash / UI).
- **Email (opciono):** listener **NotifyUserPaymentFailed** – kada bude Mailable, slati email korisniku.

---

## 5. Redirect logika (guest vs auth)

- **Guest:** redirect → **route('reservations.create')** (forma za novu rezervaciju); u session može biti error message i opciono prethodno uneseni podaci.
- **Auth:** redirect → **route('profile.reservations')** (trenutno vodi na dashboard) sa notifikacijom "Plaćanje nije uspelo".
- **Nikada** ne vraćati korisnika na gateway URL; uvek u aplikaciju sa jasnim statusom.

Frontend: ping **GET /payment/result?merchant_transaction_id=...** → backend vraća JSON: `{ status, user_type, message?, redirect_guest, redirect_auth }`. Na osnovu toga frontend radi redirect na odgovarajuću stranicu i prikazuje poruku.

---

## 6. Queue job – SUCCESS vs CANCEL/ERROR grana

- **SUCCESS** → kreiraj reservation, temp_data.status = processed, oslobodi soft-lock (pending→reserved), PostFiscalizationJob, email (cron).
- **CANCEL/ERROR/failed** → mark failed, sačuvaj raw payload + error, oslobodi soft-lock (decrement pending), **emit PaymentFailed event** (log + notify).

Job **emituje PaymentFailed** event koji frontend ne prima direktno – frontend otkriva status preko **/payment/result**. Event služi za backend (log u payments channel, email).

---

## 7. Frontend flow

- Callback endpoint je **API** (POST /api/payments/callback); **ne radi redirect**, nema session/cookies.
- Callback setuje status u bazi (preko job-a); vraća samo 202 ili 400.
- **Frontend success/cancel URL** (stranica na koju banka vrati korisnika):
  - Frontend ping-a **GET /payment/result?merchant_transaction_id=...**
  - Backend vraća JSON: `{ status: "success"|"failed"|"pending", user_type: "guest"|"auth", message?, redirect_guest, redirect_auth }`
  - Frontend radi redirect na odgovarajuću stranicu i prikazuje poruku.

---

## Bonus (production safety)

- **Log** svaki CANCEL/ERROR u **payments** channel (storage/logs/payments.log).
- **Rate limiting** na callback: **throttle 60/min** (sprečavanje brute force callback spam).
- **Metric:** broj neuspešnih plaćanja – log u payments channel sa merchant_transaction_id; kasnije može se agregirati za monitoring.
