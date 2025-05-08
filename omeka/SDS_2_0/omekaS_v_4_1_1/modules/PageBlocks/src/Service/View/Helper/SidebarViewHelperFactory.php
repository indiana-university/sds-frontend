<?php
namespace PageBlocks\Service\View\Helper;

use Interop\Container\ContainerInterface;
use PageBlocks\View\Helper\SidebarViewHelper;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SidebarViewHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SidebarViewHelper(
            $services->get('FormElementManager'));
    }
}
?>