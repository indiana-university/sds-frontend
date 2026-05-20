#!/usr/bin/env php
<?php
/**
 * Patch the bundled OIDC module to use direct OpenID discovery only.
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
        exit(0);
    }

    fwrite(STDERR, "Unable to patch MetadataProviderBuilder.php: expected provider block not found.\n");
    exit(1);
}

file_put_contents($metadataProviderBuilder, str_replace($from, $to, $source));
echo "Patched OIDC module to disable WebFinger metadata fallback.\n";
