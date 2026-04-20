# Agency panel (ulogovani korisnik, `/panel`)

**Poslednje ažuriranje:** 2026-03-31

Prefiks ruta: **`/panel`**, middleware **`auth`** + **`verified`**. Gornja navigacija: `resources/views/layouts/navigation.blade.php`.

---

## Rute (sažetak)

| Ruta | Naziv rute (izbor) | Opis |
|------|-------------------|------|
| `GET /panel/reservations` | `panel.reservations` | Nova rezervacija (`ReservationBookingPageData`) |
| `GET /panel/upcoming` | `panel.upcoming` | Predstojeće rezervacije, promena vozila |
| `GET /panel/realized` | `panel.realized` | Realizovane, link na PDF u novom tabu |
| `GET /panel/vehicles` | `panel.vehicles` | Vozila |
| `GET /panel/statistics` | `panel.statistics` | Statistika: ukupno plaćeno, broj realizovanih posjeta, tabela po tablicama |
| `GET /panel/user` | `panel.user` | Korisnik: ime, jezik, email, lozinka |
| `PATCH /profile` | `profile.update` | Čuva profil (uključujući lozinku ako je uneta) |
| `PATCH /panel/reservations/{id}/vehicle` | `panel.reservations.vehicle` | Zamena vozila na upcoming rezervaciji |
| `GET /panel/reservations/{id}/invoice/view` | `panel.reservations.invoice.view` | PDF **inline** (browser tab) |
| `GET /panel/reservations/{id}/invoice` | `panel.reservations.invoice` | PDF **download** |

`GET /profile` redirectuje na `/panel/user` (`profile.edit`).

---

## Predstojeće vs realizovane

Servis: **`App\Services\Reservation\PanelReservationListService`**.

- **Upcoming:** datum rezervacije je **posle** danasnjeg dana **ili** (datum je **danas** i **`now` je pre kraja** pick-up termina: `ListOfTimeSlot::getEndTimeForDate` za taj datum). Ako kraj termina ne može da se parsira, tretira se kao upcoming.
- **Realized:** sve ostalo (prošlost ili danas posle kraja odlaska).

---

## Promena vozila (samo upcoming)

- Dozvoljeno samo vozilo istog korisnika čiji je **`vehicle_types.price` ≤** cene kategorije na rezervaciji (snapshot **`vehicle_type_id`** se **ne** menja).
- **Plaćena** rezervacija: reset **`invoice_sent_at`** / **`email_sent`**, zatim **`SendInvoiceEmailJob`** (PDF se generiše u jobu, bez trajnog skladišta).
- **Besplatna** (`reservations.status = free`): samo **`license_plate`** / **`vehicle_id`**, bez fiskal/PDF mejla.

**UI (label tipa vozila):** u panelu i na korisničkim formama tip vozila se prikazuje kao **`Naziv (Opis) - Cena`** (opis je lokalizovan iz `vehicle_type_translations.description`, ako postoji). Formatiranje je centralizovano u `VehicleType::formatLabel($locale, 'EUR')`. Ako opis nedostaje, prikaz je `Naziv - Cena`.

Validacija: **`App\Http\Requests\UpdateReservationVehicleRequest`**.

---

## User tab (`/panel/user`)

- Jedna forma: **`resources/views/panel/partials/user-settings-form.blade.php`** (PATCH `/profile`).
- Polja: **name**, **lang** (`cg` / `en`), **email**, **password** (trenutna / nova / potvrda) sa istim eye partialima kao auth; **`country`** ostaje kao **hidden** sa trenutnom vrednošću.
- **Save** / **Cancel** aktivni samo kad ima izmena (Alpine); Cancel vraća inicijalne vrednosti.
- **Email:** nova adresa upisuje se u **`users.email`**, **`email_verified_at`** se postavlja na **`null`**, šalje se verification mail (minimalni „Opcija A“ tok bez odvojene tabele).
- Tekstovi: grupa **`user`** u **`UiTranslationsSeeder`** + **`UiText::t`**.

---

## Brisanje naloga

**`resources/views/profile/partials/delete-user-form.blade.php`** — stringovi iz **`ui_translations`** (grupa **`user`**, ključevi **`delete_account_*`**, **`cancel`**).

---

## Statistika (`/panel/statistics`)

Servis **`App\Services\Reservation\PanelStatisticsService`**: koristi **`PanelReservationListService::realizedFor`** za istu definiciju „realized“. **Total paid** = suma **`vehicle_types.price`** snapshota samo za **`reservations.status = paid`**. **Broj posjeta** = broj svih realizovanih rezervacija. **Tabela vozila** = grupisano po **`license_plate` + `vehicle_type_id`**, broj realizovanih po grupi.

## Veza: besplatne rezervacije

Checkout za besplatan termin **ne** ide na banku i **ne** pokreće fiskalizaciju; vidi kratku napomenu u **[success-payment-pipeline.md](./success-payment-pipeline.md)** i kod: **`CheckoutController`**, **`PaymentSuccessHandler`**, **`SendFreeReservationConfirmationJob`**, **`FreeReservationRules`**.
