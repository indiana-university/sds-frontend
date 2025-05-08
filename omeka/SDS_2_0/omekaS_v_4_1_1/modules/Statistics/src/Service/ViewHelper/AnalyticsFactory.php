<?php declare(strict_types=1);

namespace Statistics\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Statistics\View\Helper\Analytics;

class AnalyticsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        $apiAdapters = $services->get('Omeka\ApiAdapterManager');
        return new Analytics(
            $apiAdapters->get('hits'),
            $apiAdapters->get('stats')
        );
    }
}
