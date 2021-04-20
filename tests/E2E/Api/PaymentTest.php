<?php declare(strict_types=1);

namespace App\Tests\E2E\Api;

use App\Tests\E2E\Helper\StoreApiClient;
use App\Tests\E2E\Traits\ContractTestTrait;
use App\Tests\E2E\Traits\E2ETestTrait;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    use E2ETestTrait;
    use ContractTestTrait;

    public function tearDown(): void
    {
        $this->stopServer();
    }

    public function testPayment(): void
    {
        $productId = bin2hex(random_bytes(16));

        $this->setPaymentMethod();
        $storeApiClient = new StoreApiClient($this->getClient());

        $taxId = $this->getClient()->searchIds('tax', [])['data'][0];
        $salutationId = $this->getClient()->searchIds('salutation', [])['data'][0];

        $this->getClient()->createEntity('product', [
            'id' => $productId,
            'name' => 'my product',
            'taxId' => $taxId,
            'price' => [
                [
                    'currencyId' => $storeApiClient->getSalesChannel()['currencyId'],
                    'gross' => '100',
                    'linked' => true,
                    'net' => '90',
                ],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => StoreApiClient::SALES_CHANNEL,
                    'visibility' => 30,
                ],
            ],
            'productNumber' => $productId,
            'stock' => 100,
        ]);

        $storeApiClient->registerAccount([
            'salutationId' => $salutationId,
            'firstName' => 'Alice',
            'lastName' => 'Apple',
            'email' => bin2hex(random_bytes(8)) . '@example.com',
            'password' => 'ilovefruits',
            'billingAddress' => [
                'street' => 'Apple Alley 42',
                'zipcode' => '1234-5',
                'city' => 'Appleton',
                'countryId' => $storeApiClient->getSalesChannel()['countryId'],
            ],
        ]);

        $storeApiClient->addToCart([
            'items' => [
                [
                    'type' => 'product',
                    'referencedId' => $productId,
                ],
            ],
        ]);

        $order = $storeApiClient->orderCheckout();
        $orderId = $order['id'];
        $transactionId = $order['transactions'][0]['id'];

        $redirectUrl = $storeApiClient->handlePayment(['orderId' => $orderId])['redirectUrl'];
        static::assertStringContainsString('/pay/', $redirectUrl);

        $returnUrl = $this->getOrderRepository()->fetchColumn('return_url', $transactionId);
        static::assertNotEmpty($returnUrl);

        $transaction = $this->getClient()->fetchDetail('order-transaction', $transactionId)['data'];
        static::assertSame('in_progress', $transaction['stateMachineState']['technicalName']);

        $this->getOrderRepository()->updateOrderStatus('paid', $transactionId);

        $this->getClient()->getHttpClient()->request('GET', $returnUrl);

        $transaction = $this->getClient()->fetchDetail('order-transaction', $transactionId)['data'];
        static::assertSame('paid', $transaction['stateMachineState']['technicalName']);
    }

    private function setPaymentMethod(): void
    {
        $paymentMethodId = $this->getClient()->searchIds('payment-method', [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'handlerIdentifier',
                    'value' => 'app\\SwagStripeDemoPayment_stripe',
                ],
            ],
        ])['data'][0] ?? '';

        if (!$paymentMethodId) {
            throw new \Exception('Could not find payment method');
        }

        $this->getClient()->updateEntity('sales-channel', StoreApiClient::SALES_CHANNEL, [
            'paymentMethodId' => $paymentMethodId,
            'paymentMethods' => [
                [
                    'id' => $paymentMethodId,
                ],
            ],
        ]);
    }
}
