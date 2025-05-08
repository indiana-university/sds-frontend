<?php declare(strict_types=1);

namespace Statistics\Service\Controller;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Statistics\Controller\StatisticsController;

class StatisticsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Access');
        $hasAccess = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        $module = $moduleManager->getModule('AdvancedSearch');
        $hasAdvancedSearch = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;

        return new StatisticsController(
            $services->get('Omeka\Connection'),
            $services->get('Omeka\ApiAdapterManager'),
            $hasAccess,
            $hasAdvancedSearch
        );
    }
}
