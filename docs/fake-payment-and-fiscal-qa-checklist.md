# Fake payment + fake fiscalization — QA checklist

Poslednje ažuriranje: 2026-04-01  
Namena: brz, praktičan checklist za ručno testiranje **fake bank** scenarija i **fake fiskal** scenarija (lokalno), uključujući DB verifikacije.

---

## 1) Setup (pre testa)

### 1.1 Preporučeni `.env` za lokalni QA

```env
APP_ENV=local
APP_DEBUG=true
BANK_DRIVER=fake
FISCALIZATION_DRIVER=fake
QUEUE_CONNECTION=sync
MAIL_MAILER=log
```

Ako koristiš `QUEUE_CONNECTION=database`, moraš imati aktivan worker:

- `php artisan queue:work`

### 1.2 Očisti cache kad menjaš `.env` / view

- `php artisan config:clear`
- `php artisan view:clear`

### 1.3 Rute koje koristiš u testu

- **Početna (guest UX)**: `GET /`
- **Checkout init**: `POST /checkout`
- **Simulacija banke (UI)**: `GET /payment/fake-bank?merchant_transaction_id=<tx>`
- **Fake bank complete**: `GET /fake-bank/complete?tx=<tx>&scenario=<scenario>`
- **Return page**: `GET /payment/return?merchant_transaction_id=<tx>`
- **Fake fiskal panel (scenario)**: `GET /payment/fake-fiscal` (vidljivo u local okruženju)

### 1.4 Šta očekivati na `/payment/return` (UX)

- Dok je **`temp_data.status = pending`**: stranica prikazuje poruku „plaćanje se obrađuje“, **polling** na `/payment/result`, dugmad osveži / nazad. **Layout:** gost → guest okvir; ulogovan korisnik → panel (**`x-app-layout`**).
- Kad callback kreira rezervaciju ili završi neuspeh: sledeći **reload** ili dolazak na return URL vodi na **redirect** — korisnik završava na **`/guest/reserve`** ili **`/panel/reservations`** sa **flash bannerom** (`checkout_banner`, grupe `checkout_result`), a ne na dugom „success“ ekranu na `/payment/return`.

---

## 2) “Happy path” (success → reservation → fake fiscal)

### 2.1 Kreiraj novi pokušaj plaćanja (pending)

- **Steps**
  - Otvori `GET /`
  - Izaberi datum + arrival + departure
  - Popuni podatke i klikni “Reserve”

- **Expected DB**
  - `temp_data.status = pending`
  - `daily_parking_data.pending` je **+1 za oba slota** (drop_off i pick_up) za taj datum

- **Brzi SQL**

```sql
select merchant_transaction_id, status, reservation_date, drop_off_time_slot_id, pick_up_time_slot_id, created_at, updated_at
from temp_data
order by id desc
limit 5;
```

### 2.2 Simuliraj uspešno plaćanje (fake bank: success)

- **Steps**
  - Otvori `GET /payment/fake-bank?merchant_transaction_id=<tx>`
  - Klikni scenario **success** (ili direktno):
    - `GET /fake-bank/complete?tx=<tx>&scenario=success`
  - Završi na `GET /payment/return?merchant_transaction_id=<tx>` (možeš kratko videti **pending** ekran dok job ne obradi callback; zatim **redirect** na guest/panel sa zelenom ili info porukom).

- **Expected DB**
  - `temp_data.status = processed`
  - postoji `reservations` red sa istim `merchant_transaction_id`
  - `daily_parking_data`: **pending -1** i **reserved +1** za oba slota (ne duplira ako su slotovi isti)

- **Brzi SQL**

```sql
-- phpMyAdmin ne razume :tx placeholder. Koristi jednu od opcija ispod.

-- Opcija A: direktno ubaci vrednost
select merchant_transaction_id, status, callback_error_code, callback_error_reason, resolution_reason
from temp_data
where merchant_transaction_id = 'PUT_TX_HERE';

select id, merchant_transaction_id, reservation_date, drop_off_time_slot_id, pick_up_time_slot_id, status, fiscal_jir, fiscal_ikof
from reservations
where merchant_transaction_id = 'PUT_TX_HERE';

-- Opcija B: koristi MySQL varijablu
set @tx := 'PUT_TX_HERE';

select merchant_transaction_id, status, callback_error_code, callback_error_reason, resolution_reason
from temp_data
where merchant_transaction_id = @tx;

select id, merchant_transaction_id, reservation_date, drop_off_time_slot_id, pick_up_time_slot_id, status, fiscal_jir, fiscal_ikof
from reservations
where merchant_transaction_id = @tx;
```

---

## 3) Fake bank — test matrix (7 scenarija)

