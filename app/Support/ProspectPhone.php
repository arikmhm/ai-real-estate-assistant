<?php

namespace App\Support;

class ProspectPhone
{
    public static function normalize(string $input): ?string
    {
        $compact = preg_replace('/[\s().-]+/', '', trim($input));
        if ($compact === null) {
            return null;
        }

        if (preg_match('/^08\d{8,11}$/', $compact)) {
            return '+62'.substr($compact, 1);
        }

        return preg_match('/^\+628\d{8,11}$/', $compact) ? $compact : null;
    }
}
