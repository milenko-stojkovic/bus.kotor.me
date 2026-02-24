# Autentifikovani vs. guest korisnici

## User model (autentifikovani)

- Autentifikovani korisnici imaju red u tabeli `users`.
- Važe svi standardni Laravel auth mehanizmi: registracija, verifikacija e-mail-a, reset lozinke, remember token itd.
- User je povezan sa **Reservation** i **Vehicle** preko `user_id` (nullable u rezervacijama).

## Guest korisnici

- Mogu napraviti rezervaciju **bez naloga** – `user_id` je **NULL** u `reservations` i `temp_data`.
- Za guest rezervacije svi podaci koji se inače vežu za korisnika ostaju **snapshot** u rezervaciji:
  - `user_name`, `country`, `email`, `license_plate`, `vehicle_type_id`
- Kada guest plati, podaci se čuvaju u tabeli, ali **nema veze sa `users`**.

## Pravila u kodu

1. **Gde se koristi `user_id`** – uvek proveriti da li je korisnik autentifikovan.
2. **Guest rezervacije** – tretirati kao standalone; nema FK ka `users`.
3. **Metode koje rade sa rezervacijama** moraju podržavati oba slučaja:
   - `$reservation->user?->name` → `null` ako je guest
   - ili `$reservation->isGuest()` pa prikaz iz `$reservation->user_name` (snapshot).

## Istorija plaćanja

- **Autentifikovani** – vidi svoje rezervacije prema `user_id` (npr. `$user->reservations`).
- **Guest** – nema istoriju (osim ako se kasnije ne uvede način da napravi nalog i poveže stari `user_id`).

## Eloquent relacije

- **User** `hasMany` **Reservation** (samo rezervacije gde je `user_id` = taj user).
- **Reservation** `belongsTo` **User** (nullable) – kada je guest, `user_id` je null.
- Isto važi za **TempData** i **User**.

U metodama koje manipulišu rezervacijama uvek koristiti **nullable check** na `user_id` / `$reservation->user` (npr. optional chaining `$reservation->user?->name` ili `$reservation->isGuest()`).
