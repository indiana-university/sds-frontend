#!/usr/bin/env php
<?php
/**
 * Upgrade Omeka S modules whose files are newer than the version recorded in
 * the database (state "needs_upgrade"). Uses the module files already on disk;
 * nothing is downloaded.
 *
 * Run from the Omeka S root, after the core database migration.
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

require 'bootstrap.php';

// Omeka's own init() also loads the active modules, which upgrades can depend on.
$app = \Omeka\Mvc\Application::init(
    require 'application/config/application.config.php'
);
$services = $app->getServiceManager();
$manager = $services->get('Omeka\\ModuleManager');

if (!$services->get('Omeka\\Status')->isInstalled()) {
    echo "Omeka S is not installed yet. Skipping module upgrades.\n";
    exit(0);
}

$modules = $manager->getModulesByState(\Omeka\Module\Manager::STATE_NEEDS_UPGRADE);
if (!$modules) {
    echo "No modules need an upgrade.\n";
    exit(0);
}

// Upgrading is permission checked; act as the first global administrator.
$entityManager = $services->get('Omeka\\EntityManager');
$admin = $entityManager->getRepository(\Omeka\Entity\User::class)
    ->findOneBy(['role' => 'global_admin', 'isActive' => true], ['id' => 'ASC']);
if (!$admin) {
    echo "ERROR: no active global_admin user found to run module upgrades.\n";
    exit(1);
}
$services->get('Omeka\\AuthenticationService')->getStorage()->write($admin);

$failed = 0;
foreach ($modules as $id => $module) {
    $from = $module->getDb()['version'] ?? 'unknown';
    try {
        $manager->upgrade($module);
        echo sprintf("Module '%s' upgraded: %s -> %s\n", $id, $from, $module->getIni('version'));
    } catch (\Throwable $e) {
        $failed++;
        echo sprintf("ERROR: failed to upgrade module '%s': %s\n", $id, $e->getMessage());
    }
}

exit($failed ? 1 : 0);
