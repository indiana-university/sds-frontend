<?php declare(strict_types=1);

namespace Shortcode;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.3.1.2', '<')) {
    require_once __DIR__ . '/upgrade_vocabulary.php';
}

if (version_compare($oldVersion, '3.3.1.6', '<')) {
    require_once __DIR__ . '/upgrade_vocabulary.php';
}

if (version_compare($oldVersion, '3.3.1.7', '<')) {
    $message = new Message(
        'It’s now possible to display the primary image of a resource with shortcut [image].' // @translate
    );
    $messenger->addSuccess($message);
}
