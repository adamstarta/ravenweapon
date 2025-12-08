<?php declare(strict_types=1);

namespace RavenTheme\Controller;

use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ManufacturerPageController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $manufacturerRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly GenericPageLoaderInterface $genericPageLoader
    ) {
    }

    #[Route(
        path: '/{manufacturerSlug}',
        name: 'frontend.manufacturer.page',
        requirements: ['manufacturerSlug' => '[a-z0-9-]+'],
        defaults: ['_httpCache' => true],
        methods: ['GET'],
        priority: -1000
    )]
    public function index(string $manufacturerSlug, Request $request, SalesChannelContext $context): Response
    {
        // Find manufacturer by slugified name
        $manufacturer = $this->findManufacturerBySlug($manufacturerSlug, $context);

        if ($manufacturer === null) {
            // Let Shopware handle 404
            throw $this->createNotFoundException('Manufacturer not found');
        }

        // Load base page (header, footer, etc.)
        $page = $this->genericPageLoader->load($request, $context);

        // Load products for this manufacturer
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('manufacturerId', $manufacturer->getId()));
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->setLimit(100);

        $products = $this->productRepository->search($criteria, $context->getContext());

        // Get unique categories from these products for the filter
        $categoryIds = [];
        foreach ($products->getEntities() as $product) {
            if ($product->getCategoryTree()) {
                foreach ($product->getCategoryTree() as $catId) {
                    $categoryIds[$catId] = true;
                }
            }
        }

        $categories = null;
        if (!empty($categoryIds)) {
            $catCriteria = new Criteria(array_keys($categoryIds));
            $catCriteria->addFilter(new EqualsFilter('active', true));
            $categories = $this->categoryRepository->search($catCriteria, $context->getContext());
        }

        return $this->renderStorefront('@RavenTheme/storefront/page/manufacturer/index.html.twig', [
            'page' => $page,
            'manufacturer' => $manufacturer,
            'products' => $products->getEntities(),
            'categories' => $categories ? $categories->getEntities() : [],
            'totalProducts' => $products->getTotal(),
        ]);
    }

    private function findManufacturerBySlug(string $slug, SalesChannelContext $context): ?object
    {
        $criteria = new Criteria();
        $manufacturers = $this->manufacturerRepository->search($criteria, $context->getContext());

        foreach ($manufacturers->getEntities() as $manufacturer) {
            $name = $manufacturer->getTranslation('name') ?? $manufacturer->getName();
            if ($this->slugify($name) === $slug) {
                return $manufacturer;
            }
        }

        return null;
    }

    private function slugify(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace spaces and underscores with hyphens
        $text = preg_replace('/[\s_]+/', '-', $text);

        // Remove special characters except hyphens
        $text = preg_replace('/[^a-z0-9-]/', '', $text);

        // Remove multiple consecutive hyphens
        $text = preg_replace('/-+/', '-', $text);

        // Trim hyphens from start and end
        return trim($text, '-');
    }
}
