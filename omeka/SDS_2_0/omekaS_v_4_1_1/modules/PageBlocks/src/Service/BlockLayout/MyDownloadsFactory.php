<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\MyDownloads;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MyDownloadsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MyDownloads(
            $services->get('FormElementManager'));
    }
}
?>