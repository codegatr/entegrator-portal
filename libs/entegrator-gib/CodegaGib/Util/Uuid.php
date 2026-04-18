<?php

declare(strict_types=1);

namespace CodegaGib\Util;

/**
 * RFC 4122 v4 UUID üreteci.
 *
 * GİB e-Faturada ETTN (Evrensel Tekil Tanımlayıcı Numarası) olarak kullanılır.
 * Her fatura için benzersiz bir UUID üretilmelidir; aynı ETTN ile aynı mükellef
 * tarafından iki kez fatura kesilmesi GİB tarafından reddedilir.
 *
 * Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx (y = 8/9/a/b)
 *
 * Örnek: 550e8400-e29b-41d4-a716-446655440000
 */
final class Uuid
{
    /**
     * RFC 4122 v4 UUID üretir (random-based).
     */
    public static function v4(): string
    {
        $data = random_bytes(16);

        // v4 gereği 6. byte üst 4 biti 0100 (= 4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // 8. byte üst 2 biti 10 (= RFC 4122 variant)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        $hex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * UUID v4 formatına uyup uymadığını kontrol eder.
     */
    public static function isValid(string $uuid): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }
}
