<?php

namespace App\Support;

class AssistantGuard
{
    private const EMAIL = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';

    private const NIK = '/(?<!\d)\d{16}(?!\d)/';

    private const PHONE = '/(?<![\d.,])(?:\+62|62|0)8\d(?:[\s-]?\d){6,11}(?!\d)/';

    private const URL = '/https?:\/\/|www\.|\b[a-z0-9-]+\.(?:com|id|net|org|co|io|me)\b/i';

    private const AMOUNT = '/(Rp\.?\s?)?(\d+(?:[.,]\d+)*)\s*(miliar|milyar|juta|jt)?/iu';

    /** Masks personal data before it is sent to the model provider. */
    public static function redact(string $text): string
    {
        return preg_replace([self::EMAIL, self::NIK, self::PHONE], '[disensor]', $text) ?? '';
    }

    /**
     * Accepts model text only when every price, area and room count it states comes from tool data or the user's own message.
     *
     * @param  iterable<array<string, mixed>>  $facts
     */
    public static function isGrounded(string $text, iterable $facts, string $userMessage): bool
    {
        if (trim($text) === '' || mb_strlen($text) > 800 || preg_match(self::URL, $text) || preg_match(self::PHONE, $text)) {
            return false;
        }

        $amounts = self::amounts($userMessage);
        preg_match_all('/\d+(?:[.,]\d+)?/', $userMessage, $userNumbers);
        $numbers = array_map(self::number(...), $userNumbers[0]);
        foreach ($facts as $fact) {
            $amounts[] = $fact['price_idr'];
            array_push($numbers, ...array_filter([$fact['land_area_m2'], $fact['building_area_m2'], $fact['rooms']], fn ($value) => $value !== null));
        }

        foreach (self::amounts($text) as $amount) {
            if (! in_array($amount, $amounts, true)) {
                return false;
            }
        }
        preg_match_all('/(\d+(?:[.,]\d+)?)\s?(?:m²|m2|meter persegi)|(\d+)\s?kamar/iu', $text, $stated);
        foreach (array_filter(array_merge($stated[1], $stated[2]), 'strlen') as $value) {
            if (! in_array(self::number($value), $numbers, false)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<int> Rupiah amounts written with "Rp" or a juta/miliar unit. */
    private static function amounts(string $text): array
    {
        preg_match_all(self::AMOUNT, $text, $matches, PREG_SET_ORDER);
        $amounts = [];
        foreach ($matches as $match) {
            $unit = strtolower($match[3] ?? '');
            if ($unit === '' && ($match[1] ?? '') === '') {
                continue;
            }
            $amounts[] = $unit === ''
                ? (int) str_replace(['.', ','], '', $match[2])
                : (int) round(self::number($match[2]) * (in_array($unit, ['juta', 'jt'], true) ? 1_000_000 : 1_000_000_000));
        }

        return $amounts;
    }

    private static function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
