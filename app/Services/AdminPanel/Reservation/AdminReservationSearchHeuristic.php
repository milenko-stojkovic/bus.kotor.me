<?php

namespace App\Services\AdminPanel\Reservation;

/**
 * Jednostavna heuristika za pretragu imena/emaila (nije fuzzy engine).
 *
 * - jedno izostavljeno slovo (nad normalizovanim stringom)
 * - permutacija 2 susedna slova
 * - za ime: ignorišu se varijacije doo / d.o.o. u normalizovanom terminu
 */
final class AdminReservationSearchHeuristic
{
    /**
     * @return list<string> LIKE obrasci (sa % sa obe strane)
     */
    public function nameLikePatterns(string $raw): array
    {
        $normalized = $this->normalizeNameToken($raw);
        if ($normalized === '') {
            return [];
        }

        $variants = $this->variantsForString($normalized);
        $out = [];
        foreach ($variants as $v) {
            if ($v !== '') {
                $out[] = '%'.$v.'%';
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string> LIKE obrasci za email
     */
    public function emailLikePatterns(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $variants = $this->variantsForString(mb_strtolower($trimmed));

        return array_values(array_unique(array_map(fn (string $v) => '%'.$v.'%', array_filter($variants))));
    }

    /**
     * Uklanja tipične varijante doo iz imena za generisanje varijanti (ne menja pravila LIKE direktno nad kolonom).
     */
    public function normalizeNameToken(string $raw): string
    {
        $s = mb_strtolower(preg_replace('/\s+/u', '', trim($raw)));
        $s = preg_replace('/d\.?o\.?o\.?/u', '', $s) ?? '';

        return is_string($s) ? $s : '';
    }

    /**
     * @return list<string>
     */
    private function variantsForString(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }
        $variants = [$normalized];
        $len = mb_strlen($normalized);
        for ($i = 0; $i < $len; $i++) {
            $variants[] = mb_substr($normalized, 0, $i).mb_substr($normalized, $i + 1);
        }
        for ($i = 0; $i < $len - 1; $i++) {
            $a = mb_substr($normalized, $i, 1);
            $b = mb_substr($normalized, $i + 1, 1);
            $variants[] = mb_substr($normalized, 0, $i).$b.$a.mb_substr($normalized, $i + 2);
        }

        return array_values(array_unique(array_filter($variants, fn (string $v) => $v !== '')));
    }
}
