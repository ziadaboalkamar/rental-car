<?php

namespace App\Support\Payments;

use App\Models\PaymentProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MyFatoorahSubscriptionProvider
{
    public function listPaymentMethods(PaymentProvider $provider, float $amount, string $currency): array
    {
        $config = $this->providerConfig($provider);
        $baseUrl = $this->resolveBaseUrl($provider, $config);

        $response = $this->request($provider, 'post', rtrim($baseUrl, '/').'/v2/InitiatePayment', [
            'InvoiceAmount' => $amount > 0 ? $amount : 1,
            'CurrencyIso' => strtoupper(trim($currency)) !== '' ? strtoupper(trim($currency)) : 'USD',
        ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->extractErrorMessage($response->json()) ?: 'MyFatoorah InitiatePayment failed.');
        }

        $methods = data_get($response->json(), 'Data.PaymentMethods');
        if (!is_array($methods)) {
            return [];
        }

        return collect($methods)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                return [
                    'id' => (int) ($item['PaymentMethodId'] ?? 0),
                    'name' => trim((string) ($item['PaymentMethodEn'] ?? $item['PaymentMethodAr'] ?? '')),
                    'name_ar' => trim((string) ($item['PaymentMethodAr'] ?? '')),
                    'name_en' => trim((string) ($item['PaymentMethodEn'] ?? '')),
                    'is_direct' => (bool) ($item['IsDirectPayment'] ?? false),
                    'service_charge' => $this->toFloatOrNull($item['ServiceCharge'] ?? null),
                    'total_amount' => $this->toFloatOrNull($item['TotalAmount'] ?? null),
                    'currency' => trim((string) ($item['CurrencyIso'] ?? '')),
                    'image_url' => trim((string) ($item['ImageUrl'] ?? '')),
                ];
            })
            ->filter(fn (array $item) => $item['id'] > 0 && $item['name'] !== '')
            ->values()
            ->all();
    }

    public function createCheckout(array $payload, PaymentProvider $provider): array
    {
        $config = $this->providerConfig($provider);
        $amount = max(0.01, (float) ($payload['amount'] ?? 0));
        $currency = $this->normalizeCurrency((string) ($payload['currency'] ?? ''), $config);

        $paymentMethodId = $this->resolvePaymentMethodId(
            $provider,
            $config,
            $amount,
            $currency,
            isset($payload['payment_method_id']) ? (int) $payload['payment_method_id'] : null
        );

        if ($paymentMethodId <= 0) {
            throw new RuntimeException('MyFatoorah payment_method_id is required in provider config to start checkout.');
        }

        $baseUrl = $this->resolveBaseUrl($provider, $config);
        $callbackUrl = trim((string) ($payload['callback_url'] ?? ''));
        $errorUrl = trim((string) ($payload['error_url'] ?? ''));

        if (!filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid callback URL for MyFatoorah.');
        }

        if (!filter_var($errorUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid error URL for MyFatoorah.');
        }

        $requestBody = [
            'PaymentMethodId' => $paymentMethodId,
            'InvoiceValue' => $amount,
            'CallBackUrl' => $callbackUrl,
            'ErrorUrl' => $errorUrl,
            'Language' => (string) ($payload['language'] ?? 'en'),
            'CustomerName' => (string) ($payload['customer_name'] ?? ''),
            'CustomerEmail' => (string) ($payload['customer_email'] ?? ''),
            'CustomerMobile' => $this->normalizeCustomerMobile($payload['customer_mobile'] ?? null),
            'DisplayCurrencyIso' => $currency,
            'CustomerReference' => (string) ($payload['customer_reference'] ?? ''),
            'UserDefinedField' => (string) ($payload['user_defined_field'] ?? ''),
            'InvoiceItems' => $payload['items'] ?? [],
        ];

        // MyFatoorah rejects empty strings for some optional fields; strip empty values.
        $requestBody = array_filter($requestBody, static function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            if (is_array($value)) {
                return $value !== [];
            }

            return true;
        });

        $response = $this->request($provider, 'post', rtrim($baseUrl, '/').'/v2/ExecutePayment', $requestBody);

        if (!$response->successful()) {
            Log::warning('MyFatoorah ExecutePayment failed', [
                'provider_id' => $provider->id,
                'status' => $response->status(),
                'request' => $requestBody,
                'response' => $response->json(),
            ]);

            throw new RuntimeException($this->extractErrorMessage($response->json()) ?: 'MyFatoorah ExecutePayment failed.');
        }

        $json = $response->json();
        $paymentUrl = data_get($json, 'Data.PaymentURL');
        if (!is_string($paymentUrl) || trim($paymentUrl) === '') {
            throw new RuntimeException('MyFatoorah did not return a payment URL.');
        }

        return [
            'payment_url' => $paymentUrl,
            'invoice_id' => data_get($json, 'Data.InvoiceId'),
            'raw' => is_array($json) ? $json : [],
        ];
    }

    public function verifyPaymentStatus(array $callbackQuery, PaymentProvider $provider): array
    {
        $config = $this->providerConfig($provider);
        $baseUrl = $this->resolveBaseUrl($provider, $config);

        $paymentId = trim((string) ($callbackQuery['paymentId'] ?? $callbackQuery['paymentid'] ?? $callbackQuery['Id'] ?? ''));
        $invoiceId = trim((string) ($callbackQuery['InvoiceId'] ?? $callbackQuery['invoiceId'] ?? ''));

        if ($paymentId === '' && $invoiceId === '') {
            throw new RuntimeException('MyFatoorah callback did not include paymentId or invoiceId.');
        }

        $key = $paymentId !== '' ? $paymentId : $invoiceId;
        $keyType = $paymentId !== '' ? 'paymentId' : 'invoiceId';

        $response = $this->request($provider, 'post', rtrim($baseUrl, '/').'/v2/GetPaymentStatus', [
            'Key' => $key,
            'KeyType' => $keyType,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->extractErrorMessage($response->json()) ?: 'MyFatoorah GetPaymentStatus failed.');
        }

        $json = $response->json();
        $data = is_array(data_get($json, 'Data')) ? data_get($json, 'Data') : [];

        $invoiceStatus = strtoupper(trim((string) ($data['InvoiceStatus'] ?? '')));
        $transactions = is_array($data['InvoiceTransactions'] ?? null) ? $data['InvoiceTransactions'] : [];
        $firstTxn = is_array($transactions[0] ?? null) ? $transactions[0] : [];

        $transactionStatus = strtoupper(trim((string) (
            $firstTxn['TransactionStatus'] ?? $firstTxn['TransactionStatusDescription'] ?? ''
        )));

        $paid = in_array($invoiceStatus, ['PAID'], true)
            || in_array($transactionStatus, ['SUCCESS', 'SUCCEEDED', 'PAID'], true);

        $failed = in_array($invoiceStatus, ['FAILED', 'CANCELED', 'CANCELLED'], true)
            || in_array($transactionStatus, ['FAILED', 'CANCELED', 'CANCELLED'], true);

        $amount = $this->toFloatOrNull($data['InvoiceValue'] ?? null)
            ?? $this->toFloatOrNull($firstTxn['PaidCurrencyValue'] ?? null);

        $currency = strtoupper(trim((string) (
            $data['DisplayCurrencyIso'] ?? $firstTxn['PaidCurrency'] ?? ''
        )));

        return [
            'is_paid' => $paid,
            'is_failed' => $failed && !$paid,
            'invoice_status' => strtolower($invoiceStatus),
            'payment_id' => (string) ($data['PaymentId'] ?? $paymentId ?: ''),
            'invoice_id' => (string) ($data['InvoiceId'] ?? $invoiceId ?: ''),
            'transaction_id' => (string) (
                $firstTxn['TransactionId'] ?? $firstTxn['PaymentId'] ?? $paymentId ?? ''
            ),
            'payment_method' => trim((string) ($data['PaymentGateway'] ?? $firstTxn['PaymentGateway'] ?? 'myfatoorah')),
            'amount_paid' => $amount,
            'currency' => $currency !== '' ? $currency : null,
            'paid_at' => $this->normalizeDateTime(
                $firstTxn['TransactionDate'] ?? $data['InvoiceDisplayValue'] ?? null
            ),
            'failure_reason' => $this->extractErrorMessage($json),
            'raw' => is_array($json) ? $json : [],
        ];
    }

    private function providerConfig(PaymentProvider $provider): array
    {
        $config = is_array($provider->config) ? $provider->config : [];

        $apiToken = trim((string) ($config['api_token'] ?? ''));
        if ($apiToken === '') {
            throw new RuntimeException('MyFatoorah API token is not configured.');
        }

        return $config;
    }

    private function request(PaymentProvider $provider, string $method, string $url, array $payload = [])
    {
        $config = is_array($provider->config) ? $provider->config : [];
        $apiToken = trim((string) ($config['api_token'] ?? ''));

        if ($apiToken === '') {
            throw new RuntimeException('MyFatoorah API token is not configured.');
        }

        return Http::acceptJson()
            ->withToken($apiToken)
            ->$method($url, $payload);
    }

    private function resolveBaseUrl(PaymentProvider $provider, array $config): string
    {
        $configured = trim((string) ($config['api_base_url'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return $provider->mode === 'live'
            ? 'https://api.myfatoorah.com'
            : 'https://apitest.myfatoorah.com';
    }

    private function extractErrorMessage(mixed $json): ?string
    {
        if (!is_array($json)) {
            return null;
        }

        $message = trim((string) (
            data_get($json, 'Message')
            ?? data_get($json, 'Data.ErrorMessage')
            ?? ''
        ));

        $validationErrors = data_get($json, 'ValidationErrors');
        if (is_array($validationErrors) && $validationErrors !== []) {
            $mapped = collect($validationErrors)
                ->filter(fn ($item) => is_array($item))
                ->map(function (array $item): string {
                    $field = trim((string) ($item['Name'] ?? $item['FieldName'] ?? ''));
                    $error = trim((string) ($item['Error'] ?? $item['Message'] ?? ''));
                    if ($field !== '' && $error !== '') {
                        return $field.': '.$error;
                    }

                    return $error !== '' ? $error : $field;
                })
                ->filter(fn (string $line) => $line !== '')
                ->values()
                ->all();

            if ($mapped !== []) {
                return implode(' | ', $mapped);
            }
        }

        $fieldErrors = data_get($json, 'FieldsErrors');
        if (is_array($fieldErrors) && $fieldErrors !== []) {
            $mapped = collect($fieldErrors)
                ->filter(fn ($item) => is_array($item))
                ->map(fn (array $item): string => trim((string) ($item['Error'] ?? $item['Message'] ?? '')))
                ->filter(fn (string $line) => $line !== '')
                ->values()
                ->all();

            if ($mapped !== []) {
                return implode(' | ', $mapped);
            }
        }

        if ($message !== '') {
            return $message;
        }

        return trim((string) (data_get($json, 'Data.InvoiceTransactions.0.Error') ?? '')) ?: null;
    }

    private function normalizeCustomerMobile(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // MyFatoorah commonly rejects symbols and malformed lengths.
        // Keep digits-only and send only plausible mobile lengths.
        $length = strlen($digits);
        if ($length < 7 || $length > 15) {
            return null;
        }

        return $digits;
    }

    private function resolvePaymentMethodId(
        PaymentProvider $provider,
        array $config,
        float $amount,
        string $currency,
        ?int $requestedId
    ): int {
        $configuredId = (int) ($config['payment_method_id'] ?? 0);
        $candidate = (int) ($requestedId ?: $configuredId);

        try {
            $methods = $this->listPaymentMethods($provider, $amount, $currency);
            $methodIds = collect($methods)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($methodIds !== []) {
                if (in_array($candidate, $methodIds, true)) {
                    return $candidate;
                }

                if ($configuredId > 0 && in_array($configuredId, $methodIds, true)) {
                    return $configuredId;
                }

                return $methodIds[0];
            }
        } catch (Throwable $e) {
            Log::warning('Could not load MyFatoorah payment methods before ExecutePayment', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $candidate;
    }

    private function normalizeCurrency(string $currency, array $config): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency !== '') {
            return $currency;
        }

        $configured = strtoupper(trim((string) ($config['currency'] ?? '')));
        if ($configured !== '') {
            return $configured;
        }

        $country = strtoupper(trim((string) ($config['country'] ?? '')));
        $countryToCurrency = [
            'KW' => 'KWD',
            'SA' => 'SAR',
            'OM' => 'OMR',
            'AE' => 'AED',
            'BH' => 'BHD',
            'QA' => 'QAR',
            'US' => 'USD',
        ];

        return $countryToCurrency[$country] ?? 'USD';
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches) !== 1) {
            return null;
        }

        return (float) $matches[0];
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
