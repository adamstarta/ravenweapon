<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\CookieProvider;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class CookieProvider implements CookieProviderInterface
{
    private const requiredCookies = [
        [
            'snippet_name' => 'Payrexx Payments',
            'cookie'       => 'payrexx',
        ],
    ];

    /** @var CookieProviderInterface */
    private $parentProvider;

    public function __construct(CookieProviderInterface $parentProvider)
    {
        $this->parentProvider = $parentProvider;
    }

    public function getCookieGroups(): array
    {
        $groups = $this->parentProvider->getCookieGroups();

        $groups[0]['entries'] = array_merge($groups[0]['entries'], self::requiredCookies);

        return $groups;
    }
}