Pokrećeš preko:

- `GET /fake-bank/complete?tx=<tx>&scenario=<scenario>`

Scenariji:

- `success`
- `cancel`
- `expired`
- `declined`
- `insufficient_funds`
- `3ds_failed`
- `system_error`

### Očekivanja za sve “fail/cancel” scenarije

- **Expected DB**
  - `temp_data.status` prelazi iz `pending` u **ne-success status** (npr. `canceled/expired/failed` zavisno od implementacije u grani)
  - `callback_error_code`, `callback_error_reason`, `resolution_reason` se popunjavaju (ako payload to ima)
  - `reservations` se **ne kreira**
  - `daily_parking_data.pending` se **vrati nazad** (pending -1) za oba slota

- **Brzi SQL**

```sql
-- phpMyAdmin ne razume :tx placeholder. Koristi PUT_TX_HERE ili @tx varijablu.

-- Opcija A: direktno ubaci vrednost
select merchant_transaction_id, status, callback_error_code, callback_error_reason, resolution_reason, updated_at
from temp_data
where merchant_transaction_id = 'PUT_TX_HERE';

select count(*) as reservation_count
from reservations
where merchant_transaction_id = 'PUT_TX_HERE';

-- Opcija B: koristi MySQL varijablu
set @tx := 'PUT_TX_HERE';

select merchant_transaction_id, status, callback_error_code, callback_error_reason, resolution_reason, updated_at
from temp_data
where merchant_transaction_id = @tx;

select count(*) as reservation_count
from reservations
where merchant_transaction_id = @tx;
```

---

## 4) Fake fiskal — scenariji i očekivanja

Fake fiskal driver u app-u radi “real-like” flow: **deposit → receipt**.

Scenario možeš setovati preko panela:

- `GET /payment/fake-fiscal` → set/clear scenario (session), plus prikaz `env` default-a (`FISCAL_FAKE_SCENARIO`)

### 4.1 Fiskal success

- **Setup**: scenario `success`
- **Steps**: uradi payment success (sekcija 2.2)
- **Expected DB**
  - `reservations.fiscal_jir` / `fiscal_ikof` popunjeni

### 4.2 Fiskal “deposit_missing (58)” na receipt-u

- **Setup**: scenario `deposit_missing`
- **Steps**: uradi payment success
- **Expected behavior**
  - Rezervacija je kreirana (payment success)
  - Fiskalizacija se tretira kao “pending/needs retry” (npr. zapis u `post_fiscalization_data` ako se koristi u ovoj grani)
  - Korisnik i dalje vidi success rezervacije (ne rollback)

### 4.3 Fiskal timeout / provider_down / validation_error

- **Setup**: scenario `timeout` ili `provider_down` ili `validation_error`
- **Steps**: uradi payment success
- **Expected behavior**
  - Rezervacija ostaje validna
  - Fiskal polja na reservation ostaju null
  - Sistem evidentira failure (log + eventualni retry zapis)

---

## 5) Ako zapne (najčešći uzroci)

### 5.1 `temp_data` ostaje `pending`

- Proveri `QUEUE_CONNECTION=sync` ili da li worker radi (`queue:work`).

### 5.2 Fake bank dugmad ne rade / ne vidiš scenarije

- Očisti view cache: `php artisan view:clear`

### 5.3 Fake fiskal panel se ne vidi posle success

- Panel je zasebna ruta: `GET /payment/fake-fiscal`
- Proveri da je `APP_ENV=local` (panel je tipično ograničen na local).

---

## 6) Copy/paste SQL za verifikaciju `daily_parking_data`

```sql
-- vidi slot brojače za konkretan tx
-- Opcija A: direktno ubaci vrednost
select reservation_date, drop_off_time_slot_id, pick_up_time_slot_id
from temp_data
where merchant_transaction_id = 'PUT_TX_HERE';

-- ručno upiši date/slot iz gornjeg select-a
-- Opcija A: direktno ubaci vrednosti
select date, time_slot_id, capacity, reserved, pending
from daily_parking_data
where date = 'YYYY-MM-DD'
  and time_slot_id in (DROP_OFF_ID, PICK_UP_ID);

-- Opcija B: koristi MySQL varijable
set @tx := 'PUT_TX_HERE';

select reservation_date, drop_off_time_slot_id, pick_up_time_slot_id
from temp_data
where merchant_transaction_id = @tx;

set @date := 'YYYY-MM-DD';
set @drop_off := 0;
set @pick_up := 0;

select date, time_slot_id, capacity, reserved, pending
from daily_parking_data
where date = @date
  and time_slot_id in (@drop_off, @pick_up);
```

