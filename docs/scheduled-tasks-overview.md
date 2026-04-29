# Scheduled Tasks Overview

Pregled svih trenutno planiranih (scheduled) taskova u projektu.

## Gde je scheduler definisan

- **Local SAFE schedule**: `routes/console.php` (komande koje ne kontaktiraju stvarnu banku/fiskalni servis)
- **Production-only schedule**: `bootstrap/app.php` (`withSchedule(...)`) — bank/fiscal komande su namerno pod `app()->environment('production')`

## Kako pokrenuti scheduler

- Jednokratno izvršenje due taskova:
  - `php artisan schedule:run`
- Kontinuirano lokalno:
  - `php artisan schedule:work`
- Pregled rasporeda:
  - `php artisan schedule:list`

## Aktivni scheduled taskovi

### Local SAFE (Laragon/Cronical)

| Command | Schedule | Command file | Kratko |
|---|---|---|---|
| `reservations:expire-pending` | every 10 minutes | `app/Console/Commands/ExpirePendingReservations.php` | Pending -> expired, oslobađa soft lock |
| `parking:sync-days` | daily at 00:05 | `app/Console/Commands/SyncDailyParkingDays.php` | Sinhronizuje redove `daily_parking_data` za današnji dan + narednih 90 dana; briše stare datume |
| `temp-data:cleanup` | daily | `app/Console/Commands/CleanupOldTempData.php` | Briše samo stare **ne-pending** redove po retention pravilu (default 180 dana) |
| `advance:send-yearly-statements` | yearly on Jan 1 at 10:00 | `routes/console.php` | “Kartica avansa” (prethodna godina); idempotentno; feature-guard |

### Production-only (bank/fiscal)

Ovi taskovi su **namerno zakazani samo u produkciji** (da se lokalno izbegnu realne finansijske/fiskalne radnje):

| Command | Schedule | Command file | Kratko |
|---|---|---|---|
| `reservations:process-pending` | every 5 minutes | `app/Console/Commands/ProcessPendingReservations.php` | Obrada `temp_data` / pipeline (može uključiti fiskal tokove) |
| `payment:check-pending-inquiry` | every 5 minutes | `app/Console/Commands/CheckPendingPaymentStatus.php` | Bank inquiry (Bankart) → `PaymentCallbackJob` |
| `post-fiscalization:retry` | every 10 minutes | `app/Console/Commands/RetryPostFiscalization.php` | Retry stvarne fiskalizacije |

## Ručno pokretanje komandi

Možeš ih pokrenuti pojedinačno. Na **Windowsu** u Cursor terminalu često **`php` nije u PATH-u** — koristi iz korena repoa **`.\laragon-artisan.cmd <komanda>`** (v. **`docs/project-conventions.md`** §3).

- `php artisan reservations:process-pending`
- `php artisan payment:check-pending-inquiry`
- `php artisan post-fiscalization:retry`
- `php artisan reservations:expire-pending`
- `php artisan reservations:assign-late-success`
- `php artisan parking:sync-days`
- `php artisan parking:update-availability`
- `php artisan reservations:send-emails`
- `php artisan temp-data:cleanup`
 - `php artisan advance:send-yearly-statements`

## Napomena za late_success

- Trenutno je `reservations:assign-late-success` u V1 režimu definisan kao stub:
  - ne kreira automatski rezervaciju,
  - služi da redovi ostanu dostupni za admin manual review flow.

## Dodatno (nije scheduled task)

- `routes/console.php` sadrži i **local safe** schedule + “advance yearly statement” komandu.
