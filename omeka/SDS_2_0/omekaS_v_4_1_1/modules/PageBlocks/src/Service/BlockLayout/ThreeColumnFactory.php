<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\ThreeColumn;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ThreeColumnFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ThreeColumn(
            $services->get('FormElementManager'));
    }
}
?>