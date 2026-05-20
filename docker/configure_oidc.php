#!/usr/bin/env php
<?php
/**
 * Configure OIDC module settings from environment variables.
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$discoveryUri = trim(getenv('OIDC_DOMAIN') ?: '');
if ('' === $discoveryUri) {
    echo "OIDC_DOMAIN is not set. Skipping OIDC discovery configuration.\n";
    exit(0);
}

if (false === strpos($discoveryUri, '/.well-known/')) {
    $discoveryUri = rtrim($discoveryUri, '/') . '/.well-known/openid-configuration';
}

require 'bootstrap.php';

$app = \Laminas\Mvc\Application::init(
    require 'application/config/application.config.php'
);
$services = $app->getServiceManager();
$status = $services->get('Omeka\\Status');

if (!$status->isInstalled()) {
    echo "Omeka S is not installed yet. Skipping OIDC discovery configuration.\n";
    exit(0);
}

$settings = $services->get('Omeka\\Settings');
$settings->set('oidc_discovery', $discoveryUri);

echo "OIDC discovery URI configured: $discoveryUri\n";
