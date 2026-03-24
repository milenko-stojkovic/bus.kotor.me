# Scheduled Tasks Overview

Pregled svih trenutno planiranih (scheduled) taskova u projektu.

## Gde je scheduler definisan

- `bootstrap/app.php` (`withSchedule(...)`)

## Kako pokrenuti scheduler

- Jednokratno izvršenje due taskova:
  - `php artisan schedule:run`
- Kontinuirano lokalno:
  - `php artisan schedule:work`
- Pregled rasporeda:
  - `php artisan schedule:list`

## Aktivni scheduled taskovi

| Command | Schedule | Command file | Kratko |
|---|---|---|---|
| `reservations:process-pending` | every 5 minutes | `app/Console/Commands/ProcessPendingReservations.php` | Obrada `temp_data` pending (trenutno TODO/stub delovi postoje) |
| `payment:check-pending-inquiry` | every 5 minutes | `app/Console/Commands/CheckPendingPaymentStatus.php` | Status inquiry za pending plaćanja starija od praga |
| `post-fiscalization:retry` | every 10 minutes | `app/Console/Commands/RetryPostFiscalization.php` | Retry fiskalizacije za `post_fiscalization_data` |
| `reservations:expire-pending` | every 10 minutes | `app/Console/Commands/ExpirePendingReservations.php` | Pending -> expired, oslobađa soft lock |
| `reservations:assign-late-success` | every 15 minutes | `app/Console/Commands/AssignLateSuccessReservations.php` | Late success obrada (**trenutno V1 stub**) |
| `parking:update-availability` | every 10 minutes | `app/Console/Commands/UpdateDailyParkingAvailability.php` | Ažurira `daily_parking_data` (**trenutno TODO/stub**) |
| `reservations:send-emails` | every 10 minutes | `app/Console/Commands/SendReservationEmails.php` | Slanje potvrda rezervacije (osnovni flow, delom TODO) |
| `temp-data:cleanup` | daily | `app/Console/Commands/CleanupOldTempData.php` | Audit mode: ne briše fizički `temp_data` |

## Ručno pokretanje komandi

Možeš ih pokrenuti pojedinačno:

- `php artisan reservations:process-pending`
- `php artisan payment:check-pending-inquiry`
- `php artisan post-fiscalization:retry`
- `php artisan reservations:expire-pending`
- `php artisan reservations:assign-late-success`
- `php artisan parking:update-availability`
- `php artisan reservations:send-emails`
- `php artisan temp-data:cleanup`

## Napomena za late_success

- Trenutno je `reservations:assign-late-success` u V1 režimu definisan kao stub:
  - ne kreira automatski rezervaciju,
  - služi da redovi ostanu dostupni za admin manual review flow.

## Dodatno (nije scheduled task)

- `routes/console.php` sadrži samo pomoćnu komandu `inspire` i nije deo business scheduler tokova.
