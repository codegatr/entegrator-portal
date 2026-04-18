<?php

declare(strict_types=1);

namespace CodegaGib\Util;

/**
 * GİB UBL-TR 2.1'in beklediği tutar ve oran formatları.
 *
 * Kurallar:
 * - Tutarlar MAX 2 ondalık (KDV, toplam, matrah vs.)
 * - Birim fiyat MAX 4 ondalık (detaylı fiyatlandırma için)
 * - Miktar MAX 8 ondalık (kg/litre gibi küçük birimler)
 * - Oranlar 2 ondalık (%20.00)
 * - Her zaman NOKTA ondalık ayraç, virgül KULLANILMAZ
 * - Binlik ayracı kullanılmaz
 * - Negatif değerler için minus işareti, parantez değil
 */
final class AmountFormatter
{
    public const AMOUNT_PRECISION   = 2;
    public const PRICE_PRECISION    = 4;
    public const QUANTITY_PRECISION = 8;
    public const PERCENT_PRECISION  = 2;

    /**
     * Tutarı (KDV, matrah, toplam gibi) 2 ondalık formata çevirir.
     * 100      → "100.00"
     * 99.5     → "99.50"
     * 12.3456  → "12.35"
     */
    public static function amount(float|int|string $value): string
    {
        return self::fmt($value, self::AMOUNT_PRECISION);
    }

    /**
     * Birim fiyat (4 ondalık).
     * 0.1     → "0.1000"
     * 12.3456 → "12.3456"
     */
    public static function price(float|int|string $value): string
    {
        return self::fmt($value, self::PRICE_PRECISION);
    }

    /**
     * Miktar (8 ondalık, ama gereksiz sıfırlar kırpılabilir).
     */
    public static function quantity(float|int|string $value): string
    {
        return self::fmt($value, self::QUANTITY_PRECISION);
    }

    /**
     * Oran (% — 2 ondalık).
     * 20 → "20.00", 0.5 → "0.50"
     */
    public static function percent(float|int|string $value): string
    {
        return self::fmt($value, self::PERCENT_PRECISION);
    }

    private static function fmt(float|int|string $value, int $precision): string
    {
        if (is_string($value)) {
            // Türkçe virgül geldiyse düzelt
            $value = str_replace([','], ['.'], $value);
            $value = (float)$value;
        }
        // BCMATH olmadan HALF_UP: round() PHP_ROUND_HALF_UP varsayılan
        $rounded = round((float)$value, $precision, PHP_ROUND_HALF_UP);
        return number_format($rounded, $precision, '.', '');
    }

    /**
     * Toplam tutarların (matrah + KDV = toplam) mantıksal tutarlılığını kontrol eder.
     * Kuruş farkları yuvarlama nedeniyle olabilir → tolerans 0.05 TL.
     */
    public static function assertTotalMatch(float $line, float $tax, float $total, float $tolerance = 0.05): bool
    {
        return abs(($line + $tax) - $total) <= $tolerance;
    }
}
