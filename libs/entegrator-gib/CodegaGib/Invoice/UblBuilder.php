<?php

declare(strict_types=1);

namespace CodegaGib\Invoice;

use CodegaGib\Exception\InvalidInvoiceDataException;
use CodegaGib\Invoice\Models\InvoiceLine;
use CodegaGib\Invoice\Models\Party;
use CodegaGib\Util\AmountFormatter;
use CodegaGib\Util\Uuid;

/**
 * UBL-TR 2.1 standardına göre e-Fatura / e-Arşiv XML üretici.
 *
 * Desteklenen profiller:
 *  - TEMELFATURA   → kabul/red süreci yok, direkt geçer (B2B alıcı red edemez)
 *  - TICARIFATURA  → kabul/red süreci var (B2B)
 *  - EARSIVFATURA  → son kullanıcı / e-Fatura mükellefi olmayan alıcı
 *  - IHRACAT       → gümrük beyannameli dış satış
 *
 * Desteklenen fatura türleri (cbc:InvoiceTypeCode):
 *  - SATIS           → normal satış
 *  - IADE            → iade faturası
 *  - TEVKIFAT        → KDV tevkifatlı
 *  - ISTISNA         → KDV istisnası
 *  - OZELMATRAH      → özel matrah
 *  - IHRACKAYITLI    → ihraç kayıtlı satış
 *
 * Standart referans: GİB e-Fatura UBL-TR 1.2 Paket Kılavuzu
 * https://ebelge.gib.gov.tr/dosyalar/kilavuzlar
 */
class UblBuilder
{
    // Profiller
    public const PROFILE_TEMEL    = 'TEMELFATURA';
    public const PROFILE_TICARI   = 'TICARIFATURA';
    public const PROFILE_EARSIV   = 'EARSIVFATURA';
    public const PROFILE_IHRACAT  = 'IHRACAT';

    // Fatura türleri
    public const TYPE_SATIS          = 'SATIS';
    public const TYPE_IADE           = 'IADE';
    public const TYPE_TEVKIFAT       = 'TEVKIFAT';
    public const TYPE_ISTISNA        = 'ISTISNA';
    public const TYPE_OZELMATRAH     = 'OZELMATRAH';
    public const TYPE_IHRACKAYITLI   = 'IHRACKAYITLI';

    // UBL namespaces
    private const NS_MAIN = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC  = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC  = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const NS_EXT  = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const NS_DS   = 'http://www.w3.org/2000/09/xmldsig#';

    private string $profileId      = self::PROFILE_TEMEL;
    private string $invoiceType    = self::TYPE_SATIS;
    private string $currencyCode   = 'TRY';
    private ?string $invoiceNumber = null;
    private ?string $ettn;
    private ?string $issueDate     = null;
    private ?string $issueTime     = null;
    private ?Party $supplier       = null;
    private ?Party $customer       = null;
    private ?string $notes         = null;

    /** @var InvoiceLine[] */
    private array $lines = [];

    private ?string $orderReference = null;
    private ?string $despatchReference = null;

    public function __construct()
    {
        // ETTN ve issueTime varsayılan — ilk bilgi objesi oluşturulur oluşturulmaz
        $this->ettn      = Uuid::v4();
        $this->issueTime = date('H:i:s');
    }

    /**
     * Fatura numarası (zorunlu, GİB formatı: 3 harf + 4 yıl + 9 seri = 16 char).
     * Örnek: SF02026000000001, TUR2026000000001
     */
    public function setInvoiceNumber(string $number): self
    {
        if (!preg_match('/^[A-Z]{3}[0-9]{13}$/', $number)) {
            throw new InvalidInvoiceDataException(
                "Fatura numarası formatı: 3 büyük harf + 13 rakam = 16 karakter olmalı. ".
                "Verilen: '$number' (". strlen($number) ." karakter)"
            );
        }
        $this->invoiceNumber = $number;
        return $this;
    }

