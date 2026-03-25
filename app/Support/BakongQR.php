<?php

namespace App\Support;

use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\MerchantInfo;

class BakongQR
{
    public static function generateMerchantQR(float $amountUsd, ?string $invoiceNo = null): ?string
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

        $merchantInfo = new MerchantInfo(
            bakongAccountID: $account,
            merchantName: $merchantName,
            merchantCity: $city,
            merchantID: $merchantId,
            acquiringBank: $acquiringBank,
            currency: KHQRData::CURRENCY_USD,
            amount: round($amountUsd, 2),
            billNumber: $invoiceNo,
            storeLabel: config('bakong.store_label', 'WEBSTORE'),
            terminalLabel: config('bakong.terminal_label', 'ONLINE'),
            expirationTimestamp: $expirationMs,
        );

        $response = BakongKHQR::generateMerchant($merchantInfo);

        return $response->data['qr'] ?? null;
    }
}
