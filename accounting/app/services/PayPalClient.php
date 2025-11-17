<?php

class PayPalClient
{
    private string $clientId;
    private string $clientSecret;
    private bool $isSandbox;
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;

    public function __construct(string $clientId, string $clientSecret, bool $isSandbox = true)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->isSandbox = $isSandbox;
    }

    private function getBaseUrl(): string
    {
        return $this->isSandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    private function authenticate(): bool
    {
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return true;
        }

        $url = $this->getBaseUrl() . '/v1/oauth2/token';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ':' . $this->clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return false;
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            return false;
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + (int)($data['expires_in'] ?? 3600) - 60;

        return true;
    }

    public function getBalance(): ?array
    {
        if (!$this->authenticate()) {
            return null;
        }

        $url = $this->getBaseUrl() . '/v1/reporting/balances';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['balances'] ?? [];
    }
}
