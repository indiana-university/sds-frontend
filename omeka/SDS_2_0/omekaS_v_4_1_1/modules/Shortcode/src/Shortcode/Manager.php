<?php declare(strict_types=1);

namespace Shortcode\Shortcode;

use Omeka\ServiceManager\AbstractPluginManager;

class Manager extends AbstractPluginManager
{
    protected $autoAddInvokableClass = false;

    protected $instanceOf = ShortcodeInterface::class;
}
