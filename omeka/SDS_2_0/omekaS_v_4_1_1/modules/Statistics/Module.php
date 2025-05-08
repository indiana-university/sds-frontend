<?php declare(strict_types=1);

namespace Statistics;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceRepresentation;

/**
 * Stats
 *
 * Logger that counts views of pages and resources and makes stats about usage
 * and users of the site.
 *
 * @copyright Daniel Berthereau, 2014-2023
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $message = new \Omeka\Stdlib\Message(
            $translator->translate('To compute access to files, you must add a rule in file .htaccess at the root of Omeka. See %sreadme%s.'), // @translate
            '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-Statistics" target="_blank" rel="noopener">', '</a>'
        );
        $message->setEscapeHtml(false);
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $messenger->addWarning($message);
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $acl
            // These rights may be too much large: it's viewable by api and
            // contains sensitive informations.
            // FIXME Add a filter in the api to limit output.
            ->allow(
                null,
                [
                    \Statistics\Entity\Hit::class,
                    \Statistics\Entity\Stat::class,
                ],
                ['read', 'create', 'search']
            )
            ->allow(
                null,
                [
                    \Statistics\Api\Adapter\HitAdapter::class,
                    \Statistics\Api\Adapter\StatAdapter::class,
                ],
                ['read', 'create', 'search']
            )
            ->allow(
                null,
                ['Statistics\Controller\Download']
            )
        ;
        // Only admins are allowed to browse stats.
        // The individual stats are always displayed in admin.

        // The public rights are checked in controller according to the config.
        $settings = $services->get('Omeka\Settings');
        if ($settings->get('statistics_public_allow_statistics')) {
            $acl
                ->allow(
                    null,
                    ['Statistics\Controller\Statistics']
                );
        }
        if ($settings->get('statistics_public_allow_summary')) {
            $acl
                ->allow(
                    null,
                    ['Statistics\Controller\Analytics'],
                    ['index']
                );
        }
        // Browse implies Summary.
        if ($settings->get('statistics_public_allow_browse')) {
            $acl
                ->allow(
                    null,
                    ['Statistics\Controller\Analytics']
                );
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Events for the public front-end.
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            [$this, 'displayPublic']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\ItemSet',
            'view.show.after',
            [$this, 'displayPublic']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Media',
            'view.show.after',
            [$this, 'displayPublic']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Page',
            'view.show.after',
            [$this, 'displayPublic']
        );

        // Events for the admin front-end.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.details',
            [$this, 'viewDetails']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.details',
            [$this, 'viewDetails']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.details',
            [$this, 'viewDetails']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Index',
            'view.browse.after',
            [$this, 'filterAdminDashboardPanels']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );

        $sharedEventManager->attach(
            \BulkImport\Processor\EprintsProcessor::class,
            'bulk.import.after',
            [$this, 'handleBulkImportAfter']
        );
    }

    public function displayPublic(Event $event): void
    {
        $view = $event->getTarget();
        $resource = $view->vars()->offsetGet('resource');
        echo $view->analytics()->textResource($resource);
    }

    public function viewDetails(Event $event): void
    {
        $view = $event->getTarget();
        $representation = $event->getParam('entity');
        $statTitle = $view->translate('Analytics'); // @translate
        $statText = $this->resultResource($view, $representation);
        $html = <<<HTML
<div class="meta-group">
    <h4>$statTitle</h4>
    $statText
</div>

HTML;
        echo $html;
    }

    protected function resultResource(PhpRenderer $view, AbstractResourceRepresentation $resource)
    {
        /** @var \Statistics\View\Helper\Analytics $analytics */
        $plugins = $view->getHelperPluginManager();
        $analytics = $plugins->get('analytics');
        $translate = $plugins->get('translate');

        $html = '<ul>';
        $html .= '<li>';
        $html .= sprintf(
            $translate('Views: %d (anonymous: %d / users: %d)'), // @translate
            $analytics->totalResource($resource),
            $analytics->totalResource($resource, null, 'anonymous'),
            $analytics->totalResource($resource, null, 'identified')
        );
        $html .= '</li>';
        $html .= '<li>';
        $html .= sprintf(
            $translate('Position: %d (anonymous: %d / users: %d)'), // @translate
            $analytics->positionResource($resource),
            $analytics->positionResource($resource, null, 'anonymous'),
            $analytics->positionResource($resource, null, 'identified')
        );
        $html .= '</li>';
        $html .= '</ul>';
        return $html;
    }

    public function filterAdminDashboardPanels(Event $event): void
    {
        $view = $event->getTarget();
        $plugins = $view->getHelperPluginManager();
        $userIsAllowed = $plugins->get('userIsAllowed');

        $userIsAllowedSummary = $userIsAllowed('Statistics\Controller\Analytics', 'index');
        $userIsAllowedBrowse = $userIsAllowed('Statistics\Controller\Analytics', 'browse');
        if (!$userIsAllowedSummary && !$userIsAllowedBrowse) {
            return;
        }

        /**
         * @var \Statistics\View\Helper\Analytics $analytics
         */
        $services = $this->getServiceLocator();
        $url = $plugins->get('url');
        $api = $services->get('Omeka\ApiManager');
        $escape = $plugins->get('escapeHtml');
        $analytics = $plugins->get('analytics');
        $settings = $services->get('Omeka\Settings');
        $translate = $plugins->get('translate');
        $escapeAttr = $plugins->get('escapeHtmlAttr');

        $userStatus = $settings->get('statistics_default_user_status_admin');
        $totalHits = $api->search('hits', ['user_status' => $userStatus, 'limit' => 0])->getTotalResults();

        $statsTitle = $translate('Statistics'); // @translate
        $html = <<<HTML
<div id="stats" class="panel">
    <h2>$statsTitle</h2>

HTML;

        if ($userIsAllowedSummary) {
            $statsSummaryUrl = $url('admin/analytics', [], true);
            $statsSummaryText = sprintf($translate('Total Hits: %d'), $totalHits); // @translate
            $lastTexts = [
                30 => $translate('Last 30 days'),
                7 => $translate('Last 7 days'),
                1 => $translate('Last 24 hours'),
            ];
            $lastTotals = [
                30 => $api->search('hits', ['since' => date('Y-m-d', strtotime('-30 days')), 'user_status' => $userStatus, 'limit' => 0])->getTotalResults(),
                7 => $api->search('hits', ['since' => date('Y-m-d', strtotime('-7 days')), 'user_status' => $userStatus, 'limit' => 0])->getTotalResults(),
                1 => $api->search('hits', ['since' => date('Y-m-d', strtotime('-1 days')), 'user_status' => $userStatus, 'limit' => 0])->getTotalResults(),
            ];
            $html .= <<<HTML
    <h4><a href="$statsSummaryUrl">$statsSummaryText</a></h4>
    <ul>
        <li>$lastTexts[30] : $lastTotals[30]</li>
        <li>$lastTexts[7] : $lastTotals[7]</li>
        <li>$lastTexts[1] : $lastTotals[1]</li>
    </ul>

HTML;
        }

        if ($userIsAllowedBrowse) {
            $statsBrowseUrl = $url('admin/analytics/default', ['action' => 'by-page'], true);
            $statsBrowseText = $translate('Most viewed public pages'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            /** @var \Statistics\Api\Representation\StatRepresentation[] $stats */
            $stats = $analytics->mostViewedPages(null, $userStatus, 1, 5);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $html .= '<ol>';
                foreach ($stats as $stat) {
                    $html .= '<li>';
                    $html .= sprintf(
                        $translate('%s (%d views)'),
                        // $stat->getPositionPage(),
                        '<a href="' . $escapeAttr($stat->hitUrl()) . '">' . $escape($stat->hitUrl()) . '</a>',
                        $stat->totalHits($userStatus)
                    );
                    $html .= '</li>';
                }
                $html .= '</ol>';
            }

            $statsBrowseUrl = $url('admin/analytics/default', ['action' => 'by-resource'], true);
            $statsBrowseText = $translate('Most viewed public item'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            $stats = $analytics->mostViewedResources('items', $userStatus, 1, 5);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $stat = reset($stats);
                $html .= '<ul>';
                $html .= sprintf($translate('%s (%d views)'), // @translate
                    $stat->linkEntity(),
                    $stat->totalHits($userStatus)
                );
                $html .= '</ul>';
            }

            $statsBrowseUrl = $url('admin/analytics/default', ['action' => 'by-resource'], true);
            $statsBrowseText = $translate('Most viewed public item set'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            $stats = $analytics->mostViewedResources('item_sets', $userStatus, 1, 5);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $stat = reset($stats);
                $html .= '<ul>';
                $html .= sprintf($translate('%s (%d views)'), // @translate
                    $stat->linkEntity(),
                    $stat->totalHits($userStatus)
                );
                $html .= '</ul>';
            }

            $statsBrowseUrl = $url('admin/analytics/default', ['action' => 'by-download'], true);
            $statsBrowseText = $translate('Most downloaded file'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            $stats = $analytics->mostViewedDownloads($userStatus, 1, 1);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $stat = reset($stats);
                $html .= '<ul>';
                $html .= sprintf($translate('%s (%d downloads)'), // @translate
                    $stat->linkEntity(),
                    $stat->totalHits($userStatus)
                );
                $html .= '</ul>';
            }

            $statsBrowseUrl = $url('admin/analytics/default', ['action' => 'by-field'], true);
            $statsBrowseText = $translate('Most frequent fields'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            /** @var \Statistics\Api\Representation\StatRepresentation[] $results */
            foreach ([
                'referrer' => $translate('Referrer'), // @translate
                'query' => $translate('Query'), // @translate
                'user_agent' => $translate('User Agent'), // @translate
                // 'accept_language' => $translate('Full Accepted Language'), // @translate
                'language' => $translate('Accepted Language'), // @translate
            ] as $field => $label) {
                $results = $analytics->mostFrequents($field, $userStatus, 1, 1);
                $html .= '<li>';
                if (empty($results)) {
                    $html .= sprintf($translate('%s: None'), $label);
                } else {
                    $result = reset($results);
                    $html .= sprintf('%s: %s (%d%%)', sprintf('<a href="%s">%s</a>', $url('admin/analytics/default', ['action' => 'by-field'], true) . '?field=' . $field, $label), $result[$field], $result['hits'] * 100 / $totalHits);
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        echo $html;
    }

    public function handleBulkImportAfter(Event $event): void
    {
        /** @var \BulkImport\Processor\AbstractFullProcessor $processor */
        $processor = $event->getTarget();
        $toImport = $processor->getParam('types') ?: [];
        if (!in_array('hits', $toImport)) {
            return;
        }

        /** @var \Omeka\Mvc\Controller\Plugin\JobDispatcher $dispatcher */
        $services = $this->getServiceLocator();
        $strategy = $services->get(\Omeka\Job\DispatchStrategy\Synchronous::class);
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);

        $logger = $event->getParam('logger');
        $logger->notice('Update of aggregated statistics: Start'); // @translate

        $dispatcher->dispatch(\Statistics\Job\AggregateHits::class, [], $strategy);

        $logger->notice('Update of aggregated statistics: Ended.'); // @translate
    }
}
