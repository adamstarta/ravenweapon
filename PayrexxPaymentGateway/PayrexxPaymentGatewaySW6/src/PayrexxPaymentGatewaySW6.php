<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway;

use PayrexxPaymentGateway\Installer\PayrexxPaymentInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class PayrexxPaymentGatewaySW6 extends Plugin
{

    public function install(InstallContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->install($context);
        parent::install($context);
    }

    public function uninstall(UninstallContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->uninstall($context);
        parent::uninstall($context);
    }

    public function activate(ActivateContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->activate($context);
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->deactivate($context);
        parent::deactivate($context);
    }

    public function update(UpdateContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->update($context);
        parent::update($context);
    }

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        if (!$this->isActive()) {
            return;
        }

        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');
        $routeDir =  $this->getPath() . '/Resources/config/compatibility/routes/';

        if (version_compare($version, '6.4', '>=') && version_compare($version, '6.5', '<')) {
            $routeFile = $routeDir . 'routes_64.xml';
        } else {
            $routeFile = $routeDir . 'routes_6.xml';
        }
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($routeFile)) {
            $routes->import($routeFile);
        }
    }
}
