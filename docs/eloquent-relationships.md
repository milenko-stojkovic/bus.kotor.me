# Eloquent relacije (iz migracija)

Pregled FK iz baze i preporučene relacije za modele.

**Poslednje ažuriranje:** 2026-06-19 — dopunjeno `reservation_kind`, `daily_ticket`, MEGA arhiva, FZBR, avans.

---

## 1. User

**Tabela:** `users` (id = bigInteger)

**Referencira ga:**
- `sessions.user_id`
- `temp_data.user_id` (nullable) – guest rezervacije imaju user_id = null
- `reservations.user_id` (nullable) – guest rezervacije imaju user_id = null
- `vehicles.user_id`

**Autentifikovani vs. guest:** v. `docs/auth-and-guests.md`. Relacije `reservations` i `tempData` vraćaju samo redove gde je `user_id` = ovaj user; guest rezervacije nemaju vezu sa users.

**Relacije u modelu User:**
```php
public function vehicles(): HasMany
{
    return $this->hasMany(Vehicle::class);
}

public function reservations(): HasMany
{
    return $this->hasMany(Reservation::class);
}

public function tempData(): HasMany
{
    return $this->hasMany(TempData::class);
}
```

---

## 2. Admin

**Tabela:** `admins` — nema FK. Nema relacija ka drugim tabelama.

---

## 3. ListOfTimeSlot (list_of_time_slots)

**Tabela:** `list_of_time_slots` (id = unsignedInteger)

**Referencira ga:**
- `daily_parking_data.time_slot_id`
- `reservations.drop_off_time_slot_id`, `reservations.pick_up_time_slot_id`
- `temp_data.drop_off_time_slot_id`, `temp_data.pick_up_time_slot_id`

**Relacije u modelu ListOfTimeSlot:**
```php
public function dailyParkingData(): HasMany
{
    return $this->hasMany(DailyParkingData::class, 'time_slot_id');
}

public function reservationsAsDropOff(): HasMany
{
    return $this->hasMany(Reservation::class, 'drop_off_time_slot_id');
}

public function reservationsAsPickUp(): HasMany
{
    return $this->hasMany(Reservation::class, 'pick_up_time_slot_id');
}

public function tempDataAsDropOff(): HasMany
{
    return $this->hasMany(TempData::class, 'drop_off_time_slot_id');
}

public function tempDataAsPickUp(): HasMany
{
    return $this->hasMany(TempData::class, 'pick_up_time_slot_id');
}
```

---

## 4. VehicleType (vehicle_types)

**Tabela:** `vehicle_types` (id = unsignedInteger)

**Referencira ga:**
- `reservations.vehicle_type_id`
- `temp_data.vehicle_type_id`
- `vehicle_type_translations.vehicle_type_id`
- `vehicles.vehicle_type_id`

**Relacije u modelu VehicleType:**
```php
public function translations(): HasMany
{
    return $this->hasMany(VehicleTypeTranslation::class, 'vehicle_type_id');
}

public function reservations(): HasMany
{
    return $this->hasMany(Reservation::class, 'vehicle_type_id');
}

public function tempData(): HasMany
{
    return $this->hasMany(TempData::class, 'vehicle_type_id');
}

public function vehicles(): HasMany
{
    return $this->hasMany(Vehicle::class, 'vehicle_type_id');
}
```

---

## 5. VehicleTypeTranslation (vehicle_type_translations)

**Tabela:** `vehicle_type_translations`  
**FK:** `vehicle_type_id` → `vehicle_types`

**Relacije:**
```php
public function vehicleType(): BelongsTo
{
    return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
}
```

---

## 6. DailyParkingData (daily_parking_data)

**Tabela:** `daily_parking_data` (id = unsignedInteger)  
**FK:** `time_slot_id` → `list_of_time_slots`

**Relacije:**
```php
public function timeSlot(): BelongsTo
{
    return $this->belongsTo(ListOfTimeSlot::class, 'time_slot_id');
}
```

**Fillable (sugestija):** `date`, `time_slot_id`, `capacity`, `reserved`, `pending`  
**Casts:** `date` => `date`, `capacity/reserved/pending` => `integer`

---

## 7. Reservation (reservations)

**Tabela:** `reservations` (id = unsignedInteger)

**FK:**
- `user_id` → `users` (nullable)
- `vehicle_id` → `vehicles` (nullable)
- `drop_off_time_slot_id` → `list_of_time_slots` (nullable za `reservation_kind = daily_ticket`)
- `pick_up_time_slot_id` → `list_of_time_slots` (nullable za `daily_ticket`)
- `vehicle_type_id` → `vehicle_types`
- `free_reservation_request_id` → `free_reservation_requests` (nullable; povezani FZBR fulfill)

**Invariant `reservation_kind`:** `time_slots` (default) → oba slot ID NOT NULL; `daily_ticket` → oba slot ID NULL. Helperi: `Reservation::isTimeSlots()`, `isDailyTicket()`.

**Referencira ga:** `post_fiscalization_data.reservation_id`

**Relacije:**
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function vehicle(): BelongsTo
{
    return $this->belongsTo(Vehicle::class);
}

public function dropOffTimeSlot(): BelongsTo
{
    return $this->belongsTo(ListOfTimeSlot::class, 'drop_off_time_slot_id');
}

public function pickUpTimeSlot(): BelongsTo
{
    return $this->belongsTo(ListOfTimeSlot::class, 'pick_up_time_slot_id');
}

public function vehicleType(): BelongsTo
{
    return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
}

public function postFiscalizationData(): HasOne
{
    return $this->hasOne(PostFiscalizationData::class, 'reservation_id');
}

