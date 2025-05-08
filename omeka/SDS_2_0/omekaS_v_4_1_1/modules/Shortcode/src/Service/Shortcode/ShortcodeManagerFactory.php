<?php declare(strict_types=1);

namespace Shortcode\Service\Shortcode;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Shortcode\Shortcode\Manager;

class ShortcodeManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Manager($services, $services->get('Config')['shortcodes']);
    }
}
