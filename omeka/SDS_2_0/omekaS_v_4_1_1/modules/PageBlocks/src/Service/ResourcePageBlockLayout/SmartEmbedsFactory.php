<?php
namespace PageBlocks\Service\ResourcePageBlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\ResourcePageBlockLayout\SmartEmbeds;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SmartEmbedsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SmartEmbeds(
            $services->get('Omeka\ModuleManager'));
    }
}
?>