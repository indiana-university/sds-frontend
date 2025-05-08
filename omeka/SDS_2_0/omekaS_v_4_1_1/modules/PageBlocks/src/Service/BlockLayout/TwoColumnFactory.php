<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\TwoColumn;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TwoColumnFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new TwoColumn(
            $services->get('FormElementManager'));
    }
}
?>