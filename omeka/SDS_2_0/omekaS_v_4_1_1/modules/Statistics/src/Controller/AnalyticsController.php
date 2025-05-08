<?php declare(strict_types=1);

namespace Statistics\Controller;

use Doctrine\DBAL\Connection;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Stdlib\Message;
use Statistics\Entity\Stat;

/**
 * Controller to browse Analytics.
 */
class AnalyticsController extends AbstractActionController
{
    use StatisticsTrait;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $userStatus;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function indexAction()
    {
        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();
        $this->userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        $results = [];
        $time = time();

        $translate = $this->plugins->get('translate');

        $results['all'] = $this->analyticsPeriod();

        $results['today'] = $this->analyticsPeriod(strtotime('today'));

        $results['history'][$translate('Last year')] = $this->analyticsPeriod( // @translate
            strtotime('-1 year', strtotime(date('Y-1-1', $time))),
            strtotime(date('Y-1-1', $time) . ' - 1 second')
        );
        $results['history'][$translate('Last month')] = $this->analyticsPeriod( // @translate
            strtotime('-1 month', strtotime(date('Y-m-1', $time))),
            strtotime(date('Y-m-1', $time) . ' - 1 second')
        );
        $results['history'][$translate('Last week')] = $this->analyticsPeriod( // @translate
            strtotime("previous week"),
            strtotime("previous week + 6 days")
        );
        $results['history'][$translate('Yesterday')] = $this->analyticsPeriod( // @translate
            strtotime('-1 day', strtotime(date('Y-m-d', $time))),
            strtotime('-1 day', strtotime(date('Y-m-d', $time)))
        );

        $results['current'][$translate('This year')] = // @translate
        $this->analyticsPeriod(strtotime(date('Y-1-1', $time)));
        $results['current'][$translate('This month')] =  // @translate
        $this->analyticsPeriod(strtotime(date('Y-m-1', $time)));
        $results['current'][$translate('This week')] = // @translate
        $this->analyticsPeriod(strtotime('this week'));
        $results['current'][$translate('This day')] = // @translate
        $this->analyticsPeriod(strtotime('today'));

        foreach ([365 => null, 30 => null, 7 => null, 1 => null] as $start => $endPeriod) {
            $startPeriod = strtotime("- {$start} days");
            $label = ($start == 1)
                ? $translate('Last 24 hours') // @translate
                : sprintf($translate('Last %s days'), $start); // @translate
            $results['rolling'][$label] = $this->analyticsPeriod($startPeriod, $endPeriod);
        }

        if ($this->userIsAllowed('Statistics\Controller\Analytics', 'by-page')) {
            /** @var \Statistics\View\Helper\Analytics $analytics */
            $analytics = $this->viewHelpers()->get('analytics');
            $results['most_viewed_pages'] = $analytics->mostViewedPages(null, $this->userStatus, 1, 10);
            $results['most_viewed_resources'] = $analytics->mostViewedResources(null, $this->userStatus, 1, 10);
            $results['most_viewed_item_sets'] = $analytics->mostViewedResources('item_sets', $this->userStatus, 1, 10);
            $results['most_viewed_downloads'] = $analytics->mostViewedDownloads($this->userStatus, 1, 10);
            $results['most_frequent_fields']['referrer'] = $analytics->mostFrequents('referrer', $this->userStatus, 1, 10);
            $results['most_frequent_fields']['query'] = $analytics->mostFrequents('query', $this->userStatus, 1, 10);
            $results['most_frequent_fields']['user_agent'] = $analytics->mostFrequents('user_agent', $this->userStatus, 1, 10);
            // $results['most_frequent_fields']['accept_language'] = $analytics->mostFrequents('accept_language', $this->userStatus, 1, 10);
            $results['most_frequent_fields']['language'] = $analytics->mostFrequents('language', $this->userStatus, 1, 10);
            // Make json results utf-8.
            foreach ($results['most_frequent_fields']['query'] as &$val) {
                $val['query'] = json_encode(json_decode($val['query'], true), 320);
            }
            unset($val);
        }

        $view = new ViewModel([
            'results' => $results,
            'userStatus' => $this->userStatus,
        ]);

        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/index' : 'statistics/site/analytics/index');
    }

