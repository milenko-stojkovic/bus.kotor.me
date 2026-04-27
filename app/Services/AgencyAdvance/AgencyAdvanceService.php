<?php

namespace App\Services\AgencyAdvance;

use App\Models\AgencyAdvanceTransaction;
use Illuminate\Support\Facades\DB;

final class AgencyAdvanceService
{
    /**
     * Returns balance as a 2-decimal string, computed as SUM(amount) from ledger.
     */
    public function balance(int $agencyUserId): string
    {
        $sum = DB::table('agency_advance_transactions')
            ->where('agency_user_id', $agencyUserId)
            ->selectRaw('COALESCE(SUM(amount), 0) as s')
            ->value('s');

        return $this->normalizeDecimal2($sum);
    }

    /**
     * Valid topup amount rules:
     * - positive
     * - whole euros only (no cents)
     * - last digit is 0 or 5
     */
    public function isValidTopupAmount(mixed $amount): bool
    {
        $s = $this->normalizeInputNumber($amount);
        if ($s === null) {
            return false;
        }

        // Accept "15", "15.0", "15.00" only. Reject cents like "100.50".
        if (preg_match('/^(0|[1-9]\d*)(?:\.0{1,2})?$/', $s) !== 1) {
            return false;
        }

        $euros = (int) explode('.', $s, 2)[0];
        if ($euros <= 0) {
            return false;
        }

        $last = $euros % 10;
        return $last === 0 || $last === 5;
    }

    public function canSpend(int $agencyUserId, mixed $amount): bool
    {
        $need = $this->normalizeDecimal2($amount);
        if ($this->compareDecimal2($need, '0.00') <= 0) {
            return false;
        }

        $bal = $this->balance($agencyUserId);
        return $this->compareDecimal2($bal, $need) >= 0;
    }

    /**
     * @param  mixed  $v
     * @return ?string canonical numeric string (no spaces), without scientific notation
     */
    private function normalizeInputNumber(mixed $v): ?string
    {
        if (is_int($v)) {
            return (string) $v;
        }

        if (is_float($v)) {
            // Avoid scientific notation; floats here are used only in tests like 15.00 / 100.50.
            $s = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
            return $s === '' ? null : $s;
        }

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') {
                return null;
            }
            if (str_contains($s, 'e') || str_contains($s, 'E')) {
                return null;
            }
            // Normalize comma -> dot just in case.
            $s = str_replace(',', '.', $s);
            if (preg_match('/^-?\d+(?:\.\d+)?$/', $s) !== 1) {
                return null;
            }
            return $s;
        }

        return null;
    }

    private function normalizeDecimal2(mixed $v): string
    {
        $s = $this->normalizeInputNumber($v);
        if ($s === null) {
            return '0.00';
        }

        $neg = false;
        if (str_starts_with($s, '-')) {
            $neg = true;
            $s = substr($s, 1);
        }

        [$int, $frac] = array_pad(explode('.', $s, 2), 2, '');
        $int = ltrim($int, '0');
        if ($int === '') {
            $int = '0';
        }
        $frac = preg_replace('/\D/', '', $frac) ?? '';
        $frac = substr(str_pad($frac, 2, '0'), 0, 2);

        $out = $int.'.'.$frac;
        if ($neg && $out !== '0.00') {
            $out = '-'.$out;
        }

        return $out;
    }

    /**
     * Compare two 2-decimal strings (or inputs that can be normalized).
     * Returns -1, 0, 1 like strcmp for numbers.
     */
    private function compareDecimal2(mixed $a, mixed $b): int
    {
        [$aNeg, $aCents] = $this->toCents($this->normalizeDecimal2($a));
        [$bNeg, $bCents] = $this->toCents($this->normalizeDecimal2($b));

        if ($aNeg !== $bNeg) {
            return $aNeg ? -1 : 1;
        }

        if ($aCents === $bCents) {
            return 0;
        }

        $cmp = $aCents <=> $bCents;
        return $aNeg ? -$cmp : $cmp;
    }

    /**
     * @return array{0:bool,1:int} (negative, absolute cents)
     */
    private function toCents(string $s): array
    {
        $neg = str_starts_with($s, '-');
        if ($neg) {
            $s = substr($s, 1);
        }
        [$int, $frac] = array_pad(explode('.', $s, 2), 2, '00');
        $intN = (int) $int;
        $fracN = (int) substr(str_pad($frac, 2, '0'), 0, 2);
        $cents = $intN * 100 + $fracN;
        return [$neg, $cents];
    }
}

