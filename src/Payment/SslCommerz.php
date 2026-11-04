<?php
declare(strict_types=1);

namespace App\Payment;

use App\Core\Config;
use App\Core\Logger;

/**
 * SSLCommerz v4 client.
 *
 * Trust model: the browser (success_url) and the gateway (ipn_url) are both
 * untrusted callers. Nothing is ever granted from their POST fields alone — the
 * val_id is re-checked against the gateway server-side and the amount, currency,
 * store id and transaction id must all match what we recorded.
 */
final class SslCommerz
{
    private const SANDBOX_PROCESS = 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';
    private const SANDBOX_VALIDATE = 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php';
    private const LIVE_PROCESS = 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';
    private const LIVE_VALIDATE = 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';

    public function __construct(
        private readonly string $storeId,
        private readonly string $storePassword,
        private readonly bool $sandbox,
        private readonly string $currency
    ) {
    }

    public static function make(): self
    {
        $config = Config::get('payment.sslcommerz', []);

        return new self(
            (string) ($config['store_id'] ?? ''),
            (string) ($config['store_password'] ?? ''),
            (bool) ($config['sandbox'] ?? true),
            (string) ($config['currency'] ?? 'BDT')
        );
    }

    public function isConfigured(): bool
    {
        return $this->storeId !== '' && $this->storePassword !== '';
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * @param array<string,mixed> $order
     * @return array{ok:bool, gateway_url?:string, error?:string}
     */
    public function initiate(array $order, string $tranId): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Card payments are not configured yet. Please use a manual payment method.'];
        }

        $apiBase = rtrim((string) Config::get('urls.api'), '/');
        $frontend = rtrim((string) Config::get('urls.frontend'), '/');

        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => number_format((float) $order['total_amount'], 2, '.', ''),
            'currency' => $this->currency,
            'tran_id' => $tranId,
            'success_url' => $apiBase . '/payment/success.php',
            'fail_url' => $apiBase . '/payment/fail.php',
            'cancel_url' => $apiBase . '/payment/cancel.php',
            'ipn_url' => $apiBase . '/payment/ipn.php',
            'cus_name' => $order['customer_name'],
            'cus_email' => $order['customer_email'],
            'cus_phone' => $order['customer_phone'],
            'cus_add1' => 'N/A',
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => 'Digital subscription — ' . $order['order_number'],
            'product_category' => 'Digital Goods',
            'product_profile' => 'general',
            // Only used to jump back to the right dashboard row; the payment row is
            // always resolved from tran_id, so this value is never trusted.
            'value_a' => $frontend . '/dashboard.html',
        ];

        $response = $this->post($this->endpoint(self::SANDBOX_PROCESS, self::LIVE_PROCESS), $payload);

        if (isset($response['error']) && !isset($response['status'])) {
            return ['ok' => false, 'error' => (string) $response['error']];
        }

        if (($response['status'] ?? '') !== 'SUCCESS' || empty($response['GatewayPageURL'])) {
            Logger::warning('Gateway refused session', ['tran_id' => $tranId, 'reason' => (string) ($response['failedreason'] ?? 'unknown')));

            return ['ok' => false, 'error' => 'The payment gateway refused this transaction. Please try another method.'];
        }

        return ['ok' => true, 'gateway_url' => (string) $response['GatewayPageURL']];
    }

    /** @return array<string,mixed>|null */
    public function validate(string $valId): ?array
    {
        if (!$this->isConfigured() || $valId === '') {
            return null;
        }

        $url = $this->endpoint(self::SANDBOX_VALIDATE, self::LIVE_VALIDATE) . '?' . http_build_query([
            'val_id' => $valId,
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'format' => 'json',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || !is_string($body)) {
            Logger::error('Gateway validation call failed', ['errno' => $errno]);
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Compares a gateway verdict against the payment row we wrote before the
     * redirect. Any single mismatch means "not paid" — fail closed.
     *
     * @param array<string,mixed> $response
     * @return array{ok:bool, reason:string}
     */
    public function verify(array $response, string $tranId, float $expectedAmount): array
    {
        $status = strtoupper((string) ($response['status'] ?? ''));

        if (!in_array($status, ['VALID', 'VALIDATED'], true)) {
            return ['ok' => false, 'reason' => 'gateway_reports_' . strtolower($status ?: 'unknown')];
        }

        if ((string) ($response['store_id'] ?? '') !== $this->storeId) {
            return ['ok' => false, 'reason' => 'store_id_mismatch'];
        }

        if ((string) ($response['tran_id'] ?? '') !== $tranId) {
            return ['ok' => false, 'reason' => 'tran_id_mismatch'];
        }

        if (strtoupper((string) ($response['currency'] ?? '')) !== strtoupper($this->currency)) {
            return ['ok' => false, 'reason' => 'currency_mismatch'];
        }

        $claimed = round((float) ($response['amount'] ?? -1), 2);
        $gatewayAmount = isset($response['gw_amount']) ? round((float) $response['gw_amount'], 2) : $claimed;

        if (abs($claimed - $expectedAmount) > 0.009 || abs($gatewayAmount - $expectedAmount) > 0.009) {
            return ['ok' => false, 'reason' => 'amount_mismatch'];
        }

        return ['ok' => true, 'reason' => 'verified'];
    }

    /** @return array<string,mixed> decoded response, or ['error' => string] when the call failed */
    private function post(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || !is_string($body)) {
            Logger::error('Gateway session call failed', ['errno' => $errno]);
            return ['error' => 'The payment gateway is unreachable right now. Please try again shortly.'];
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            Logger::error('Gateway returned a non-JSON body');
            return ['error' => 'Unexpected response from the payment gateway.'];
        }

        return $decoded;
    }

    private function endpoint(string $sandbox, string $live): string
    {
        return $this->sandbox ? $sandbox : $live;
    }
}
