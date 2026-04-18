<?php

declare(strict_types=1);

namespace CodegaGib\Invoice\Models;

/**
 * UBL-TR cac:PostalAddress modeli.
 *
 * Zorunlu alanlar: city (il) ve country. GİB'in Türkiye adres standardına göre:
 * - district     → cbc:CitySubdivisionName (ilçe)
 * - city         → cbc:CityName (il)
 * - countryName  → cac:Country/cbc:Name ("Türkiye")
 *
 * Opsiyonel: mahalle, sokak, bina no, daire no, posta kodu.
 */
final class Address
{
    public function __construct(
        public readonly string $city,
        public readonly string $country = 'Türkiye',
        public readonly ?string $district = null,
        public readonly ?string $street = null,
        public readonly ?string $buildingName = null,
        public readonly ?string $buildingNumber = null,
        public readonly ?string $room = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $region = null,
    ) {}

    public static function tr(
        string $city,
        ?string $district = null,
        ?string $street = null,
        ?string $buildingNumber = null,
        ?string $postalCode = null,
    ): self {
        return new self(
            city: $city,
            country: 'Türkiye',
            district: $district,
            street: $street,
            buildingNumber: $buildingNumber,
            postalCode: $postalCode,
        );
    }
}
