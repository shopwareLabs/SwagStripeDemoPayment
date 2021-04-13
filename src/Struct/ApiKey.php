<?php declare(strict_types=1);

namespace App\Struct;

class ApiKey
{
    private string $publicApiKey;

    private string $shopId;

    public function __construct(string $publicApiKey, string $shopId)
    {
        $this->publicApiKey = $publicApiKey;
        $this->shopId = $shopId;
    }

    public function getPublicApiKey(): string
    {
        return $this->publicApiKey;
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }
}
