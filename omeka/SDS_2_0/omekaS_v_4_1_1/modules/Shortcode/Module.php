<?php declare(strict_types=1);

/**
 * Shortcode
 *
 * Insert shortcuts in site pages in order to render more content via a simple string.
 *
 * @copyright Daniel Berthereau, 2021
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace Shortcode;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected function preInstall(): void
    {
        require_once __DIR__ . '/data/scripts/upgrade_vocabulary.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Page',
            'view.show.before',
            [$this, 'onSitePageShowBefore']
        );
        // Possibilities: view.layout; rep.resource.values; rep.resource.display_values.
        // If rep.resource.values, rep.resource.json_output will be affected.
        // If rep.resource.display_values, only in the main display, not when
        // description is displayed separately.
        // If view.layout, may be heavy (and remove event for page).
        // rep.value.string and rep.value.html, but not rep.value.json? May be
        // heavy too, but designed for that. So limited to html?
        // In theme? In layout or manually each time a value is queried? No, too heavy.
        // Let to be used in module?
        // Add a config? Site setting level (no: it's resource based).
        // Limit by type and property, or even template.
    }

    public function onSitePageShowBefore(Event $event): void
    {
        /**
         * @var \Laminas\View\Renderer\PhpRenderer $view
         * @var \Shortcode\View\Helper\Shortcodes $shortcodes
         * @var string $content
         */
        $view = $event->getTarget();
        $shortcodes = $view->getHelperPluginManager()->get('shortcodes');
        $view->content = $shortcodes($view->content);
    }
}
