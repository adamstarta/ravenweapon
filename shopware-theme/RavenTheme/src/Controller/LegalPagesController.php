<?php declare(strict_types=1);

namespace RavenTheme\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class LegalPagesController extends StorefrontController
{
    #[Route(path: '/agb', name: 'frontend.legal.agb', options: ['seo' => false], defaults: ['_httpCache' => true], methods: ['GET'])]
    public function agb(Request $request, SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@RavenTheme/storefront/page/legal/agb.html.twig', [
            'pageTitle' => 'Allgemeine Geschäftsbedingungen'
        ]);
    }

    #[Route(path: '/impressum', name: 'frontend.legal.impressum', options: ['seo' => false], defaults: ['_httpCache' => true], methods: ['GET'])]
    public function impressum(Request $request, SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@RavenTheme/storefront/page/legal/impressum.html.twig', [
            'pageTitle' => 'Impressum'
        ]);
    }

    #[Route(path: '/datenschutz', name: 'frontend.legal.datenschutz', options: ['seo' => false], defaults: ['_httpCache' => true], methods: ['GET'])]
    public function datenschutz(Request $request, SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@RavenTheme/storefront/page/legal/datenschutz.html.twig', [
            'pageTitle' => 'Datenschutzerklärung'
        ]);
    }
}