public function freeReservationRequest(): BelongsTo
{
    return $this->belongsTo(FreeReservationRequest::class, 'free_reservation_request_id');
}
```

**Fillable:** sva polja koja se mass-assign-uju (uključujući `reservation_kind`, `payment_method`, `created_by_admin`, `invoice_amount`, …).  
**Casts:** `reservation_date` => `date`, `fiscal_date` => `datetime`, `email_sent` => `integer` — semantika: **`Reservation::EMAIL_NOT_SENT`**, **`EMAIL_SENT`**, **`EMAIL_SENDING`** (v. model).

---

## 8. PostFiscalizationData (post_fiscalization_data)

**Tabela:** `post_fiscalization_data`  
**FK:** `reservation_id` → `reservations`

**Relacije:**
```php
public function reservation(): BelongsTo
{
    return $this->belongsTo(Reservation::class, 'reservation_id');
}
```

**Fillable:** `reservation_id`, `merchant_transaction_id`

---

## 9. TempData (temp_data)

**Tabela:** `temp_data` (id = unsignedInteger)

**FK:**
- `user_id` → `users` (nullable)
- `drop_off_time_slot_id` → `list_of_time_slots` (nullable za `daily_ticket`)
- `pick_up_time_slot_id` → `list_of_time_slots` (nullable za `daily_ticket`)
- `vehicle_type_id` → `vehicle_types`

**Status ENUM (produkcija):** `pending`, `processed`, `canceled`, `expired`, `late_success`, `late_manual_review`, `late_rejected` — **nema** `failed`. Konstante: `TempData::STATUS_*`, terminalna: `TempData::TERMINAL_STATES`.

**Relacije:**
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function dropOffTimeSlot(): BelongsTo
{
    return $this->belongsTo(ListOfTimeSlot::class, 'drop_off_time_slot_id');
}

public function pickUpTimeSlot(): BelongsTo
{
    return $this->belongsTo(ListOfTimeSlot::class, 'pick_up_time_slot_id');
}

public function vehicleType(): BelongsTo
{
    return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
}
```

**Fillable:** sva polja koja se setuju pri kreiranju (uključujući `reservation_kind`, `invoice_amount_snapshot`, callback/audit polja).  
**Casts:** `reservation_date` => `date`, `status` => enum/string.

---

## 11. ExternalFileArchive (external_file_archives)

**Tabela:** `external_file_archives` — MEGA arhiva privatnih fajlova (Limo, FZBR prilozi, …). V. `docs/external-file-archive.md`.

**Nema klasičnih FK** — polimorfna veza preko `source_table`, `source_id`, `source_column`.

**Status:** `pending` | `uploaded` | `failed` (status **arhivnog reda**, ne `temp_data`).

**Model:** `App\Models\ExternalFileArchive` — bez Eloquent relacija ka izvoru (lookup u servisu).

---

## 12. AgencyAdvanceTopup / AgencyAdvanceTransaction

**Tabele:** `agency_advance_topups`, `agency_advance_transactions` (avansne uplate agencija).

**FK:**
- `agency_advance_topups.agency_user_id` → `users`
- `agency_advance_transactions.agency_user_id` → `users` (ledger)

**Relacije (Topup):**
```php
public function agencyUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'agency_user_id');
}
```

**Status topup-a:** `pending`, `paid`, `failed`, `expired` — ovo je **zaseban** lifecycle od `temp_data` (admin Uvid: `/admin/uvid/avans`).

---

## 13. FreeReservationRequest (FZBR / besplatne rezervacije)

**Tabela:** `free_reservation_requests` + djeca: `free_reservation_request_vehicles`, `free_reservation_request_segments`, `free_reservation_request_attachments`.

**FK:** `user_id` → `users`; slotovi → `list_of_time_slots`; fulfill postavlja `reservations.free_reservation_request_id`.

**Relacije:**
```php
public function vehicles(): HasMany
public function segments(): HasMany
public function attachments(): HasMany
public function reservations(): HasMany // reverse: Reservation::freeReservationRequest()
```

**Status:** `submitted`, `updated`, `fulfilled`, `rejected`. V. `docs/agency-panel.md`, `docs/admin-panel.md` § Besplatne rezervacije.

---

## 10. Vehicle (vehicles)

**Tabela:** `vehicles` (id = unsignedInteger)

**FK:**
- `user_id` → `users`
- `vehicle_type_id` → `vehicle_types`

**Referencira ga:** `reservations.vehicle_id`

**Relacije:**
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function vehicleType(): BelongsTo
{
    return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
}

public function reservations(): HasMany
{
    return $this->hasMany(Reservation::class, 'vehicle_id');
}
```

**Fillable:** `user_id`, `license_plate`, `vehicle_type_id`  
**Casts:** po potrebi.

---

## Tabele bez modela (po želji)

- **report_emails**, **system_config**, **ui_translations** — nema FK; ako ih koristiš u relacijama ili po imenu, možeš napraviti jednostavan model bez relacija. **`report_emails.purpose`:** ENUM `report` | `limo_incidents`.
- **cache**, **jobs**, **sessions**, **password_reset_tokens**, **migrations** — sistemske; obično bez Eloquent modela.

---

## Napomena o tipovima ključeva

- `users.id` = **bigInteger** → svi `user_id` su `unsignedBigInteger`.
- `list_of_time_slots.id`, `vehicle_types.id`, `reservations.id`, `vehicles.id`, `daily_parking_data.id`, `temp_data.id` = **unsignedInteger** → svi FK ka njima su `unsignedInteger` (osim `vehicles.user_id` = bigInteger).
- U modelima koristi `$keyType` i `$incrementing` samo ako ne koristiš standardne `id`; inače Laravel podrazumeva ispravno.
