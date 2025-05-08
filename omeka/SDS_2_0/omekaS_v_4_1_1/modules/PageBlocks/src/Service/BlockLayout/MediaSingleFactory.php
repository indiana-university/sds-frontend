<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\MediaSingle;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaSingleFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MediaSingle(
            $services->get('FormElementManager'));
    }
}
?>