<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\MediaDetails;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaDetailsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MediaDetails(
            $services->get('FormElementManager'));
    }
}
?>