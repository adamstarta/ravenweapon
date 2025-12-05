<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Administration;

use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class PayrexxPaymentMethodController
{
    /**
     * @Route("/api/_action/payrexx_payment/validate-api-credentials", name="api.action.payrexx_payment.validate.api.credentials", methods={"POST"})
     * @throws \Payrexx\PayrexxException
     */
    #[Route(path: '/api/_action/payrexx_payment/validate-api-credentials', name: 'api.action.payrexx_payment.validate.api.credentials', methods: ['POST'])]
    public function validateApiCredentials(Request $request, Context $context): JsonResponse
    {
        require_once dirname(dirname(__DIR__)). '/vendor/autoload.php';
        $config = $request->get('credentials', []);

        $platform = !empty($config['platform']) ? $config['platform'] : '';
        $payrexx = new \Payrexx\Payrexx($config['instanceName'], $config['apiKey'], '', $platform);

        $signatureCheck = new \Payrexx\Models\Request\SignatureCheck();

        $error = '';
        try {
            $payrexx->getOne($signatureCheck);
        } catch(\Payrexx\PayrexxException $e) {
            $error = $e;
        }
        return new JsonResponse(['credentialsValid' => !$error, 'error' => $error]);

    }

}
