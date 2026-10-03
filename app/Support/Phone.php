<?php

namespace App\Support;

/**
 * Российские мобильные и городские номера в едином виде +7XXXXXXXXXX,
 * чтобы «8 (999) 123-45-67» и «+7 999 1234567» считались одним клиентом.
 */
final class Phone
{
    public static function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            return null;
        }

        return '+7'.$digits;
    }

    /** +79991234567 → +7 (999) 123-45-67 */
    public static function format(string $phone): string
    {
        if (! preg_match('/^\+7(\d{3})(\d{3})(\d{2})(\d{2})$/', $phone, $m)) {
            return $phone;
        }

        return "+7 ({$m[1]}) {$m[2]}-{$m[3]}-{$m[4]}";
    }
}
