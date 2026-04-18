<?php

declare(strict_types=1);

namespace CodegaGib\Exception;

/**
 * Geçersiz fatura verisi istisnası.
 *
 * UblBuilder giriş validasyonunda zorunlu alan eksikliği, format hatası,
 * tutar tutarsızlığı gibi durumlarda fırlatılır.
 */
class InvalidInvoiceDataException extends CodegaGibException
{
}
