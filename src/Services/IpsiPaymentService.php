<?php

namespace Krixnaas\IpsiLaravel\Services;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class IpsiPaymentService
{
    protected int $amount;
    protected $user;
    protected ?string $token = null;
    protected ?string $brand = null;
    protected array $config;

    public function __construct()
    {
        $this->config = [
            'username'      => config('ipsi.username'),
            'config_id'     => config('ipsi.config_id'),
            'shared_secret' => config('ipsi.shared_secret'),
            'base_url'      => config('ipsi.base_url'),
        ];
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * Set the payment amount in cents.
     *
     * @param int $cents Must be a positive integer
     * @return $this
     * @throws InvalidArgumentException
     */
    public function amount(int $cents): self
    {
        if ($cents <= 0) {
            throw new InvalidArgumentException('Amount must be a positive integer in cents.');
        }

        $this->amount = $cents;
        return $this;
    }

    /**
     * Set the user (expects an object with at least an `id` property)
     */
    public function user($user): self
    {
        if (!is_object($user) || !isset($user->id)) {
            throw new InvalidArgumentException('User must be an object with an `id` property.');
        }

        $this->user = $user;
        return $this;
    }

    /**
     * Set the token (for saved cards)
     */
    public function token(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * (Optional) Set brand for merchant reference (will be sanitized)
     */
    public function brand(string $brand): self
    {
        $this->brand = preg_replace('/[^A-Z0-9]/i', '', strtoupper($brand));
        return $this;
    }

    /**
     * Ensure required data is set
     *
     * @throws InvalidArgumentException
     */
    protected function ensureRequiredData(): void
    {
        if (!$this->user) {
            throw new InvalidArgumentException('User must be set before generating URL.');
        }

        if (!$this->amount) {
            throw new InvalidArgumentException('Amount must be set before generating URL.');
        }
    }

    /**
     * Build merchant reference using brand, random digits, and user ID
     */
    protected function buildMerchReference(): string
    {
        $random = random_int(100000, 999999);
        return strtoupper($this->brand ?? '') . '-' . $random . '-' . $this->user->id;
    }

    /**
     * Create a HMAC SHA-256 signature
     */
    protected function hashSignature(string $data): string
    {
        return hash_hmac('sha256', $data, $this->config['shared_secret']);
    }

    /**
     * Build the query parameter string for redirect
     */
    protected function buildParams(string $merchRef): string
    {
        $params = [
            'merchReference' => $merchRef,
            'userName'       => $this->config['username'],
            'configId'       => $this->config['config_id'],
            'txnType'        => 0,
            'amount'         => $this->amount / 100,
        ];

        if ($this->token) {
            $params['cardToken'] = $this->token;
        } else {
            $params['tokenControl.token'] = 'true';
        }

        return http_build_query($params);
    }

    /**
     * Generate the payment URL with signature
     */
    public function getUrl(): string
    {
        $this->ensureRequiredData();

        $merchRef = $this->buildMerchReference();
        $paramString = $this->buildParams($merchRef);
        $verifyMessage = $this->hashSignature($paramString);

        return rtrim($this->config['base_url'], '/') . '?' . $paramString . '&verifyMessage=' . urlencode($verifyMessage);
    }

    /**
     * Extract confirmation details from IPSI callback
     */
    public function confirmation(array $data): array
    {
        return [
            'cardToken'      => Arr::get($data, 'cardToken'),
            'txnReference'   => Arr::get($data, 'txnReference', ''),
            'amount'         => Arr::get($data, 'amount', ''),
            'maskedPAN'      => Arr::get($data, 'maskedPAN', ''),
            'merchReference' => Arr::get($data, 'merchReference', ''),
            'hierarchy'      => Arr::get($data, 'hierarchy', ''),
            'customerId'     => Arr::get($data, 'customerId', ''),
            'notificationId' => Arr::get($data, 'notificationId', ''),
            'userName'       => Arr::get($data, 'userName', ''),
        ];
    }
}
