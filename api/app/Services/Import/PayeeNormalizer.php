<?php

namespace App\Services\Import;

/**
 * "PAYPAL (EUROPE) S.A R.L. ET CIE" + "… Ihr Einkauf bei Netflix" → "NETFLIX"
 * "Spotify AB 4711-2026" → "SPOTIFY"
 */
class PayeeNormalizer
{
    /** Payment services: the real merchant is in the purpose text */
    private const PAYMENT_SERVICES = ['PAYPAL', 'KLARNA', 'STRIPE', 'ADYEN', 'MOLLIE', 'GOOGLE PAYMENT', 'APPLE'];

    private const PREFIXES = [
        'PAYPAL \*', 'PP\.\d+\.PP \*', 'SEPA-LASTSCHRIFT', 'SEPA LASTSCHRIFT', 'LASTSCHRIFT', 'FOLGELASTSCHRIFT',
        'ERSTLASTSCHRIFT', 'DAUERAUFTRAG', 'KARTENZAHLUNG', 'SEPA-BASISLASTSCHRIFT',
    ];

    private const LEGAL_FORMS = [
        'GMBH & CO\. KG', 'GMBH & CO KG', 'GMBH', 'MBH', 'AG', 'SE', 'KG', 'OHG', 'E\.V\.', 'EV', 'LTD', 'LIMITED', 'INC',
        'B\.V\.', 'BV', 'S\.A R\.L\.', 'SARL', 'S\.A\.', 'SA', 'AB', 'ASA', 'PLC', 'LLC', 'CO', 'ET CIE', 'S\.C\.A\.', 'SCA',
        'EUROPE', 'DEUTSCHLAND', 'GERMANY', 'DE',
    ];

    /** PayPal & Co. use one mandate for all purchases, so the mandate says nothing about the contract. */
    public function isPaymentService(string $payee): bool
    {
        $name = $this->clean($payee);
        foreach (self::PAYMENT_SERVICES as $service) {
            if (str_starts_with($name, $service)) {
                return true;
            }
        }

        return false;
    }

    public function normalize(string $payee, string $purpose = ''): string
    {
        $name = $this->clean($payee);

        foreach (self::PAYMENT_SERVICES as $service) {
            if (str_starts_with($name, $service)) {
                $merchant = $this->merchantFromPurpose($purpose);
                if ($merchant !== null) {
                    return $merchant;
                }
            }
        }

        return $name;
    }

    /** Readable name for the suggestion, e.g. "NETFLIX" → "Netflix" */
    public function displayName(string $normalized): string
    {
        $words = array_map(function (string $w) {
            // keep short all-caps words like "DAZN", "WOW", "ADAC"
            return mb_strlen($w) <= 4 ? $w : mb_convert_case(mb_strtolower($w), MB_CASE_TITLE);
        }, explode(' ', $normalized));

        return mb_substr(trim(implode(' ', $words)), 0, 100) ?: 'Unbekannt';
    }

    private function clean(string $value): string
    {
        $v = mb_strtoupper(trim($value));
        $v = strtr($v, ['Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ẞ' => 'SS']);

        foreach (self::PREFIXES as $prefix) {
            $v = preg_replace('/^'.$prefix.'\s*/u', '', $v) ?? $v;
        }

        // References, contract numbers, dates: tokens with 3+ digits (keeps "1&1", "1PASSWORD")
        $v = preg_replace('/\S*\d\S*\d\S*\d\S*/u', ' ', $v) ?? $v;
        $v = preg_replace('/[^\p{L}\p{N}&+ ]/u', ' ', $v) ?? $v;
        $v = ' '.preg_replace('/\s+/', ' ', $v).' ';

        foreach (self::LEGAL_FORMS as $form) {
            $plain = str_replace('\\', '', $form);
            $pattern = preg_replace('/[^\p{L}&+ ]/u', ' ', $plain);
            $pattern = trim(preg_replace('/\s+/', ' ', (string) $pattern));
            if ($pattern !== '') {
                $v = preg_replace('/ '.preg_quote($pattern, '/').'(?= )/u', ' ', $v) ?? $v;
            }
        }

        $v = trim(preg_replace('/\s+/', ' ', $v) ?? $v, ' &+');

        return mb_substr($v, 0, 120);
    }

    private function merchantFromPurpose(string $purpose): ?string
    {
        $p = mb_strtoupper($purpose);

        if (preg_match('/(?:IHR EINKAUF BEI|EINKAUF BEI|ZAHLUNG AN|BEI)\s+([^,;]+)/u', $p, $m)) {
            $name = $this->clean($m[1]);

            return $name !== '' ? $name : null;
        }

        return null;
    }
}
