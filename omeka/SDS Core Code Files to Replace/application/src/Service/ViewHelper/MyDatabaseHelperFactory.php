<?php

namespace Omeka\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\View\Helper\MyDatabaseHelper;
use Doctrine\DBAL\Connection;


class MyDatabaseHelperFactory implements FactoryInterface
{public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
{$connection = $container->get('Omeka\Connection');
return new MyDatabaseHelper($connection);}
}
