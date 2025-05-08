<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\JumbotronSearch;
use Laminas\ServiceManager\Factory\FactoryInterface;

class JumbotronSearchFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new JumbotronSearch(
            $services->get('FormElementManager'));
    }
}
?>