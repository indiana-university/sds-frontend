<?php declare(strict_types=1);

namespace Statistics\Service\Controller;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Statistics\Controller\AnalyticsController;

class AnalyticsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AnalyticsController(
            $services->get('Omeka\Connection')
        );
    }
}
