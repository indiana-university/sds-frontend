#!/usr/bin/env php
<?php
/**
 * Patch the bundled OIDC module for Kubernetes ingress deployment.
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$moduleDir = $argv[1] ?? '';
if ('' === $moduleDir || !is_dir($moduleDir)) {
    fwrite(STDERR, "Usage: php patch_oidc_module.php /path/to/OIDC\n");
    exit(1);
}

$metadataProviderBuilder = $moduleDir . '/vendor/facile-it/php-openid-client/src/Issuer/Metadata/Provider/MetadataProviderBuilder.php';
if (!is_file($metadataProviderBuilder)) {
    fwrite(STDERR, "Unable to find MetadataProviderBuilder.php in OIDC module.\n");
    exit(1);
}

$source = file_get_contents($metadataProviderBuilder);
$from = <<<'PHP'
        $provider = new RemoteProvider([
            $this->buildDiscoveryProvider(),
            $this->buildWebFingerProvider(),
        ]);
PHP;

$to = <<<'PHP'
        $provider = new RemoteProvider([
            $this->buildDiscoveryProvider(),
        ]);
PHP;

if (false === strpos($source, $from)) {
    if (false !== strpos($source, $to)) {
        echo "OIDC module already patched for direct discovery.\n";
    } else {
        fwrite(STDERR, "Unable to patch MetadataProviderBuilder.php: expected provider block not found.\n");
        exit(1);
    }
} else {
    file_put_contents($metadataProviderBuilder, str_replace($from, $to, $source));
    echo "Patched OIDC module to disable WebFinger metadata fallback.\n";
}

$controller = $moduleDir . '/src/Controller/OIDCController.php';
if (!is_file($controller)) {
    fwrite(STDERR, "Unable to find OIDCController.php in OIDC module.\n");
    exit(1);
}

$source = file_get_contents($controller);
$from = <<<'PHP'
        $this->redirect = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $basePath() . "/oidc/redirect";
PHP;

$to = <<<'PHP'
        $scheme = 'http';
        $forwardedProto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
        if ('https' === $forwardedProto
            || (!empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']))
            || 443 === (int) ($_SERVER['SERVER_PORT'] ?? 0)
        ) {
            $scheme = 'https';
        }
        $this->redirect = $scheme . "://" . $_SERVER['HTTP_HOST'] . $basePath() . "/oidc/redirect";
PHP;

if (false === strpos($source, $from)) {
    if (false !== strpos($source, $to)) {
        echo "OIDC controller already patched for forwarded HTTPS.\n";
        exit(0);
    }

    fwrite(STDERR, "Unable to patch OIDCController.php: expected redirect line not found.\n");
    exit(1);
}

file_put_contents($controller, str_replace($from, $to, $source));
echo "Patched OIDC controller to honor forwarded HTTPS.\n";
