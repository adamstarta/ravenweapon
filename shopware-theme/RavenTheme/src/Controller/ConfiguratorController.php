<?php declare(strict_types=1);

namespace RavenTheme\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ConfiguratorController extends StorefrontController
{
    #[Route(path: '/konfigurator', name: 'frontend.configurator.index', methods: ['GET'])]
    public function index(SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@RavenTheme/storefront/page/configurator/index.html.twig', [
            'page' => []
        ]);
    }
}