    /**
     * Redirect to the 'by-page' action.
     */
    public function browseAction()
    {
        $query = $this->params()->fromRoute();
        $query['action'] = 'by-page';
        $isSiteRequest = $this->status()->isSiteRequest();
        return $this->redirect()->toRoute($isSiteRequest ? 'site/analytics/default' : 'admin/analytics/default', $query);
    }

    public function bySiteAction()
    {
        // FIXME Stats by site has not been fully checked.
        // TODO Add a column "site_id" in table "stat".
        // TODO Factorize with byItemSetAction?
        // TODO Move the process into view helper Analytics.
        // TODO Enlarge byItemSet to byResource (since anything is resource).

        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();

        $userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        if ($userStatus === 'anonymous') {
            $whereStatus = "\nAND hit.user_id = 0";
        } elseif ($userStatus === 'identified') {
            $whereStatus = "\nAND hit.user_id <> 0";
        } else {
            $whereStatus = '';
        }

        $query = $this->params()->fromQuery();
        $year = empty($query['year']) || !is_numeric($query['year']) ? null : (int) $query['year'];
        $month = empty($query['month']) || !is_numeric($query['month']) ? null : (int) $query['month'];

        $appendDates = $this->whereDate($year, $month, [], []);
        $bind = $appendDates['bind'];
        $types = $appendDates['types'];
        $force = $appendDates['force'];
        $whereYear = $appendDates['whereYear'];
        $whereMonth = $appendDates['whereMonth'];

        $sql = <<<SQL
SELECT hit.site_id, COUNT(hit.id) AS total_hits
FROM hit hit $force
WHERE hit.entity_name = "items"$whereStatus$whereYear$whereMonth
GROUP BY hit.site_id
ORDER BY total_hits ASC
;
SQL;
        $hitsPerSite = $this->connection->executeQuery($sql, $bind, $types)->fetchAllKeyValue();

        $removedSite = $this->translate('[Removed site #%d]'); // @translate

        $api = $this->api();
        $results = [];
        foreach ($hitsPerSite as $siteId => $hits) {
            try {
                $siteTitle = $api->read('sites', ['id' => $siteId])->getContent()->title();
            } catch (\Exception $e) {
                $siteTitle = sprintf($removedSite, $siteId);
            }
            $results[] = [
                'site' => $siteTitle,
                'hits' => $hits,
                'hitsInclusive' => '',
            ];
        }

        $this->paginator(count($results));

        // TODO Manage special sort fields.
        $sortBy = $query['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, ['site', 'hits', 'hitsInclusive'])) {
            $sortBy = 'hitsInclusive';
        }
        $sortOrder = isset($query['sort_order']) && strtolower($query['sort_order']) === 'asc' ? 'asc' : 'desc';

        usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
            $cmp = strnatcasecmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
            return $sortOrder === 'desc' ? -$cmp : $cmp;
        });

        $years = $this->listYears('hit', null, null, false);

        $view = new ViewModel([
            'type' => 'site',
            'results' => $results,
            'years' => $years,
            'yearFilter' => $year,
            'monthFilter' => $month,
        ]);
        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/by-site' : 'statistics/site/analytics/by-site');
    }

    /**
     * Browse rows by page action.
     */
    public function byPageAction()
    {
        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();

        $userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $query = $this->params()->fromQuery();
        $query['type'] = Stat::TYPE_PAGE;
        $query['user_status'] = $userStatus;

        $response = $this->api()->search('stats', $query);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
            'type' => Stat::TYPE_PAGE,
        ]);
        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/by-stat' : 'statistics/site/analytics/by-stat');
    }

    /**
     * Browse rows by resource action.
     */
    public function byResourceAction()
    {
        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();

        $userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $query = $this->params()->fromQuery();
        $query['type'] = Stat::TYPE_RESOURCE;
        $query['user_status'] = $userStatus;

        $response = $this->api()->search('stats', $query);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
            'type' => Stat::TYPE_RESOURCE,
        ]);
        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/by-stat' : 'statistics/site/analytics/by-stat');
    }

    /**
     * Browse rows by download action.
     */
    public function byDownloadAction()
    {
        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();

        $userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $query = $this->params()->fromQuery();
        $query['type'] = Stat::TYPE_DOWNLOAD;
        $query['user_status'] = $userStatus;

        $response = $this->api()->search('stats', $query);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
            'type' => Stat::TYPE_DOWNLOAD,
        ]);
        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/by-stat' : 'statistics/site/analytics/by-stat');
    }

    /**
     * Browse rows by field action.
     */
    public function byFieldAction()
    {
        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();

        $userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        $query = $this->params()->fromQuery();

        $fields = [
            'referrer',
            'query',
            'user_agent',
            'accept_language',
            'language',
        ];

        $field = $query['field'] ?? null;
        if (empty($field) || !in_array($field, $fields)) {
            $field = 'referrer';
            $query['field'] = $field;
        }

        $query = $this->defaultSort($query, [$field, 'hits'], 'hits');

        $currentPage = isset($query['page']) ? (int) $query['page'] : null;
        $resourcesPerPage = $isAdminRequest
            ? (int) $settings->get('pagination_per_page', 25)
            : (int) $this->siteSettings()->get('pagination_per_page', 25);

        // Don't use api, because this is a synthesis, not a list of resources.
        /** @var \Statistics\View\Helper\Analytics $analytics */
        $analytics = $this->viewHelpers()->get('analytics');
        $results = $analytics->frequents($query, $currentPage, $resourcesPerPage);
        $totalResults = $analytics->countFrequents($query);
        $totalHits = $this->api()->search('hits', ['user_status' => $userStatus, 'limit' => 0])->getTotalResults();
        $totalNotEmpty = $this->api()->search('hits', ['field' => $field, 'user_status' => $userStatus, 'not_empty' => $field, 'limit' => 0])->getTotalResults();
        $this->paginator($totalResults);

        switch ($field) {
            default:
            case 'referrer':
                $labelField = $this->translate('External Referrers'); // @translate
                break;
            case 'query':
                $labelField = $this->translate('Queries'); // @translate
                break;
            case 'user_agent':
                $labelField = $this->translate('Browsers'); // @translate
                break;
            case 'accept_language':
                $labelField = $this->translate('Full Accepted Languages'); // @translate
                break;
            case 'language':
                $labelField = $this->translate('Accepted Languages'); // @translate
                break;
        }

        $view = new ViewModel([
            'type' => 'field',
            'field' => $field,
            'labelField' => $labelField,
            'results' => $results,
            'totalHits' => $totalHits,
            'totalNotEmpty' => $totalNotEmpty,
            'userStatus' => $userStatus,
        ]);
        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/by-field' : 'statistics/site/analytics/by-field');
    }

    public function byItemSetAction()
    {
        // FIXME Stats by item set has not been fully checked.
        // TODO Move the process into view helper Analytics.
        // TODO Enlarge byItemSet to byResource (since anything is resource).

        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();

        $userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        if ($userStatus === 'anonymous') {
            $whereStatus = "\nAND hit.user_id = 0";
        } elseif ($userStatus === 'identified') {
            $whereStatus = "\nAND hit.user_id <> 0";
        } else {
            $whereStatus = '';
        }

        $query = $this->params()->fromQuery();
        $year = empty($query['year']) || !is_numeric($query['year']) ? null : (int) $query['year'];
        $month = empty($query['month']) || !is_numeric($query['month']) ? null : (int) $query['month'];

        $appendDates = $this->whereDate($year, $month, [], []);
        $bind = $appendDates['bind'];
        $types = $appendDates['types'];
        $force = $appendDates['force'];
        $whereYear = $appendDates['whereYear'];
        $whereMonth = $appendDates['whereMonth'];

        $sql = <<<SQL
SELECT item_item_set.item_set_id, COUNT(hit.id) AS total_hits
FROM hit hit $force
JOIN item_item_set ON hit.entity_id = item_item_set.item_id
WHERE hit.entity_name = "items"$whereStatus$whereYear$whereMonth
GROUP BY item_item_set.item_set_id
ORDER BY total_hits ASC
;
SQL;
        $hitsPerItemSet = $this->connection->executeQuery($sql, $bind, $types)->fetchAllKeyValue();

        $removedItemSet = $this->translate('[Removed item set #%d]'); // @translate

        $api = $this->api();
        $results = [];
        // TODO Check and integrate statistics for item set tree (with performance).
        if (false && $this->plugins()->has('itemSetsTree')) {
            $itemSetIds = $api->search('item_sets', [], ['returnScalar', 'id'])->getContent();
            foreach ($itemSetIds as $itemSetId) {
                $hitsInclusive = $this->getHitsPerItemSet($hitsPerItemSet, $itemSetId);
                if ($hitsInclusive > 0) {
                    try {
                        $itemSetTitle = $api->read('item_sets', ['id' => $itemSetId])->getContent()->displayTitle();
                    } catch (\Exception $e) {
                        $itemSetTitle = sprintf($removedItemSet, $itemSetId);
                    }
                    $results[] = [
                        'item-set' => $itemSetTitle,
                        'hits' => $hitsPerItemSet[$itemSetId] ?? 0,
                        'hitsInclusive' => $hitsInclusive,
                    ];
                }
            }
        } else {
            foreach ($hitsPerItemSet as $itemSetId => $hits) {
                try {
                    $itemSetTitle = $api->read('item_sets', ['id' => $itemSetId])->getContent()->displayTitle();
                } catch (\Exception $e) {
                    $itemSetTitle = sprintf($removedItemSet, $itemSetId);
                }
                $results[] = [
                    'item-set' => $itemSetTitle,
                    'hits' => $hits,
                    'hitsInclusive' => '',
                ];
            }
        }

        $this->paginator(count($results));

        // TODO Manage special sort fields.
        $sortBy = $query['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, ['itemSet', 'hits', 'hitsInclusive'])) {
            $sortBy = 'hitsInclusive';
        }
        $sortOrder = isset($query['sort_order']) && strtolower($query['sort_order']) === 'asc' ? 'asc' : 'desc';

        usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
            $cmp = strnatcasecmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
            return $sortOrder === 'desc' ? -$cmp : $cmp;
        });

        $years = $this->listYears('hit', null, null, false);

        $view = new ViewModel([
            'type' => 'item-set',
            'results' => $results,
            'years' => $years,
            'yearFilter' => $year,
            'monthFilter' => $month,
        ]);
        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/by-item-set' : 'statistics/site/analytics/by-item-set');
    }

    public function byValueAction()
    {
        // FIXME Stats by value has not been fully checked.
        // TODO Move the process into view helper Analytics.
        // TODO Enlarge byItemSet to byResource (since anything is resource).
        // TODO Here, only items are analyzed.

        $isAdminRequest = $this->status()->isAdminRequest();
        $settings = $this->settings();

        $userStatus = $isAdminRequest
            ? $settings->get('statistics_default_user_status_admin')
            : $settings->get('statistics_default_user_status_public');

        if ($userStatus === 'anonymous') {
            $whereStatus = "\nAND hit.user_id = 0";
        } elseif ($userStatus === 'identified') {
            $whereStatus = "\nAND hit.user_id <> 0";
        } else {
            $whereStatus = '';
        }

        $query = $this->params()->fromQuery();
        $year = empty($query['year']) || !is_numeric($query['year']) ? null : (int) $query['year'];
        $month = empty($query['month']) || !is_numeric($query['month']) ? null : (int) $query['month'];
        $property = $query['property'] ?? null;
        $typeFilter = $query['value_type'] ?? null;
        $byPeriodFilter = isset($query['by_period']) && in_array($query['by_period'], ['year', 'month']) ? $query['by_period'] : 'all';

        if ($property && $propertyId = $this->getPropertyId($property)) {
            if (is_numeric($property)) {
                $property = $this->getPropertyId([$propertyId]);
                $property = key($property);
            }
            $joinProperty = ' AND property_id = :property_id';
            $bind['property_id'] = $propertyId;
            $types['property_id'] = \Doctrine\DBAL\ParameterType::INTEGER;
        } else {
            $property = null;
            if ($query) {
                $this->messenger()->addError(new Message('A property is required to get statistics.')); // @translate
            }
        }

        switch ($byPeriodFilter) {
            case 'year':
                $periods = $this->listYears('hit', $year, $year, true);
                break;
            case 'month':
                if ($year && $month) {
                    $periods = $this->listYearMonths('hit', (int) sprintf('%04d%02d', $year, $month), (int) sprintf('%04d%02d', $year, $month), true);
                } elseif ($year) {
                    $periods = $this->listYearMonths('hit', (int) sprintf('%04d01', $year), (int) sprintf('%04d12', $year), true);
                } elseif ($month) {
                    $periods = null;
                    $this->messenger()->addError(new Message('A year is required to get details by month.')); // @translate
                } else {
                    $periods = $this->listYearMonths('hit', null, null, true);
                }
                break;
            case 'all':
            default:
                $periods = [];
                break;
        }

        $view = new ViewModel([
            'type' => 'value',
            'results' => [],
            'years' => $this->listYears('hit', null, null, true),
            'periods' => $periods,
            'yearFilter' => $year,
            'monthFilter' => $month,
            'propertyFilter' => $property,
            'valueTypeFilter' => $typeFilter,
            'byPeriodFilter' => $byPeriodFilter,
        ]);
        $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/analytics/by-value' : 'statistics/site/analytics/by-value');

        if (is_null($periods) || !$property) {
            return $view;
        }

        // TODO There is no pagination currently in stats by value.

        // TODO Manage special sort fields.
        $sortBy = $query['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, ['value', 'hits', 'hitsInclusive'])) {
            $sortBy = 'hitsInclusive';
        }
        $sortOrder = isset($query['sort_order']) && strtolower($query['sort_order']) === 'asc' ? 'asc' : 'desc';

        // TODO Add a type filter for all, or no type filter.
        switch ($typeFilter) {
            default:
            case 'value':
                $joinResource = '';
                $selectValue = 'value.value AS "value", "" AS "label"';
                $typeFilterValue = 'value.value';
                $whereFilterValue = "\nAND value.value IS NOT NULL\nAND value.value <> ''\nAND (value.uri IS NULL OR value.uri = '')\nAND (value.value_resource_id IS NULL OR value.value_resource_id = '')";
                break;
            case 'resource':
                $joinResource = "\nLEFT JOIN resource ON resource.id = value.value_resource_id";
                $selectValue = 'value.value_resource_id AS "value", resource.title AS "label"';
                $typeFilterValue = 'value.value_resource_id';
                $whereFilterValue = "\nAND value.value_resource_id IS NOT NULL\nAND value.value_resource_id <> 0\nAND (value.value IS NULL OR value.value= '')\nAND (value.uri IS NULL OR value.uri = '')";
                break;
            case 'uri':
                $joinResource = '';
                $selectValue = 'value.uri AS "value", value.value AS "label"';
                $typeFilterValue = 'value.uri';
                $whereFilterValue = "\nAND value.uri IS NOT NULL\nAND value.uri <> ''\nAND (value.value_resource_id IS NULL OR value.value_resource_id = '')";
                break;
        }

        // FIXME The results are doubled when the property has duplicate values for a resource, so fix it or warn about deduplicating values regularly (module BulkEdit).

        $baseBind = $bind;
        $baseTypes = $types;
        if ($periods) {
            // TODO Use a single query instead of a loop, so use periods as columns.
            $results = [];
            foreach ($periods as $period => $isEmpty) {
                if (empty($isEmpty)) {
                    $results[$period] = [];
                    continue;
                }

                // TODO Manage "force index" via query builder.
                // $qb = $this->connection->createQueryBuilder();
                if ($byPeriodFilter === 'year') {
                    $yearPeriod = $period;
                    $monthPeriod = null;
                } else {
                    $yearPeriod = substr((string) $period, 0, 4);
                    $monthPeriod = substr((string) $period, 4, 2);
                }
                $appendDates = $this->whereDate($yearPeriod, $monthPeriod, $baseBind, $baseTypes);
                $bind = $appendDates['bind'];
                $types = $appendDates['types'];
                $force = $appendDates['force'];
                $whereYear = $appendDates['whereYear'];
                $whereMonth = $appendDates['whereMonth'];

                $sql = <<<SQL
SELECT $selectValue, COUNT(hit.id) AS hits, "" AS hitsInclusive
FROM hit hit $force
JOIN value ON hit.entity_id = value.resource_id$joinProperty$joinResource
WHERE hit.entity_name = "items"$whereStatus$whereYear$whereMonth$whereFilterValue
GROUP BY $typeFilterValue
ORDER BY hits DESC ASC
;
SQL;
                $result = $this->connection->executeQuery($sql, $bind, $types)->fetchAllAssociative();

                usort($result, function ($a, $b) use ($sortBy, $sortOrder) {
                    $cmp = strnatcasecmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                });

                $results[$period] = $result;
            }

            $hasValueLabel = in_array($typeFilter, ['resource', 'uri']);
            $results = $this->mergeResultsByValue($results, $hasValueLabel);
        } else {
            // TODO Manage "force index" via query builder.
            // $qb = $this->connection->createQueryBuilder();
            $appendDates = $this->whereDate($year, $month, $baseBind, $baseTypes);
            $bind = $appendDates['bind'];
            $types = $appendDates['types'];
            $force = $appendDates['force'];
            $whereYear = $appendDates['whereYear'];
            $whereMonth = $appendDates['whereMonth'];

            $sql = <<<SQL
SELECT $selectValue, COUNT(hit.id) AS hits, "" AS hitsInclusive
FROM hit hit$force
JOIN value ON hit.entity_id = value.resource_id$joinProperty$joinResource
WHERE hit.entity_name = "items"$whereStatus$whereYear$whereMonth$whereFilterValue
GROUP BY $typeFilterValue
ORDER BY hits DESC ASC
;
SQL;
            $results = $this->connection->executeQuery($sql, $bind, $types)->fetchAllAssociative();

            // TODO Reinclude sort order inside sql.
            usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
                $cmp = strnatcasecmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });

            // TODO Use the same format for all queries to simplify view.
            // $results['all'] = $results;
        }

        // TODO There is no pagination currently in stats by value.
        $this->paginator(count($results));

        $view->setVariable('results', $results);
        return $view;
    }

    /**
     * Helper to get all stats of a period.
     *
     * @todo Move the view helper Analytics.
     *
     * @param int $startPeriod Number of days before today (default is all).
     * @param int $endPeriod Number of days before today (default is now).
     * @return array
     */
    protected function analyticsPeriod(?int $startPeriod = null, ?int $endPeriod = null): array
    {
        $query = [];
        if ($startPeriod) {
            $query['since'] = date('Y-m-d 00:00:00', $startPeriod);
        }
        if ($endPeriod) {
            $query['until'] = date('Y-m-d 23:59:59', $endPeriod);
        }

        // Speed the computation for count via api.
        $query['limit'] = 0;

        $api = $this->api();
        if ($this->status()->isAdminRequest()) {
            // TODO Use a single query (see version for Omeka Classic).
            $query['user_status'] = 'anonymous';
            $anonymous = $api->search('hits', $query, ['initialize' => false, 'finalize' => false])->getTotalResults();
            $query['user_status'] = 'identified';
            $identified = $api->search('hits', $query, ['initialize' => false, 'finalize' => false])->getTotalResults();
            return [
                'anonymous' => $anonymous,
                'identified' => $identified,
                'total' => $anonymous + $identified,
            ];
        }

        $query['user_status'] = $this->userStatus ?: 'hits';
        return [
            'total' => $api->search('hits', $query, ['initialize' => false, 'finalize' => false])->getTotalResults(),
        ];
    }

    /**
     * @fixme Finalize integration of item set tree.
     */
    protected function getHitsByItemSet($hitsPerItemSet, $itemSetId): int
    {
        $childrenHits = 0;
        $childItemSetIds = $this->api()->search('item_sets_tree_edge', [], ['returnScalar' => 'id'])->getChildCollections($itemSetId);
        foreach ($childItemSetIds as $childItemSetId) {
            $childrenHits += $this->getHitsPerItemSet($hitsPerItemSet, $childItemSetId);
        }
        return ($hitsPerItemSet[$itemSetId] ?? 0) + $childrenHits;
    }

    protected function defaultSort(array $query, array $allowedSorts = [], string $defaultSort = 'hits'): array
    {
        $sortBy = $query['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, $allowedSorts)) {
            $query['sort_by'] = $defaultSort;
        }
        $sortOrder = $query['sort_order'] ?? null;
        if (empty($sortOrder) || !in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $query['sort_order'] = 'desc';
        }
        return $query;
    }

    /**
     * Get the results for all values for all periods, so fill empty hits.
     */
    protected function mergeResultsByValue(array $results, bool $hasValueLabel = false): array
    {
        // Each result by period contains value, label, hits, inclusive hits.
        // The table of values allows to sort hits by totals directly.
        $valuesMaxCounts = [];
        // This value is the merged results.
        $valuesByPeriod = [];
        foreach ($results as $period => $periodResults) {
            foreach ($periodResults as $result) {
                $v = $result['value'];
                if ($hasValueLabel) {
                    $valuesByPeriod[$v]['label'] = $result['label'];
                }
                $valuesByPeriod[$v]['hits'][$period] = $result['hits'];
                $valuesMaxCounts[$v] = isset($valuesMaxCounts[$v]) ? max($valuesMaxCounts[$v], $result['hits']) : $result['hits'];
            }
        }
        asort($valuesMaxCounts);
        $valuesMaxCounts = array_reverse($valuesMaxCounts, true);
        return array_replace($valuesMaxCounts, $valuesByPeriod);
    }

    /**
     * Prepare a query to filter by date.
     *
     * Require doctrine DBAL, not ORM, that does no support "extract".
     */
    protected function whereDate($year = null, $month = null, array $bind = [], array $types = []): array
    {
        if ($year || $month) {
            // This is the doctrine hashed name index for the column "created".
            $force = ' FORCE INDEX FOR JOIN (`IDX_5AD22641B23DB7B8`)';
            if ($year && $month) {
                $whereYear = "\nAND EXTRACT(YEAR_MONTH FROM hit.created) = :year_month";
                $bind['year_month'] = sprintf('%04d%02d', $year, $month);
                $types['year_month'] = \Doctrine\DBAL\ParameterType::INTEGER;
            } elseif ($year) {
                $whereYear = "\nAND EXTRACT(YEAR FROM hit.created) = :year";
                $bind['year'] = $year;
                $types['year'] = \Doctrine\DBAL\ParameterType::INTEGER;
            } elseif ($month) {
                $whereMonth = "\nAND EXTRACT(MONTH FROM hit.created) = :month";
                $bind['month'] = $month;
                $types['month'] = \Doctrine\DBAL\ParameterType::INTEGER;
            }
        }
        return [
            'bind' => $bind,
            'types' => $types,
            'force' => $force ?? '',
            'whereYear' => $whereYear ?? '',
            'whereMonth' => $whereMonth ?? '',
        ];
    }
}
