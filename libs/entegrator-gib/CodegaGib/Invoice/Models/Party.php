<?php

declare(strict_types=1);

namespace CodegaGib\Invoice\Models;

use CodegaGib\Exception\InvalidInvoiceDataException;

/**
 * UBL-TR cac:Party modeli. Hem satıcı (AccountingSupplierParty) hem alıcı
 * (AccountingCustomerParty) için kullanılır.
 *
 * Türkiye'de mükellef türleri:
 *  - TÜZEL KİŞİ (A.Ş., Ltd, şahıs firması)  → taxId = VKN (10 hane), scheme = 'VKN'
 *  - GERÇEK KİŞİ son kullanıcı (e-Arşiv)    → taxId = TCKN (11 hane), scheme = 'TCKN'
 *
 * VKN ve TCKN schemeID değerleri GİB UBL-TR'de 'VKN' ve 'TCKN' olarak geçer
 * (bazı eski dökümanlarda 'VKN_TCKN' vardı; güncel 1.2 sürümünde ayrıdır).
 */
final class Party
{
    public readonly string $taxIdScheme;

    /**
     * @param string       $name          Firma unvanı veya kişi ad-soyadı
     * @param string       $taxId         VKN (10) veya TCKN (11)
     * @param Address      $address       Adres
     * @param string|null  $taxOffice     Vergi dairesi adı (tüzel için zorunlu, gerçek için opsiyonel)
     * @param string|null  $email         İletişim e-postası
     * @param string|null  $phone         Telefon
     * @param string|null  $fax           Faks
     * @param string|null  $website       Web adresi
     * @param string|null  $firstName     Gerçek kişi için ad (opsiyonel split)
     * @param string|null  $familyName    Gerçek kişi için soyad
     */
    public function __construct(
        public readonly string $name,
        public readonly string $taxId,
        public readonly Address $address,
        public readonly ?string $taxOffice = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $fax = null,
        public readonly ?string $website = null,
        public readonly ?string $firstName = null,
        public readonly ?string $familyName = null,
    ) {
        $this->taxIdScheme = self::detectScheme($this->taxId);
        $this->validate();
    }

    private static function detectScheme(string $taxId): string
    {
        $len = strlen($taxId);
        if ($len === 10) return 'VKN';
        if ($len === 11) return 'TCKN';
        throw new InvalidInvoiceDataException(
            "Geçersiz VKN/TCKN uzunluğu: '$taxId' ({$len} karakter). VKN=10, TCKN=11 hane olmalı."
        );
    }

    private function validate(): void
    {
        if ($this->name === '') {
            throw new InvalidInvoiceDataException('Party.name boş olamaz');
        }
        if (!ctype_digit($this->taxId)) {
            throw new InvalidInvoiceDataException("VKN/TCKN sadece rakam olmalı: '{$this->taxId}'");
        }
        if ($this->taxIdScheme === 'VKN' && $this->taxOffice === null) {
            throw new InvalidInvoiceDataException(
                "Tüzel kişi (VKN) için vergi dairesi (taxOffice) zorunludur: '{$this->name}'"
            );
        }
        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidInvoiceDataException("Geçersiz e-posta: '{$this->email}'");
        }
    }

    public function isLegalPerson(): bool
    {
        return $this->taxIdScheme === 'VKN';
    }

    public function isNaturalPerson(): bool
    {
        return $this->taxIdScheme === 'TCKN';
    }
}
