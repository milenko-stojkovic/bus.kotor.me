# Payment states

Stanja plaćanja i fiskalizacije. Rezervacija se **uvek** kreira na **success**; neuspeh fiskalizacije **ne blokira** generisanje računa.

---

## Payment states (flow)

- **pending** – plaćanje u toku (temp_data.status = pending).
- **success** – banka vratila SUCCESS → rezervacija se **kreira** (reservation), temp_data → processed. Fiskalizacija se pokreće u pozadini (ProcessReservationAfterPaymentJob).
- **failed** – banka vratila CANCEL/ERROR → temp_data → canceled. **Nema** rezervacije.

---

## Flow: pending → success → failed

- Bank callback **SUCCESS** → rezervacija se kreira, temp_data = processed.
- Bank callback **CANCEL/ERROR** → temp_data = canceled, rezervacija se **ne** kreira.

---

## Flow: pending → success → fiscalization_failed → post_fiscalization_data

- Bank callback **SUCCESS** → rezervacija se kreira (čak i ako će fiskalizacija da padne).
- ProcessReservationAfterPaymentJob pokušava fiskalizaciju.
- **Fiskalizacija uspe** → reservation.fiscal_* set, fiskalni PDF + email.
- **Fiskalizacija ne uspe** → **fiscalization_failed**:
  - upis u **post_fiscalization_data** (reservation_id, error, attempts, next_retry_at);
  - **račun se i dalje generiše** – nefiskalni PDF sa napomenom + email (ne blokira se invoice).
- Retry fiskalizacije (cron post-fiscalization:retry) pri uspehu ažurira reservation i šalje fiskalni PDF.

---

## Timeout callback (status inquiry)

- Ako **callback od banke nikad ne stigne** (mreža, firewall, kratki outage), cron **payment:check-pending-inquiry** proverava pending starije od X min i **direktno kod banke** (status inquiry endpoint) pita status.
- Ako banka kaže **SUCCESS** → pokreće se **isti flow kao callback** (PaymentSuccessHandler: rezervacija, temp_data → processed, ProcessReservationAfterPaymentJob).
- Konfiguracija: `payment.pending_inquiry_after_minutes` (npr. 10). Status inquiry je iza interfejsa `PaymentStatusInquiryService` (fake vraća null; real poziva bank API – TODO).

---

## Pravila

- **Rezervacije se kreiraju na success** – čak i ako fiskalizacija kasnije ne uspe. Kreiranje rezervacije ne zavisi od fiskalnog API-ja.
- **Neuspeh fiskalizacije ne sme da blokira generisanje računa** – kupac uvek dobija račun (fiskalni ili nefiskalni) odmah posle uspešnog plaćanja.
