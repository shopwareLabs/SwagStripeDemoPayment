<?php declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Repository\ShopRepository;
use App\Struct\ApiKey;
use App\SwagAppsystem\Authenticator;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ApiKeyResolver implements ArgumentValueResolverInterface
{
    private ShopRepository $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if ($argument->getType() !== ApiKey::class) {
            return false;
        }

        if ($request->getMethod() === 'POST' && $this->supportsPostRequest($request)) {
            $requestContent = \json_decode($request->getContent(), true);
            $shopId = $requestContent['source']['shopId'];

            $shopSecret = $this->shopRepository->getSecretByShopId($shopId);

            return Authenticator::authenticatePostRequest($request, $shopSecret);
        } elseif ($request->getMethod() === 'GET') {
            return $request->query->has('shop-id');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        if ($request->getMethod() === 'POST') {
            $requestContent = json_decode($request->getContent(), true);
            $shopId = $requestContent['source']['shopId'];
        } else {
            $shopId = $request->query->get('shop-id');
        }

        // with this, we should now fetch shop specific credentials for Stripe, aka an API Key.
        // for this, we would need a separate configuration process which we are not going to implement here
        // for now, we have provided a Test-API-Key from Stripe. This might expire at some point, feel free to replace yourself

        Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
        yield new ApiKey('pk_test_TYooMQauvdEDq54NiTphI7jx', $shopId);
    }

    private function supportsPostRequest(Request $request): bool
    {
        $requestContent = json_decode($request->getContent(), true);

        $hasSource = $requestContent && array_key_exists('source', $requestContent);

        if (!$hasSource) {
            return false;
        }

        $requiredKeys = ['url', 'shopId'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $requestContent['source'])) {
                return false;
            }
        }

        return true;
    }
}
