<?php

declare(strict_types=1);

namespace CodegaGib\Invoice\Models;

use CodegaGib\Exception\InvalidInvoiceDataException;
use CodegaGib\Util\AmountFormatter;

/**
 * UBL-TR cac:InvoiceLine modeli. Her fatura satırı için veri.
 *
 * Matrah hesabı: (quantity × unitPrice) − lineDiscount
 * KDV hesabı:    matrah × (vatRate / 100)
 * Satır toplamı: matrah + KDV + (varsa diğer vergiler)
 *
 * GİB unitCode standardı (UN/ECE Recommendation 20):
 *  - C62  Adet/dane
 *  - KGM  Kilogram
 *  - LTR  Litre
 *  - MTR  Metre
 *  - MTK  Metrekare
 *  - NIU  Unit (sayılı kalem)
 *  - HUR  Saat
 *  - DAY  Gün
 *  - SET  Set
 *  - BX   Kutu
 *  - PK   Paket
 */
final class InvoiceLine
{
    public const UNIT_ADET   = 'C62';
    public const UNIT_KG     = 'KGM';
    public const UNIT_LITRE  = 'LTR';
    public const UNIT_METRE  = 'MTR';
    public const UNIT_M2     = 'MTK';
    public const UNIT_SAAT   = 'HUR';
    public const UNIT_GUN    = 'DAY';
    public const UNIT_SET    = 'SET';
    public const UNIT_KUTU   = 'BX';
    public const UNIT_PAKET  = 'PK';

    /**
     * @param int         $id             Satır no (1'den başlar)
     * @param string      $itemName       Ürün/hizmet adı
     * @param float       $quantity       Miktar
     * @param string      $unitCode       Birim kodu (C62=adet, KGM=kg, ...)
     * @param float       $unitPrice      Birim fiyat (KDV hariç)
     * @param float       $vatRate        KDV oranı (% olarak, 20 = %20)
     * @param float       $lineDiscount   Satır bazlı iskonto tutarı
     * @param string|null $itemDescription  Açıklama (opsiyonel)
     * @param string|null $sellerItemCode   Satıcı ürün kodu
     * @param string|null $buyerItemCode    Alıcı ürün kodu
     * @param string      $currencyCode   Para birimi (TRY, USD, EUR...)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $itemName,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $vatRate = 20.0,
        public readonly string $unitCode = self::UNIT_ADET,
        public readonly float $lineDiscount = 0.0,
        public readonly ?string $itemDescription = null,
        public readonly ?string $sellerItemCode = null,
        public readonly ?string $buyerItemCode = null,
        public readonly string $currencyCode = 'TRY',
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->id < 1) {
            throw new InvalidInvoiceDataException("Satır ID en az 1 olmalı: {$this->id}");
        }
        if ($this->itemName === '') {
            throw new InvalidInvoiceDataException("Satır {$this->id}: ürün adı boş");
        }
        if ($this->quantity <= 0) {
            throw new InvalidInvoiceDataException("Satır {$this->id}: miktar 0'dan büyük olmalı ({$this->quantity})");
        }
        if ($this->unitPrice < 0) {
            throw new InvalidInvoiceDataException("Satır {$this->id}: birim fiyat negatif olamaz ({$this->unitPrice})");
        }
        if ($this->vatRate < 0 || $this->vatRate > 100) {
            throw new InvalidInvoiceDataException("Satır {$this->id}: KDV oranı 0-100 arasında olmalı ({$this->vatRate})");
        }
        if ($this->lineDiscount < 0) {
            throw new InvalidInvoiceDataException("Satır {$this->id}: iskonto negatif olamaz");
        }
    }

    /**
     * Brüt satır tutarı (miktar × birim fiyat)
     */
    public function grossAmount(): float
    {
        return round($this->quantity * $this->unitPrice, 2);
    }

    /**
     * Matrah (KDV hariç net tutar = brüt - iskonto)
     */
    public function taxableAmount(): float
    {
        return round($this->grossAmount() - $this->lineDiscount, 2);
    }

    /**
     * KDV tutarı
     */
    public function vatAmount(): float
    {
        return round($this->taxableAmount() * ($this->vatRate / 100), 2);
    }

    /**
     * Satır toplam (matrah + KDV)
     */
    public function totalAmount(): float
    {
        return round($this->taxableAmount() + $this->vatAmount(), 2);
    }

    public function formattedQuantity(): string
    {
        return AmountFormatter::quantity($this->quantity);
    }

    public function formattedUnitPrice(): string
    {
        return AmountFormatter::price($this->unitPrice);
    }
}
