## Fake vs Real Contract Parity (Bankart + Fiskal)

Namena: da prelaz između fake i real driver-a bude što bezbolniji, samo promenom `.env`:

- `BANK_DRIVER=fake` ↔ `BANK_DRIVER=bankart`
- `FISCALIZATION_DRIVER=fake` ↔ `FISCALIZATION_DRIVER=real`

Fokus: contract parity (payload/response shape + failure semantika), bez menjanja core flow-a.

---

## 1) Summary

Usklađen je fake bank callback i fake fiskal response da budu što bliži real provider shape-u, tako da prelaz `BANK_DRIVER=fake <-> bankart` i `FISCALIZATION_DRIVER=fake <-> real` bude “bezbolan” (bez menjanja core flow-a).

- Fake Bankart callback (GET flow) sada šalje realističan Bankart payload shape (`result`, `uuid`, `merchantTransactionId`, `purchaseId`, `transactionType`, `paymentMethod`, `amount`, `currency`, plus `code/message/adapterCode/...` na fail).
- Scenario banke + fiskala: jedna QA forma na **`/payment/fake-bank`** (POST) ili `?scenario=` / `?fiscal_scenario=` na **GET** `/fake-bank/complete`.
- `PaymentCallbackJob` sada može da klasifikuje grešku i kad `code/message` postoje samo u raw payload-u (kao kod real Bankart callback-a).
- Fake fiskal sada podržava scenario-driven real-like odgovore direktno na `/api/efiscal/deposit` i `/api/efiscal/fiscalReceipt` (isti shape kao real), uključujući `deposit_missing (58)` i ostale kodove.
- Fake fiskal u app flow-u sada radi `deposit → receipt` (i retry za 58), isto kao real.

---

## 2) Files changed

- `app/Http/Controllers/FakeBankCompleteController.php`
- `resources/views/payment/fake-bank.blade.php`
- `app/Jobs/PaymentCallbackJob.php`
- `app/Http/Controllers/Api/FakeFiscalApiController.php`
- `app/Services/FiscalizationService.php`
- `config/services.php`
- `app/Http/Controllers/Api/FakeFiscalizationController.php` (legacy endpoint usklađen)

---

## 3) Koji fake scenariji sada postoje

### 3.1 Fake Bankart (callback)

Pokrećeš preko **`GET /payment/fake-bank?tx=...`** (forma) ili direktno:

- URL: `GET /fake-bank/complete?tx=<merchant_transaction_id>&scenario=<bank_scenario>&fiscal_scenario=<fiscal_scenario>` (fiskal opciono, default success)

Scenariji:

- `success`
- `cancel` (user_cancelled)
- `expired` (transaction_expired)
- `declined` (authorization_declined)
- `insufficient_funds`
- `3ds_failed`
- `system_error`

Backward compat i dalje radi: `status=success|error|cancel`.

### 3.2 Fake fiskal (deposit + receipt)

Na nivou fake eksternog servisa:

- Query/Header/Body: `scenario=<scenario>` ili header `X-Fake-Scenario: <scenario>`

Scenariji:

- `success`
- `deposit_missing` (58) (smisleno samo na receipt-u; deposit ostaje OK)
- `already_fiscalized` (78)
- `validation_error` (11)
- `provider_down` (500)
- `tax_server_error` (900–920, npr. 905)
- `temporary_service_down` (999)
- `timeout`
- `malformed_response`

Za “app fake driver” (kada je `FISCALIZATION_DRIVER=fake`) možeš setovati:

- `FISCAL_FAKE_SCENARIO=deposit_missing|provider_down|...` (čitano iz `services.fiscalization.fake_scenario`)

---

## 4) Šta je usklađeno sa real

- Bank callback payload shape: koristi `result` + `merchantTransactionId` + `code/message/adapterCode` itd.
- Failure semantika: scenariji mapiraju na realistične `code/adapterCode` vrednosti koje `ErrorClassifier` razume.
- Fiskal response shape: `IsSucccess`, `ResponseCode`, `UIDRequest`, `Url.Value`, `Error.ErrorCode/ErrorMessage`, opcioni `RawMessage`.
- Deposit pre receipt: fake flow sada radi deposit → receipt (i retry na 58) kao real.
- Fiskal “interni broj” u PDF-u: fake fiskal u success response-u vraća verifikacioni URL (`Url.Value`) koji sadrži `ord=<DocumentNumber>` i `crtd=<YYYY-...>` tako da PDF može da parsira **Interni broj = `ord/YYYY`** iz `reservations.fiscal_qr` (kao u V1).
- Operator: fake fiskal vraća `Operator` (echo iz `ENUIdentifier` poslatog u request-u), pa se `reservations.fiscal_operator` popunjava isto kao kod real integracije.

---

## 5) Minimalne preostale razlike (prihvatljivo)

- Fake bank ne radi pravi `redirectUrl/init` kao real Bankart — koristi lokalnu fake bank stranicu + interni callback poziv (OK za lokalni QA).
- `PaymentCallbackController` ne validira kompletan real payload schema (`uuid/purchaseId/adapterMessage...`) — čuva se minimalni core contract; raw payload se čuva u `temp_data.raw_callback_payload`.

