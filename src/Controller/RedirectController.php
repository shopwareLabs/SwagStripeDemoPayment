<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Struct\ApiKey;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RedirectController extends AbstractController
{
    private OrderRepository $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/redirect/success/{transaction}", name="payment.redirect.success", methods={"GET"})
     */
    public function redirectSuccess(string $transaction, ApiKey $apiKey): Response
    {
        return $this->updateStatus($transaction);
    }

    /**
     * @Route("/redirect/cancel/{transaction}", name="payment.redirect.cancel", methods={"GET"})
     */
    public function redirectCancel(string $transaction, ApiKey $apiKey): Response
    {
        return $this->updateStatus($transaction, 'cancel');
    }

    private function updateStatus(string $transactionId, ?string $newStatus = null): Response
    {
        $order = $this->orderRepository->fetchOrder($transactionId);

        if ($order === null) {
            throw new BadRequestHttpException('Invalid session');
        }

        if ($newStatus === null) {
            try {
                $session = Session::retrieve($order['session_id']);
                $newStatus = $session->payment_status === 'paid' ? 'paid' : 'authorize';
            } catch (ApiErrorException $exception) {
                $newStatus = 'fail';
            }
        }

        $this->orderRepository->updateOrderStatus($newStatus, $transactionId);

        return RedirectResponse::create($order['return_url']);
    }
}