    /**
     * ETTN (Evrensel Tekil Tanımlayıcı Numarası). Verilmezse otomatik üretilir.
     */
    public function setEttn(?string $uuid = null): self
    {
        if ($uuid === null) {
            $this->ettn = Uuid::v4();
        } else {
            if (!Uuid::isValid($uuid)) {
                throw new InvalidInvoiceDataException("Geçersiz ETTN: '$uuid' (UUID v4 olmalı)");
            }
            $this->ettn = strtolower($uuid);
        }
        return $this;
    }

    /**
     * Düzenlenme tarihi — YYYY-MM-DD formatı veya DateTimeInterface.
     */
    public function setIssueDate(string|\DateTimeInterface $date): self
    {
        if ($date instanceof \DateTimeInterface) {
            $this->issueDate = $date->format('Y-m-d');
            $this->issueTime = $date->format('H:i:s');
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new InvalidInvoiceDataException("IssueDate YYYY-MM-DD formatında olmalı: '$date'");
            }
            $this->issueDate = $date;
            $this->issueTime ??= date('H:i:s');
        }
        return $this;
    }

    public function setIssueTime(string $time): self
    {
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            throw new InvalidInvoiceDataException("IssueTime HH:MM:SS formatında olmalı: '$time'");
        }
        $this->issueTime = $time;
        return $this;
    }

    public function setProfile(string $profile): self
    {
        $valid = [self::PROFILE_TEMEL, self::PROFILE_TICARI, self::PROFILE_EARSIV, self::PROFILE_IHRACAT];
        if (!in_array($profile, $valid, true)) {
            throw new InvalidInvoiceDataException("Geçersiz profil: '$profile'. Geçerli: ".implode(', ', $valid));
        }
        $this->profileId = $profile;
        return $this;
    }

    public function setInvoiceType(string $type): self
    {
        $valid = [self::TYPE_SATIS, self::TYPE_IADE, self::TYPE_TEVKIFAT,
                  self::TYPE_ISTISNA, self::TYPE_OZELMATRAH, self::TYPE_IHRACKAYITLI];
        if (!in_array($type, $valid, true)) {
            throw new InvalidInvoiceDataException("Geçersiz fatura türü: '$type'. Geçerli: ".implode(', ', $valid));
        }
        $this->invoiceType = $type;
        return $this;
    }

    public function setCurrency(string $code): self
    {
        $code = strtoupper($code);
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            throw new InvalidInvoiceDataException("Para birimi ISO 4217 3-harf kodu olmalı: '$code'");
        }
        $this->currencyCode = $code;
        return $this;
    }

    public function setSupplier(Party $supplier): self
    {
        if (!$supplier->isLegalPerson()) {
            throw new InvalidInvoiceDataException(
                'Satıcı VKN sahibi tüzel kişi olmalı (şahıs firması da VKN alır)'
            );
        }
        $this->supplier = $supplier;
        return $this;
    }

    public function setCustomer(Party $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function addLine(InvoiceLine $line): self
    {
        $this->lines[] = $line;
        return $this;
    }

    public function setNotes(string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function setOrderReference(?string $orderId): self
    {
        $this->orderReference = $orderId;
        return $this;
    }

    public function setDespatchReference(?string $despatchId): self
    {
        $this->despatchReference = $despatchId;
        return $this;
    }

    /**
     * Tüm zorunlu alanların dolu olduğunu doğrular; eksikse istisna fırlatır.
     */
    private function validateRequired(): void
    {
        $missing = [];
        if ($this->invoiceNumber === null) $missing[] = 'invoiceNumber';
        if ($this->supplier === null)      $missing[] = 'supplier';
        if ($this->customer === null)      $missing[] = 'customer';
        if ($this->issueDate === null)     $missing[] = 'issueDate';
        if (empty($this->lines))           $missing[] = 'lines (en az 1 satır)';
        if ($missing) {
            throw new InvalidInvoiceDataException('Zorunlu alanlar eksik: '.implode(', ', $missing));
        }
        // ETTN verilmediyse otomatik üret
        if ($this->ettn === null) {
            $this->ettn = Uuid::v4();
        }
        // IssueTime verilmediyse şimdi
        $this->issueTime ??= date('H:i:s');
    }

    /**
     * @return array{line_total: float, tax_total: float, grand_total: float, payable: float}
     */
    public function calculateTotals(): array
    {
        $lineTotal = 0.0;
        $taxTotal  = 0.0;
        foreach ($this->lines as $ln) {
            $lineTotal += $ln->taxableAmount();
            $taxTotal  += $ln->vatAmount();
        }
        $grand = round($lineTotal + $taxTotal, 2);
        return [
            'line_total'  => round($lineTotal, 2),
            'tax_total'   => round($taxTotal, 2),
            'grand_total' => $grand,
            'payable'     => $grand,
        ];
    }

    /**
     * UBL-TR 2.1 XML üretir.
     */
    public function build(): string
    {
        $this->validateRequired();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS_MAIN, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DS);
        $dom->appendChild($root);

        // ext:UBLExtensions — XAdES imzası buraya girecek (imzalama aşamasında doldurulur)
        $extensions = $dom->createElementNS(self::NS_EXT, 'ext:UBLExtensions');
        $extension  = $dom->createElementNS(self::NS_EXT, 'ext:UBLExtension');
        $extContent = $dom->createElementNS(self::NS_EXT, 'ext:ExtensionContent');
        // boş — imzalayıcı dolduracak
        $extension->appendChild($extContent);
        $extensions->appendChild($extension);
        $root->appendChild($extensions);

        // Temel bilgiler
        $this->appendCbc($dom, $root, 'UBLVersionID', '2.1');
        $this->appendCbc($dom, $root, 'CustomizationID', 'TR1.2');
        $this->appendCbc($dom, $root, 'ProfileID', $this->profileId);
        $this->appendCbc($dom, $root, 'ID', $this->invoiceNumber);
        $this->appendCbc($dom, $root, 'CopyIndicator', 'false');
        $this->appendCbc($dom, $root, 'UUID', $this->ettn);
        $this->appendCbc($dom, $root, 'IssueDate', $this->issueDate);
        $this->appendCbc($dom, $root, 'IssueTime', $this->issueTime);
        $this->appendCbc($dom, $root, 'InvoiceTypeCode', $this->invoiceType);
        if ($this->notes !== null) {
            $this->appendCbc($dom, $root, 'Note', $this->notes);
        }
        $this->appendCbc($dom, $root, 'DocumentCurrencyCode', $this->currencyCode);
        $this->appendCbc($dom, $root, 'LineCountNumeric', (string)count($this->lines));

        // Referanslar (opsiyonel)
        if ($this->orderReference) {
            $or = $dom->createElementNS(self::NS_CAC, 'cac:OrderReference');
            $this->appendCbc($dom, $or, 'ID', $this->orderReference);
            $this->appendCbc($dom, $or, 'IssueDate', $this->issueDate);
            $root->appendChild($or);
        }
        if ($this->despatchReference) {
            $dr = $dom->createElementNS(self::NS_CAC, 'cac:DespatchDocumentReference');
            $this->appendCbc($dom, $dr, 'ID', $this->despatchReference);
            $this->appendCbc($dom, $dr, 'IssueDate', $this->issueDate);
            $root->appendChild($dr);
        }

        // Supplier & Customer
        $root->appendChild($this->buildPartyElement($dom, 'AccountingSupplierParty', $this->supplier));
        $root->appendChild($this->buildPartyElement($dom, 'AccountingCustomerParty', $this->customer));

        // Toplam vergi
        $totals = $this->calculateTotals();
        $root->appendChild($this->buildTaxTotal($dom, $totals['tax_total']));

        // LegalMonetaryTotal
        $lmt = $dom->createElementNS(self::NS_CAC, 'cac:LegalMonetaryTotal');
        $this->appendCbcCurrency($dom, $lmt, 'LineExtensionAmount',  $totals['line_total']);
        $this->appendCbcCurrency($dom, $lmt, 'TaxExclusiveAmount',   $totals['line_total']);
        $this->appendCbcCurrency($dom, $lmt, 'TaxInclusiveAmount',   $totals['grand_total']);
        $this->appendCbcCurrency($dom, $lmt, 'PayableAmount',        $totals['payable']);
        $root->appendChild($lmt);

        // Invoice satırları
        foreach ($this->lines as $line) {
            $root->appendChild($this->buildInvoiceLine($dom, $line));
        }

        $xml = $dom->saveXML();

        // PHP DOMDocument, createElementNS ile her child elemente xmlns:cbc="..." gibi
        // gereksiz namespace declaration'ları ekler. Root'ta zaten bulunduğu için
        // çocuklardaki tekrarları temizle — XML hala valid, daha kompakt.
        $this->stripRedundantNamespaces($xml);

        return $xml;
    }

    /**
     * Root (Invoice) dışındaki elementlerde tekrarlayan xmlns:* tanımlarını kaldırır.
     * Referans verilerek in-place çalışır.
     */
    private function stripRedundantNamespaces(string &$xml): void
    {
        // Root açılış tag'ini korumacı olarak ayır
        if (!preg_match('/^(<\?xml[^>]*>\s*<Invoice[^>]*>)(.*)$/s', $xml, $m)) {
            return;
        }
        $header = $m[1];
        $body   = $m[2];
        // Body içindeki gereksiz namespace declaration'larını sil
        $body = preg_replace('/\s+xmlns:(cbc|cac|ext|ds)="[^"]*"/', '', $body);
        $xml  = $header . $body;
    }

    // ────────────────────────────────────────────────────────────
    //  İç yardımcı builder metodları
    // ────────────────────────────────────────────────────────────

    private function appendCbc(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElementNS(self::NS_CBC, "cbc:$name", htmlspecialchars($value, ENT_XML1|ENT_QUOTES, 'UTF-8'));
        $parent->appendChild($el);
    }

    private function appendCbcCurrency(\DOMDocument $dom, \DOMElement $parent, string $name, float $amount): void
    {
        $el = $dom->createElementNS(self::NS_CBC, "cbc:$name", AmountFormatter::amount($amount));
        $el->setAttribute('currencyID', $this->currencyCode);
        $parent->appendChild($el);
    }

    private function buildPartyElement(\DOMDocument $dom, string $wrapperName, Party $party): \DOMElement
    {
        $wrapper = $dom->createElementNS(self::NS_CAC, "cac:$wrapperName");
        $partyEl = $dom->createElementNS(self::NS_CAC, 'cac:Party');

        // WebsiteURI (opsiyonel)
        if ($party->website !== null) {
            $this->appendCbc($dom, $partyEl, 'WebsiteURI', $party->website);
        }

        // PartyIdentification → VKN veya TCKN
        $pid = $dom->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
        $idEl = $dom->createElementNS(self::NS_CBC, 'cbc:ID', $party->taxId);
        $idEl->setAttribute('schemeID', $party->taxIdScheme);
        $pid->appendChild($idEl);
        $partyEl->appendChild($pid);

        // PartyName (tüzel için) veya Person (gerçek kişi için)
        if ($party->isLegalPerson()) {
            $pn = $dom->createElementNS(self::NS_CAC, 'cac:PartyName');
            $this->appendCbc($dom, $pn, 'Name', $party->name);
            $partyEl->appendChild($pn);
        }

        // PostalAddress
        $addr = $dom->createElementNS(self::NS_CAC, 'cac:PostalAddress');
        if ($party->address->street !== null) {
            $this->appendCbc($dom, $addr, 'StreetName', $party->address->street);
        }
        if ($party->address->buildingNumber !== null) {
            $this->appendCbc($dom, $addr, 'BuildingNumber', $party->address->buildingNumber);
        }
        if ($party->address->district !== null) {
            $this->appendCbc($dom, $addr, 'CitySubdivisionName', $party->address->district);
        }
        $this->appendCbc($dom, $addr, 'CityName', $party->address->city);
        if ($party->address->postalCode !== null) {
            $this->appendCbc($dom, $addr, 'PostalZone', $party->address->postalCode);
        }
        $country = $dom->createElementNS(self::NS_CAC, 'cac:Country');
        $this->appendCbc($dom, $country, 'Name', $party->address->country);
        $addr->appendChild($country);
        $partyEl->appendChild($addr);

        // PartyTaxScheme (vergi dairesi)
        if ($party->taxOffice !== null) {
            $pts = $dom->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
            $taxScheme = $dom->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $this->appendCbc($dom, $taxScheme, 'Name', $party->taxOffice);
            $pts->appendChild($taxScheme);
            $partyEl->appendChild($pts);
        }

        // Person (gerçek kişi için)
        if ($party->isNaturalPerson() && ($party->firstName || $party->familyName)) {
            $person = $dom->createElementNS(self::NS_CAC, 'cac:Person');
            if ($party->firstName) $this->appendCbc($dom, $person, 'FirstName', $party->firstName);
            if ($party->familyName) $this->appendCbc($dom, $person, 'FamilyName', $party->familyName);
            $partyEl->appendChild($person);
        }

        // Contact (telefon + e-posta)
        if ($party->phone !== null || $party->email !== null || $party->fax !== null) {
            $contact = $dom->createElementNS(self::NS_CAC, 'cac:Contact');
            if ($party->phone) $this->appendCbc($dom, $contact, 'Telephone', $party->phone);
            if ($party->fax)   $this->appendCbc($dom, $contact, 'Telefax',   $party->fax);
            if ($party->email) $this->appendCbc($dom, $contact, 'ElectronicMail', $party->email);
            $partyEl->appendChild($contact);
        }

        $wrapper->appendChild($partyEl);
        return $wrapper;
    }

    private function buildTaxTotal(\DOMDocument $dom, float $totalTax): \DOMElement
    {
        $tt = $dom->createElementNS(self::NS_CAC, 'cac:TaxTotal');
        $this->appendCbcCurrency($dom, $tt, 'TaxAmount', $totalTax);

        // Her KDV oranını gruplayarak TaxSubtotal yaz
        $byRate = [];  // rate => [taxable, tax]
        foreach ($this->lines as $ln) {
            $key = AmountFormatter::percent($ln->vatRate);
            if (!isset($byRate[$key])) $byRate[$key] = ['taxable'=>0.0, 'tax'=>0.0, 'rate'=>$ln->vatRate];
            $byRate[$key]['taxable'] += $ln->taxableAmount();
            $byRate[$key]['tax']     += $ln->vatAmount();
        }
        foreach ($byRate as $grp) {
            $sub = $dom->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
            $this->appendCbcCurrency($dom, $sub, 'TaxableAmount', round($grp['taxable'], 2));
            $this->appendCbcCurrency($dom, $sub, 'TaxAmount',     round($grp['tax'], 2));

            $perc = $dom->createElementNS(self::NS_CBC, 'cbc:Percent', AmountFormatter::percent($grp['rate']));
            $sub->appendChild($perc);

            $cat = $dom->createElementNS(self::NS_CAC, 'cac:TaxCategory');
            $scheme = $dom->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $this->appendCbc($dom, $scheme, 'Name',        'KDV');
            $this->appendCbc($dom, $scheme, 'TaxTypeCode', '0015');
            $cat->appendChild($scheme);
            $sub->appendChild($cat);

            $tt->appendChild($sub);
        }
        return $tt;
    }

    private function buildInvoiceLine(\DOMDocument $dom, InvoiceLine $ln): \DOMElement
    {
        $line = $dom->createElementNS(self::NS_CAC, 'cac:InvoiceLine');

        $this->appendCbc($dom, $line, 'ID', (string)$ln->id);

        $qty = $dom->createElementNS(self::NS_CBC, 'cbc:InvoicedQuantity', $ln->formattedQuantity());
        $qty->setAttribute('unitCode', $ln->unitCode);
        $line->appendChild($qty);

        $this->appendCbcCurrency($dom, $line, 'LineExtensionAmount', $ln->taxableAmount());

        // Satır bazlı iskonto varsa
        if ($ln->lineDiscount > 0) {
            $alc = $dom->createElementNS(self::NS_CAC, 'cac:AllowanceCharge');
            $this->appendCbc($dom, $alc, 'ChargeIndicator', 'false');
            $this->appendCbcCurrency($dom, $alc, 'Amount', $ln->lineDiscount);
            $this->appendCbcCurrency($dom, $alc, 'BaseAmount', $ln->grossAmount());
            $line->appendChild($alc);
        }

        // TaxTotal (satır düzeyinde)
        $satirTax = $dom->createElementNS(self::NS_CAC, 'cac:TaxTotal');
        $this->appendCbcCurrency($dom, $satirTax, 'TaxAmount', $ln->vatAmount());

        $sub = $dom->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
        $this->appendCbcCurrency($dom, $sub, 'TaxableAmount', $ln->taxableAmount());
        $this->appendCbcCurrency($dom, $sub, 'TaxAmount',     $ln->vatAmount());
        $perc = $dom->createElementNS(self::NS_CBC, 'cbc:Percent', AmountFormatter::percent($ln->vatRate));
        $sub->appendChild($perc);
        $cat = $dom->createElementNS(self::NS_CAC, 'cac:TaxCategory');
        $scheme = $dom->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $this->appendCbc($dom, $scheme, 'Name', 'KDV');
        $this->appendCbc($dom, $scheme, 'TaxTypeCode', '0015');
        $cat->appendChild($scheme);
        $sub->appendChild($cat);
        $satirTax->appendChild($sub);
        $line->appendChild($satirTax);

        // Item
        $item = $dom->createElementNS(self::NS_CAC, 'cac:Item');
        $this->appendCbc($dom, $item, 'Name', $ln->itemName);
        if ($ln->itemDescription) {
            $this->appendCbc($dom, $item, 'Description', $ln->itemDescription);
        }
        if ($ln->sellerItemCode) {
            $sid = $dom->createElementNS(self::NS_CAC, 'cac:SellersItemIdentification');
            $this->appendCbc($dom, $sid, 'ID', $ln->sellerItemCode);
            $item->appendChild($sid);
        }
        if ($ln->buyerItemCode) {
            $bid = $dom->createElementNS(self::NS_CAC, 'cac:BuyersItemIdentification');
            $this->appendCbc($dom, $bid, 'ID', $ln->buyerItemCode);
            $item->appendChild($bid);
        }
        $line->appendChild($item);

        // Price
        $price = $dom->createElementNS(self::NS_CAC, 'cac:Price');
        $priceAmount = $dom->createElementNS(self::NS_CBC, 'cbc:PriceAmount', $ln->formattedUnitPrice());
        $priceAmount->setAttribute('currencyID', $this->currencyCode);
        $price->appendChild($priceAmount);
        $line->appendChild($price);

        return $line;
    }

    /**
     * Debug amaçlı - JSON özet çıktı.
     */
    public function summary(): array
    {
        $totals = $this->calculateTotals();
        return [
            'invoice_number' => $this->invoiceNumber,
            'ettn'           => $this->ettn,
            'issue_date'     => $this->issueDate,
            'issue_time'     => $this->issueTime,
            'profile'        => $this->profileId,
            'type'           => $this->invoiceType,
            'currency'       => $this->currencyCode,
            'supplier'       => $this->supplier?->name,
            'supplier_vkn'   => $this->supplier?->taxId,
            'customer'       => $this->customer?->name,
            'customer_id'    => $this->customer?->taxId,
            'line_count'     => count($this->lines),
            'line_total'     => $totals['line_total'],
            'tax_total'      => $totals['tax_total'],
            'grand_total'    => $totals['grand_total'],
        ];
    }
}
