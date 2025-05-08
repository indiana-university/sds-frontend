<?php declare(strict_types=1);

namespace Shortcode\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Shortcode\View\Helper\Shortcodes;

class ShortcodesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Shortcodes(
            $services->get('ShortcodeManager')
        );
    }
}
