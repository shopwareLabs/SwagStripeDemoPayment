<?php declare(strict_types=1);

namespace App\Tests\E2E\Helper;

use App\SwagAppsystem\Client;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;

class StoreApiClient
{
    public const SALES_CHANNEL = '98432def39fc4624b33213a56b8c944d';

    private Client $client;

    private string $accessKey;

    private ?string $contextToken = null;

    private array $salesChannel;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $salesChannel = $client->search('sales-channel', [
            'ids' => [self::SALES_CHANNEL], // defaults
            'associations' => ['domains' => []],
        ])['data'][0];

        if (!$salesChannel) {
            throw new \Exception('Invalid response');
        }

        $this->salesChannel = $salesChannel;
        $this->accessKey = $salesChannel['accessKey'] ?? '';
        if (!$this->accessKey) {
            throw new \Exception('Could not fetch access key');
        }

        if (!$salesChannel['domains']) {
            $this->storefrontUrl = 'http://storefront.url';

            $snippetSetId = $client->searchIds('snippet-set', [])['data'][0];

            try {
                $client->createEntity('sales-channel-domain', [
                    'url' => $this->storefrontUrl,
                    'salesChannelId' => self::SALES_CHANNEL,
                    'languageId' => $salesChannel['languageId'],
                    'currencyId' => $salesChannel['currencyId'],
                    'snippetSetId' => $snippetSetId,
                ]);
            } catch (ServerException $e) {
                var_dump($e->getResponse()->getBody()->getContents());
            }
        } else {
            $this->storefrontUrl = current($salesChannel['domains'])['url'];
        }
    }

    public function getSalesChannel()
    {
        return $this->salesChannel;
    }

    public function addToCart(array $lineItemData): void
    {
        $response = $this->client->getHttpClient()->request('POST', '/store-api/checkout/cart/line-item', [
            'headers' => [
                'sw-access-key' => $this->accessKey,
                'sw-context-token' => $this->contextToken,
            ],
            'body' => \json_encode($lineItemData),
        ]);
        $this->updateContextToken($response);
    }

    public function registerAccount(array $userData): void
    {
        $response = $this->client->getHttpClient()->request('POST', '/store-api/account/register', [
            'headers' => [
                'sw-access-key' => $this->accessKey,
                'sw-context-token' => $this->contextToken,
            ],
            'body' => \json_encode(\array_merge([
                'storefrontUrl' => $this->storefrontUrl,
            ], $userData)),
        ]);
        $this->updateContextToken($response);
    }

    public function orderCheckout(): array
    {
        $response = $this->client->getHttpClient()->request('POST', '/store-api/checkout/order', [
            'headers' => [
                'sw-access-key' => $this->accessKey,
                'sw-context-token' => $this->contextToken,
            ],
        ]);
        $this->updateContextToken($response);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function handlePayment(array $formParams): array
    {
        $response = $this->client->getHttpClient()->request('POST', '/store-api/handle-payment', [
            'headers' => [
                'sw-access-key' => $this->accessKey,
                'sw-context-token' => $this->contextToken,
            ],
            'form_params' => $formParams,
        ]);
        $this->updateContextToken($response);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function updateContextToken(ResponseInterface $response): void
    {
        $this->contextToken = explode(', ', $response->getHeaderLine('sw-context-token'))[0];
    }
}
