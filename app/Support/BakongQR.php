<?php

namespace App\Support;

use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\MerchantInfo;

class BakongQR
{
    public static function generateMerchantQR(float $amount, ?string $invoiceNo = null, string $currencyCode = 'USD'): ?string
    {
        $account = config('bakong.merchant_account');
        $merchantName = config('bakong.merchant_name', 'My Store');
        $city = config('bakong.city', 'Phnom Penh');
        $merchantId = config('bakong.merchant_id', '123456');
        $acquiringBank = config('bakong.acquiring_bank', 'Your Bank');

        if (empty($account)) {
            return null;
        }

        // Expiration: 10 minutes from now (in milliseconds as string)
        $expirationMs = (string) (int) (microtime(true) * 1000 + 10 * 60 * 1000);
        $resolvedCurrencyCode = mb_strtoupper(trim($currencyCode));
        $isKhr = $resolvedCurrencyCode === 'KHR'
            || str_contains($resolvedCurrencyCode, 'RIEL')
            || str_contains($resolvedCurrencyCode, 'RIAL');
        $khqrCurrency = $isKhr ? KHQRData::CURRENCY_KHR : KHQRData::CURRENCY_USD;
        $finalAmount = $isKhr
            ? round(max(0, $amount), 0)
            : round(max(0, $amount), 2);

        $merchantInfo = new MerchantInfo(
            bakongAccountID: $account,
            merchantName: $merchantName,
            merchantCity: $city,
            merchantID: $merchantId,
            acquiringBank: $acquiringBank,
            currency: $khqrCurrency,
            amount: $finalAmount,
            billNumber: $invoiceNo,
            storeLabel: config('bakong.store_label', 'WEBSTORE'),
            terminalLabel: config('bakong.terminal_label', 'ONLINE'),
            expirationTimestamp: $expirationMs,
        );

        $response = BakongKHQR::generateMerchant($merchantInfo);

        return $response->data['qr'] ?? null;
    }
}
