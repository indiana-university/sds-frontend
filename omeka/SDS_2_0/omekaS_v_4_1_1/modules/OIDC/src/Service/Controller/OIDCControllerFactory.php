<?php

namespace OIDC\Service\Controller;

use Interop\Container\ContainerInterface;
use OIDC\Controller\OIDCController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Session\Container;

class OIDCControllerFactory implements FactoryInterface
{
	public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
	{
		return new OIDCController(
			$services->get('Omeka\EntityManager'),
			$services->get('Omeka\AuthenticationService'),
			$services->get('ViewHelperManager')->get('BasePath'),
			$services->get('Config'),
			$services->get('Omeka\Logger'),
		);
	}
}
