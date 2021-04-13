<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Struct\ApiKey;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class PaymentController extends AbstractController
{
    private RouterInterface $router;

    private ShopRepository $shopRepository;

    private OrderRepository $orderRepository;

    public function __construct(RouterInterface $router, ShopRepository $shopRepository, OrderRepository $orderRepository)
    {
        $this->router = $router;
        $this->shopRepository = $shopRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/payment/process", name="payment.process", methods={"POST"})
     */
    public function paymentStarted(ApiKey $apiKey, Request $request): JsonResponse
    {
        $content = \json_decode($request->getContent(), true);

        try {
            $session = $this->startSession($content);
        } catch (ApiErrorException $exception) {
            return $this->sign(
                ['message' => 'Communication with Stripe failed: ' . $exception->getMessage()],
                $content['source']['shopId']
            );
        } catch (\Throwable $exception) {
            return $this->sign(
                ['message' => 'Could not decode request: ' . $exception->getMessage()],
                $content['source']['shopId']
            );
        }

        $this->orderRepository->insertNewOrder([
            'transaction_id' => $content['orderTransaction']['id'],
            'order_id' => $content['order']['id'],
            'shop_id' => $content['source']['shopId'],
            'session_id' => $session->id,
            'return_url' => $content['returnUrl'],
        ]);

        return $this->sign(
            [
                'redirectUrl' => $this->router->generate(
                    'payment.pay',
                    [
                        'transaction' => $content['orderTransaction']['id'],
                        'shop-id' => $content['source']['shopId'],
                    ],
                    RouterInterface::ABSOLUTE_URL
                ),
            ],
            $content['source']['shopId']
        );
    }

    /**
     * @Route("/payment/finalize", name="payment.finalize", methods={"POST"})
     */
    public function paymentFinalized(ApiKey $apiKey, Request $request): Response
    {
        $content = \json_decode($request->getContent(), true);

        $status = $this->orderRepository->fetchColumn('status', $content['orderTransaction']['id']);

        return $this->sign(
            ['status' => $status ?? 'fail'],
            $content['source']['shopId']
        );
    }

    /**
     * @throws ApiErrorException
     */
    private function startSession(array $content): Session
    {
        $currency = $content['order']['currency']['isoCode'];
        $amount = $content['orderTransaction']['amount']['totalPrice'];
        $amount *= pow(10, 2); // this should obviously be better implemented to support zero-decimal currencies: https://stripe.com/docs/currencies
        $customerEmail = $content['order']['orderCustomer']['email'];
        $orderNumber = $content['order']['orderNumber'];

        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => \sprintf('Order %s', $orderNumber),
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $customerEmail,
            'success_url' => $this->router->generate(
                'payment.redirect.success',
                [
                    'transaction' => $content['orderTransaction']['id'],
                    'shop-id' => $content['source']['shopId'],
                ],
                RouterInterface::ABSOLUTE_URL
            ),
            'cancel_url' => $this->router->generate(
                'payment.redirect.cancel',
                [
                    'transaction' => $content['orderTransaction']['id'],
                    'shop-id' => $content['source']['shopId'],
                ],
                RouterInterface::ABSOLUTE_URL
            ),
        ]);
    }

    private function sign(array $content, string $shopId): JsonResponse
    {
        $response = new JsonResponse($content);

        $secret = $this->shopRepository->getSecretByShopId($shopId);

        $hmac = \hash_hmac('sha256', $response->getContent(), $secret);
        $response->headers->set('shopware-app-signature', $hmac);

        return $response;
    }
}
