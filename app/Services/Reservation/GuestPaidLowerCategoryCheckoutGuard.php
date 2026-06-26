<?php

namespace App\Services\Reservation;

use App\Models\VehicleType;
use App\Services\AdminFiscalizationAlertService;
use App\Services\AdminPanel\AdminAlertService;
use App\Support\UiText;
use Illuminate\Support\Facades\Log;

/**
 * Blocks guest checkout when the same plate was previously paid in a higher vehicle category.
 */
final class GuestPaidLowerCategoryCheckoutGuard
{
    public function __construct(
        private readonly GuestPaidLowerCategoryHistoryService $history,
    ) {}

    /**
     * @return array{
     *     required_category: string,
     *     locale: string,
     *     plain_message: string,
     *     historical_reservation_id: int,
     *     historical_vehicle_type_id: int,
     *     submitted_vehicle_type_id: int,
     *     license_plate: string,
     * }|null
     */
    public function evaluateForGuestCheckout(
        string $licensePlate,
        int $submittedVehicleTypeId,
    ): ?array {
        $normalizedPlate = DuplicateReservationAttemptService::normalizeLicensePlate($licensePlate);
        if ($normalizedPlate === '') {
            return null;
        }

        $historical = $this->history->findMostRecentPaidGuestReservation($normalizedPlate);
        if ($historical === null) {
            return null;
        }

        if (! $this->history->historicalPriceExceedsSubmitted($submittedVehicleTypeId, $historical)) {
            return null;
        }

        $locale = app()->getLocale();
        $requiredCategory = $this->history->requiredCategoryLabel($historical, $locale);

        return [
            'required_category' => $requiredCategory,
            'locale' => $locale,
            'plain_message' => $this->buildPlainMessage($requiredCategory, $locale),
            'historical_reservation_id' => (int) $historical->id,
            'historical_vehicle_type_id' => (int) $historical->vehicle_type_id,
            'submitted_vehicle_type_id' => $submittedVehicleTypeId,
            'license_plate' => $normalizedPlate,
        ];
    }

    /**
     * @param  array{
     *     required_category: string,
     *     locale: string,
     *     plain_message: string,
     *     historical_reservation_id: int,
     *     historical_vehicle_type_id: int,
     *     submitted_vehicle_type_id: int,
     *     license_plate: string,
     * }  $block
     */
    public function notifyBlocked(
        array $block,
        string $guestName,
        string $guestEmail,
        string $reservationDate,
        string $reservationKind,
    ): void {
        $historical = \App\Models\Reservation::query()
            ->with('vehicleType')
            ->find($block['historical_reservation_id']);
        if ($historical === null) {
            return;
        }

        $submittedType = VehicleType::query()->find($block['submitted_vehicle_type_id']);
        $locale = $block['locale'];
        $submittedLabel = $submittedType?->formatLabel($locale, 'EUR') ?? '#'.$block['submitted_vehicle_type_id'];
        $requiredLabel = $block['required_category'];

        $fiscalAlerts = app(AdminFiscalizationAlertService::class);

        $subject = '[Kotor Bus] Guest checkout blocked: lower vehicle category than historical paid';

        $body = implode("\n", [
            'Guest checkout was blocked before payment because the submitted vehicle category is lower than the most recent paid guest reservation for the same license plate.',
            '',
            '--- blocked attempt ---',
            'guest_name: '.$guestName,
            'guest_email: '.$guestEmail,
            'attempted_date: '.$reservationDate,
            'reservation_kind: '.$reservationKind,
            'locale: '.$locale,
            'submitted_plate: '.$block['license_plate'],
            'submitted_category: '.$submittedLabel,
            'required_category: '.$requiredLabel,
            '',
            '--- historical paid guest reservation ---',
            $fiscalAlerts->buildReservationContext($historical),
            'vehicle_type.price: '.number_format((float) ($historical->vehicleType?->price ?? 0), 2, '.', ''),
        ]);

        $dedupeKey = sprintf(
            'guest_lower_category_block:%s:%d:%s',
            $block['license_plate'],
            $block['submitted_vehicle_type_id'],
            $reservationDate,
        );

        app(AdminAlertService::class)->createOnce(
            'guest_lower_category_checkout_blocked',
            'Guest checkout blokiran: niža kategorija od ranije plaćene',
            sprintf(
                'Tablica %s: pokušaj u kategoriji %s blokiran — potrebna %s (historical #%d).',
                $block['license_plate'],
                $submittedLabel,
                $requiredLabel,
                $block['historical_reservation_id'],
            ),
            'medium',
            $dedupeKey,
            [
                'historical_reservation_id' => $block['historical_reservation_id'],
                'license_plate' => $block['license_plate'],
                'submitted_vehicle_type_id' => $block['submitted_vehicle_type_id'],
                'historical_vehicle_type_id' => $block['historical_vehicle_type_id'],
                'reservation_date' => $reservationDate,
                'reservation_kind' => $reservationKind,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'locale' => $locale,
                'email_full_body' => $body,
            ],
        );

        $fiscalAlerts->notify($subject, $body, [
            'alert_type' => 'guest_lower_category_checkout_blocked',
            'historical_reservation_id' => $block['historical_reservation_id'],
            'license_plate' => $block['license_plate'],
        ]);

        Log::channel('payments')->info('guest_lower_category_checkout_blocked', [
            'historical_reservation_id' => $block['historical_reservation_id'],
            'license_plate' => $block['license_plate'],
            'submitted_vehicle_type_id' => $block['submitted_vehicle_type_id'],
            'reservation_date' => $reservationDate,
            'reservation_kind' => $reservationKind,
        ]);
    }

    public function buildPlainMessage(string $requiredCategory, string $locale): string
    {
        $intro = UiText::t(
            'booking',
            'guest_lower_category_block_intro',
            $locale === 'cg'
                ? 'Za ovu registarsku tablicu ranije je plaćena rezervacija u višoj kategoriji vozila.'
                : 'This license plate was previously used for a paid reservation in a higher vehicle category.',
            $locale,
        );
        $select = UiText::t(
            'booking',
            'guest_lower_category_block_select_category',
            $locale === 'cg'
                ? 'Da biste nastavili, vozilo prijavite u kategoriji:'
                : 'To continue, please select the category:',
            $locale,
        );
        $agency = UiText::t(
            'booking',
            'guest_lower_category_block_agency_note',
            $locale === 'cg'
                ? 'Ako rezervacije pravite kao agencija, registrujte se i koristite panel za agencije. U panelu za agencije možete upravljati vozilima i rješavati zahtjeve za promjenu kategorije.'
                : 'If you are making reservations as an agency, register and use the agency panel. In the agency panel, you can manage vehicles and resolve vehicle category change requests.',
            $locale,
        );
        $support = UiText::t(
            'booking',
            'guest_lower_category_block_support',
            $locale === 'cg'
                ? 'Za podršku pišite na bus@kotor.me.'
                : 'For support, contact bus@kotor.me.',
            $locale,
        );

        return implode("\n\n", [
            $intro,
            $select.' '.$requiredCategory,
            $agency,
            $support,
        ]);
    }
}
