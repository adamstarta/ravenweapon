<?php declare(strict_types=1);

namespace RavenTheme\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly GenericPageLoaderInterface $genericPageLoader
    ) {
    }

    #[Route(
        path: '/hersteller/{manufacturerSlug}/{subcategorySlug}',
        name: 'frontend.manufacturer.subcategory',
        requirements: ['manufacturerSlug' => '[a-zA-Z0-9-]+', 'subcategorySlug' => '[a-zA-Z0-9-]+'],
        defaults: ['_httpCache' => true],
        methods: ['GET']
    )]
    public function subcategory(string $manufacturerSlug, string $subcategorySlug, Request $request, SalesChannelContext $context): Response
    {
        // Load base page (header, footer, etc.)
        $page = $this->genericPageLoader->load($request, $context);

        // Find manufacturer by slugified name
        $manufacturer = $this->findManufacturerBySlug(strtolower($manufacturerSlug), $context);

        // Show "coming soon" page for subcategories
        return $this->renderStorefront('@RavenTheme/storefront/page/manufacturer/coming-soon.html.twig', [
            'page' => $page,
            'manufacturerSlug' => $manufacturerSlug,
            'manufacturerName' => $manufacturer ? ($manufacturer->getTranslation('name') ?? $manufacturer->getName()) : $this->unslugify($manufacturerSlug),
            'subcategorySlug' => $subcategorySlug,
            'subcategoryName' => $this->unslugify($subcategorySlug),
            'manufacturer' => $manufacturer,
        ]);
    }

    #[Route(
        path: '/hersteller/{manufacturerSlug}',
        name: 'frontend.manufacturer.page',
        requirements: ['manufacturerSlug' => '[a-z0-9-]+'],
        defaults: ['_httpCache' => true],
        methods: ['GET']
    )]
    public function index(string $manufacturerSlug, Request $request, SalesChannelContext $context): Response
    {
        // Find manufacturer by slugified name
        $manufacturer = $this->findManufacturerBySlug($manufacturerSlug, $context);

        // Load base page (header, footer, etc.)
        $page = $this->genericPageLoader->load($request, $context);

        if ($manufacturer === null) {
            // Show "coming soon" page for unknown manufacturers
            return $this->renderStorefront('@RavenTheme/storefront/page/manufacturer/coming-soon.html.twig', [
                'page' => $page,
                'manufacturerSlug' => $manufacturerSlug,
                'manufacturerName' => $this->unslugify($manufacturerSlug),
            ]);
        }

        // Load products for this manufacturer using SalesChannel repository for calculated prices
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('manufacturerId', $manufacturer->getId()));
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->setLimit(500); // Show all products (no practical limit)

        // Use SalesChannelContext for price calculation
        $products = $this->productRepository->search($criteria, $context);

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

    private function unslugify(string $slug): string
    {
        // Convert hyphens to spaces and capitalize each word
        return ucwords(str_replace('-', ' ', $slug));
    }
}
