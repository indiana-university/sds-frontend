<?php declare(strict_types=1);

namespace Statistics\Controller;

require_once __DIR__ . '/../../vendor/autoload.php';

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Stdlib\Message;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

/**
 * Controller to browse Statistics.
 *
 * Statistics are mainly standard search requests with date interval.
 * To search a date interval requires module AdvancedSearch.
 *
 * @todo Many processes can be improved or moved to sql.
 */
class StatisticsController extends AbstractActionController
{
    use StatisticsTrait;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Omeka\Api\Adapter\Manager
     */
    protected $adapterManager;

    /**
     * @var bool
     */
    protected $hasAccess;

    /**
     * @var bool
     */
    protected $hasAdvancedSearch;

    public function __construct(
        Connection $connection,
        AdapterManager $adapterManager,
        bool $hasAccess,
        bool $hasAdvancedSearch
    ) {
        $this->connection = $connection;
        $this->adapterManager = $adapterManager;
        $this->hasAccess = $hasAccess;
        $this->hasAdvancedSearch = $hasAdvancedSearch;
    }

    public function indexAction()
    {
        $isAdminRequest = $this->status()->isAdminRequest();

        $results = [];
        $time = time();

        $resourceTypes = ['resources', 'item_sets', 'items', 'media'];

        $results['all'] = $this->statisticsPeriod(null, null, [], 'created', $resourceTypes);

        if (!$this->hasAdvancedSearch) {
            $view = new ViewModel([
                'results' => $results,
                'hasAccess' => $this->hasAccess,
                'hasAdvancedSearch' => $this->hasAdvancedSearch,
            ]);
            return $view
                ->setTemplate($isAdminRequest ? 'statistics/admin/statistics/index' : 'statistics/site/statistics/index');
        }

        $translate = $this->plugins->get('translate');

        $results['today'] = $this->statisticsPeriod(strtotime('today'), null, [], 'created', $resourceTypes);

        $results['history'][$translate('Last year')] = $this->statisticsPeriod( // @translate
            strtotime('-1 year', strtotime(date('Y-1-1', $time))),
            strtotime(date('Y-1-1', $time) . ' - 1 second'),
            [],
            'created',
            $resourceTypes
        );
        $results['history'][$translate('Last month')] = $this->statisticsPeriod( // @translate
            strtotime('-1 month', strtotime(date('Y-m-1', $time))),
            strtotime(date('Y-m-1', $time) . ' - 1 second'),
            [],
            'created',
            $resourceTypes
        );
        $results['history'][$translate('Last week')] = $this->statisticsPeriod( // @translate
            strtotime("previous week"),
            strtotime("previous week + 6 days"),
            [],
            'created',
            $resourceTypes
        );
        $results['history'][$translate('Yesterday')] = $this->statisticsPeriod( // @translate
            strtotime('-1 day', strtotime(date('Y-m-d', $time))),
            strtotime('-1 day', strtotime(date('Y-m-d', $time))),
            [],
            'created',
            $resourceTypes
        );

        $results['current'][$translate('This year')] = // @translate
            $this->statisticsPeriod(strtotime(date('Y-1-1', $time)), null, [], 'created', $resourceTypes);
        $results['current'][$translate('This month')] = // @translate
            $this->statisticsPeriod(strtotime(date('Y-m-1', $time)), null, [], 'created', $resourceTypes);
        $results['current'][$translate('This week')] = // @translate
            $this->statisticsPeriod(strtotime('this week'), null, [], 'created', $resourceTypes);
        $results['current'][$translate('This day')] = // @translate
            $this->statisticsPeriod(strtotime('today'), null, [], 'created', $resourceTypes);

        foreach ([365 => null, 30 => null, 7 => null, 1 => null] as $start => $endPeriod) {
            $startPeriod = strtotime("- {$start} days");
            $label = ($start == 1)
                ? $translate('Last 24 hours') // @translate
                : sprintf($translate('Last %s days'), $start); // @translate
            $results['rolling'][$label] = $this->statisticsPeriod($startPeriod, $endPeriod, [], 'created', $resourceTypes);
        }

        $view = new ViewModel([
            'results' => $results,
            'hasAccess' => $this->hasAccess,
            'hasAdvancedSearch' => $this->hasAdvancedSearch,
        ]);

        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/statistics/index' : 'statistics/site/statistics/index');
    }

    /**
     * Redirect to the "index" action.
     */
    protected function redirectToIndex()
    {
        $query = $this->params()->fromRoute();
        $query['action'] = 'index';
        $isSiteRequest = $this->status()->isSiteRequest();
        return $this->redirect()->toRoute($isSiteRequest ? 'site/statistics/default' : 'admin/statistics/default', $query);
    }

    public function browseAction()
    {
        return $this->redirectToIndex();
    }

    public function bySiteAction()
    {
        if (!$this->hasAdvancedSearch) {
            return $this->redirectToIndex();
        }

        $isAdminRequest = $this->status()->isAdminRequest();

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        $sites = $api->search('sites', [], ['initialize' => false, 'finalize' => false, 'returnScalar' => 'title'])->getContent();

        $query = $this->params()->fromQuery();
        $baseQuery = $query;

        $resourceTypes = empty($query['resource_type']) ? ['items'] : (is_array($query['resource_type']) ? $query['resource_type'] : [$query['resource_type']]);
        $resourceTypes = array_intersect(['resources', 'item_sets', 'items', 'media'], $resourceTypes) ?: ['items'];
        $year = empty($query['year']) || !is_numeric($query['year']) ? null : (int) $query['year'];
        $month = empty($query['month']) || !is_numeric($query['month']) ? null : (int) $query['month'];

        $results = [];
        foreach ($sites as $siteId => $title) {
            $query = $baseQuery;
            $query['site_id'] = $siteId;
            $results[$siteId]['label'] = $title;
            $results[$siteId]['count'] = $this->statisticsPeriod($year, $month, $query, 'created', $resourceTypes);
        }

        // TODO There is no pagination currently in stats by value.
        $this->paginator(count($results));

        // TODO Manage special sort fields.
        $sortBy = $query['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, ['site', 'resources', 'item_sets', 'items', 'media'])) {
            $sortBy = 'total';
        }
        $sortOrder = isset($query['sort_order']) && strtolower($query['sort_order']) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'site') {
            $sortBy = 'label';
            usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
                $cmp = ($a[$sortBy] ?? null) <=> ($b[$sortBy] ?? null);
                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        } elseif (in_array($sortBy, ['total', 'resources', 'item_sets', 'items', 'media'])) {
            if ($sortBy === 'total') {
                $sortBy = reset($resourceTypes);
            }
            usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
                $cmp = ($a['count'][$sortBy] ?? null) <=> ($b['count'][$sortBy] ?? null);
                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        }

        $view = new ViewModel([
            'type' => 'site',
            'results' => $results,
            'resourceTypes' => $resourceTypes,
            'years' => $this->listYears('resource', null, null, false),
            'yearFilter' => $year,
            'monthFilter' => $month,
            'hasAccess' => $this->hasAccess,
            'hasAdvancedSearch' => $this->hasAdvancedSearch,
        ]);
        $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/statistics/by-site' : 'statistics/site/statistics/by-site');

        $output = $this->params()->fromRoute('output');
        if ($output) {
            return $this->exportResults($view->getVariables(), $output) ?: $view;
        }

        return $view;
    }

    public function byValueAction()
    {
        if (!$this->hasAdvancedSearch) {
            return $this->redirectToIndex();
        }

        $isAdminRequest = $this->status()->isAdminRequest();

        $query = $this->params()->fromQuery();

        $resourceTypes = empty($query['resource_type']) ? ['items'] : (is_array($query['resource_type']) ? $query['resource_type'] : [$query['resource_type']]);
        $resourceTypes = array_intersect(['resources', 'item_sets', 'items', 'media'], $resourceTypes) ?: ['items'];
        $originalResourceTypes = $resourceTypes;
        $year = empty($query['year']) || !is_numeric($query['year']) ? null : (int) $query['year'];
        $month = empty($query['month']) || !is_numeric($query['month']) ? null : (int) $query['month'];
        $property = $query['property'] ?? null;
        $typeFilter = $query['value_type'] ?? null;
        $byPeriodFilter = isset($query['by_period']) && in_array($query['by_period'], ['year', 'month']) ? $query['by_period'] : 'all';
        $compute = isset($query['compute']) && in_array($query['compute'], ['percent', 'evolution', 'variation']) ? $query['compute'] : 'count';
        $sortBy = isset($query['sort_by']) && in_array($query['sort_by'], ['value', 'resources', 'item_sets', 'items', 'media']) ? $query['sort_by'] : 'total';
        $sortOrder = isset($query['sort_order']) && strtolower($query['sort_order']) === 'asc' ? 'asc' : 'desc';

        $isMetadata = in_array($typeFilter, ['resource_class', 'resource_template', 'owner']);

        // A property is required to get stats, so get empty without a good one.
        $propertyId = null;
        if ($property && $propertyId = $this->getPropertyId($property)) {
            if (is_numeric($property)) {
                $property = $this->getPropertyId([$propertyId]);
                $property = key($property);
            }
        } elseif (!$isMetadata) {
            $property = null;
            if ($query) {
                $this->messenger()->addError(new Message('A property is required to get statistics.')); // @translate
            }
        }

        switch ($byPeriodFilter) {
            case 'year':
                $periods = $this->listYears('resource', $year, $year, true);
                break;
            case 'month':
                if ($year && $month) {
                    $periods = $this->listYearMonths('resource', (int) sprintf('%04d%02d', $year, $month), (int) sprintf('%04d%02d', $year, $month), true);
                } elseif ($year) {
                    $periods = $this->listYearMonths('resource', (int) sprintf('%04d01', $year), (int) sprintf('%04d12', $year), true);
                } elseif ($month) {
                    $periods = null;
                    $this->messenger()->addError(new Message('A year is required to get details by month.')); // @translate
                } else {
                    $periods = $this->listYearMonths('resource', null, null, true);
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
            'totals' => [],
            'resourceTypes' => $resourceTypes,
            'years' => $this->listYears('resource', null, null, true),
            'periods' => $periods,
            'yearFilter' => $year,
            'monthFilter' => $month,
            'propertyFilter' => $property,
            'valueTypeFilter' => $typeFilter,
            'byPeriodFilter' => $byPeriodFilter,
            'compute' => $compute,
            'hasAccess' => $this->hasAccess,
            'hasAdvancedSearch' => $this->hasAdvancedSearch,
        ]);
        $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/statistics/by-value' : 'statistics/site/statistics/by-value');

        if (is_null($periods)
            || (!$property && !$isMetadata)
        ) {
            return $view;
        }

        // "resources" is not searchable currently, pull request #1799 is not merged.
        $isOnlyResources = array_values($resourceTypes) === ['resources'];
        $hasResources = array_search('resources', $resourceTypes);
        if ($isOnlyResources) {
            $resourceTypes = ['item_sets', 'items', 'media'];
        } elseif ($hasResources !== false) {
            unset($resourceTypes[$hasResources]);
            $hasResources = true;
        }

        // $baseQuery = $query;

        /** @see \Omeka\Api\Adapter\AbstractEntityAdapter::search() */
        // Set default query parameters
        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = $sortOrder;

        if ($property) {
            $query['property'] = [[
                'property' => $query['property'],
                'type' => 'ex',
            ]];
        }

        $results = [];

        // Resource type "resources" is not included here for now.
        foreach ($resourceTypes as $resourceType) {
            /** @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter */
            $adapter = $this->adapterManager->get($resourceType);
            $request = new \Omeka\Api\Request('search', $resourceType);
            $request
                ->setContent($query)
                ->setOption(['initialize' => false, 'finalize' => false]);

            // Begin building the search query.
            /** @see \Omeka\Api\Adapter\AbstractEntityAdapter::search() */
            $entityClass = $adapter->getEntityClass();
            $qb = $adapter->getEntityManager()
                ->createQueryBuilder()
                ->select('omeka_root')
                ->from($entityClass, 'omeka_root');
            $adapter->buildBaseQuery($qb, $query);
            $adapter->buildQuery($qb, $query);
            // $qb->groupBy('omeka_root.id');
            $adapter->limitQuery($qb, $query);

            // The query should be overridden manually because event "api.search.pre"
            // is not triggered.
            /** @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::startOverrideQuery() */
            $this->searchResources()
                ->startOverrideRequest($request);

            // Trigger the search.query event (required for Advanced Search).
            $event = new \Laminas\EventManager\Event('api.search.query', $adapter, [
                'queryBuilder' => $qb,
                'request' => $request,
            ]);
            $adapter->getEventManager()->triggerEvent($event);

            $expr = $qb->expr();

            // Set custom parameters.
            if ($propertyId) {
                // TODO Probably useless now, since property is included cleanly in the query.
                $qb
                    // Use class, it is orm qb.
                    ->innerJoin(\Omeka\Entity\Value::class, 'value', Join::WITH, 'value.resource = omeka_root AND value.property = ' . $propertyId);
            }

            // TODO Add a type filter for all, or no type filter.
            $hasEmpty = false;
            switch ($typeFilter) {
                default:
                case 'value':
                    $qb
                        // Not possible to select an empty string for label with orm qb, so select the uri, that is null.
                        ->select('value.value AS v', 'MIN(IDENTITY(value.valueResource)) AS l', 'COUNT(omeka_root.id) AS t')
                        ->groupBy('value.value')
                        ->where($expr->andX(
                            $expr->isNotNull('value.value'),
                            $expr->neq('value.value', ':empty_string'),
                            $expr->orX($expr->isNull('value.uri'), $expr->eq('value.uri', ':empty_string')),
                            $expr->orX($expr->isNull('value.valueResource'), $expr->eq('value.valueResource', ':empty_int'))
                        ))
                    ;
                    $hasEmpty = true;
                    break;

                case 'resource':
                    $qb
                        ->select('IDENTITY(value.valueResource) AS v', 'res.title AS l', 'COUNT(omeka_root.id) AS t')
                        ->leftJoin(\Omeka\Entity\Resource::class, 'res', Join::WITH, 'res = value.valueResource')
                        ->groupBy('value.valueResource')
                        ->where($expr->andX(
                            $expr->isNotNull('value.valueResource'),
                            $expr->neq('value.valueResource', ':empty_int'),
                            $expr->orX($expr->isNull('value.value'), $expr->eq('value.value', ':empty_string')),
                            $expr->orX($expr->isNull('value.uri'), $expr->eq('value.value', ':empty_string'))
                        ))
                    ;
                    $hasEmpty = true;
                    break;

                case 'uri':
                    $qb
                        ->select('value.uri AS v', 'value.label AS l', 'COUNT(omeka_root.id) AS t')
                        ->groupBy('value.uri')
                        ->where($expr->andX(
                            $expr->isNotNull('value.uri'),
                            $expr->neq('value.uri', ':empty_string'),
                            $expr->orX($expr->isNull('value.valueResource'), $expr->eq('value.valueResource', ':empty_int'))
                        ))
                    ;
                    $hasEmpty = true;
                    break;

                case 'resource_class':
                    $qb
                        ->select('IDENTITY(omeka_root.resourceClass) AS v', 'resource_class.localName AS l', 'COUNT(omeka_root.id) AS t')
                        ->leftJoin(\Omeka\Entity\ResourceClass::class, 'resource_class', Join::WITH, 'omeka_root.resourceClass = resource_class')
                        ->groupBy('omeka_root.resourceClass')
                    ;
                    break;

                case 'resource_template':
                    $qb
                        ->select('IDENTITY(omeka_root.resourceTemplate) AS v', 'resource_template.label AS l', 'COUNT(omeka_root.id) AS t')
                        ->leftJoin(\Omeka\Entity\ResourceTemplate::class, 'resource_template', Join::WITH, 'omeka_root.resourceTemplate = resource_template')
                        ->groupBy('omeka_root.resourceTemplate')
                    ;
                    break;

                case 'owner':
                    $qb
                        ->select('IDENTITY(omeka_root.owner) AS v', 'user.name AS l', 'COUNT(omeka_root.id) AS t')
                        ->leftJoin(\Omeka\Entity\User::class, 'user', Join::WITH, 'omeka_root.owner = user')
                        ->groupBy('omeka_root.owner')
                    ;
                    break;

                case 'access_resource':
                    if  (!$this->hasAccess) {
                        // Fake search without result.
                        $qb
                            ->select('value.value AS v')
                            ->where($expr->eq('1', '0'));
                    } else {
                        $qb
                            // TODO Finalize statistics with module Access.
                            ->select(
                                'IDENTITY(omeka_root.owner) AS v', 'user.name AS l', 'COUNT(omeka_root.id) AS t')
// TODO
                            ->leftJoin(\Access\Entity\AccessStatus::class, 'access', Join::WITH, 'omeka_root.owner = user')
                            ->groupBy('omeka_root.owner')
                        ;
                    }
                    break;
            }
            if ($hasEmpty) {
                $qb
                    ->setParameter('empty_int', 0, ParameterType::INTEGER)
                    ->setParameter('empty_string', '', ParameterType::STRING);
            }

            // FIXME The results are doubled when the property has duplicate values for a resource, so fix it or warn about deduplicating values regularly (module BulkEdit).

            if ($periods) {
                // TODO Use a single query instead of a loop, so use periods as columns or rows.
                $qbBase = $qb;
                foreach ($periods as $period => $isEmpty) {
                    if (empty($isEmpty)) {
                        $results[$period] = [];
                        continue;
                    }

                    $qb = clone $qbBase;

                    // TODO Manage "force index" via query builder.
                    if ($byPeriodFilter === 'year') {
                        $yearPeriod = $period;
                        $monthPeriod = null;
                    } else {
                        $yearPeriod = (int) substr((string) $period, 0, 4);
                        $monthPeriod = (int) substr((string) $period, 4, 2);
                    }
                    if ($yearPeriod || $monthPeriod) {
                        // TODO Add the index on table omeka resource for "created" and "modified".
                        // This is the doctrine hashed name index for the column "created".
                        // $force = ' FORCE INDEX FOR JOIN (`IDX_5AD22641B23DB7B8`)';
                        if ($yearPeriod && $monthPeriod) {
                            $qb
                                // ORM doesn't support Extract.
                                // ->andWhere($expr->eq('EXTRACT(YEAR_MONTH FROM omeka_root.created)', ':year_month'))
                                ->andWhere($expr->eq('CONCAT(SUBSTRING(omeka_root.created, 1, 4), SUBSTRING(omeka_root.created, 6, 2))', ':year_month'))
                                ->setParameter('year_month', (int) sprintf('%04d%02d', $yearPeriod, $monthPeriod), ParameterType::INTEGER)
                            ;
                        } elseif ($yearPeriod) {
                            $qb
                                // ORM doesn't support Extract.
                                // ->andWhere($expr->eq('EXTRACT(YEAR FROM omeka_root.created)', ':year'))
                                ->andWhere($expr->eq('SUBSTRING(omeka_root.created, 1, 4)', ':year'))
                                ->setParameter('year', $yearPeriod, ParameterType::INTEGER)
                            ;
                        } elseif ($monthPeriod) {
                            $qb
                                // ORM doesn't support Extract.
                                // ->andWhere($expr->eq('EXTRACT(MONTH FROM omeka_root.created)', ':month'))
                                ->andWhere($expr->eq('SUBSTRING(omeka_root.created, 6, 2)', ':month'))
                                ->setParameter('month', $monthPeriod, ParameterType::INTEGER)
                            ;
                        }
                    } else {
                        // $force = '';
                    }

                    $qb
                        ->addOrderBy('t', 'desc');

                    // $sql = str_replace('COUNT(omeka_root.id) AS "t"', 'COUNT(omeka_root.id) AS "t"' . $force, $sql);
                    // This is a qb from orm: it's not possible to use connection directly.
                    $result = $qb->getQuery()->getScalarResult();

                    /*
                    usort($result, function ($a, $b) use ($sortBy, $sortOrder) {
                        $cmp = strnatcasecmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
                        return $sortOrder === 'desc' ? -$cmp : $cmp;
                    });
                    */

                    $results[$period][$resourceType] = $result;
                }
            } else {
                if ($year || $month) {
                    // TODO Add the index on table omeka resource for "created" and "modified".
                    // This is the doctrine hashed name index for the column "created".
                    // $force = ' FORCE INDEX FOR JOIN (`IDX_5AD22641B23DB7B8`)';
                    if ($year && $month) {
                        $qb
                            // ORM doesn't support Extract.
                            // ->andWhere($expr->eq('EXTRACT(YEAR_MONTH FROM omeka_root.created)', ':year_month'))
                            ->andWhere($expr->eq('CONCAT(SUBSTRING(omeka_root.created, 1, 4), SUBSTRING(omeka_root.created, 6, 2))', ':year_month'))
                            ->setParameter('year_month', (int) sprintf('%04d%02d', $year, $month), ParameterType::INTEGER)
                        ;
                    } elseif ($year) {
                        $qb
                            // ORM doesn't support Extract.
                            // ->andWhere($expr->eq('EXTRACT(YEAR FROM omeka_root.created)', ':year'))
                            ->andWhere($expr->eq('SUBSTRING(omeka_root.created, 1, 4)', ':year'))
                            ->setParameter('year', $year, ParameterType::INTEGER)
                        ;
                    } elseif ($month) {
                        $qb
                            // ORM doesn't support Extract.
                            // ->andWhere($expr->eq('EXTRACT(MONTH FROM omeka_root.created)', ':month'))
                            ->andWhere($expr->eq('SUBSTRING(omeka_root.created, 6, 2)', ':month'))
                            ->setParameter('month', $month, ParameterType::INTEGER)
                        ;
                    }
                } else {
                    // $force = '';
                }

                $qb
                    ->addOrderBy('t', 'desc');

                // This is a qb from orm: it's not possible to use connection directly.
                $results['all'][$resourceType] = $qb->getQuery()->getScalarResult();

                // TODO Reinclude sort order inside sql.
                /*
                usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
                    $cmp = strnatcasecmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                });
                */
            }
        }

        // Merge the items sets/item/media into a single table.
        if ($isOnlyResources) {
            $output = ['resources' => []];
            foreach ($results as $period => $periodResultsByResourceType) {
                // Copy the first array directly. Normally, an empty result is
                // impossible.
                if (!count($periodResultsByResourceType)) {
                    $output[$period]['resources'] = [];
                    continue;
                } elseif (count($periodResultsByResourceType) === 1) {
                    $output[$period]['resources'] = reset($periodResultsByResourceType);
                    continue;
                }
                // Get the total of other resources. Reorder is done below.
                foreach ($periodResultsByResourceType as $resourceType => $values) {
                    foreach ($values as $value) {
                        $v = $value['v'];
                        if (isset($output[$period]['resources'][$v])) {
                            $output[$period]['resources'][$v]['t'] += $value['t'];
                        } else {
                            $output[$period]['resources'][$v] = $value;
                        }
                    }
                }
                $output[$period]['resources'] = array_values($output[$period]['resources']);
            }
            $results = $output;
            unset($output);
        }
        // Or make the sum in a separate table.
        elseif ($hasResources) {
            foreach ($results as $period => $periodResultsByResourceType) {
                // Copy the first array directly. Normally, an empty result is
                // impossible.
                if (!count($periodResultsByResourceType)) {
                    $results[$period]['resources'] = [];
                    continue;
                } elseif (count($periodResultsByResourceType) === 1) {
                    // Resources should be first.
                    $key = key($periodResultsByResourceType);
                    $results[$period] = ['resources' => $periodResultsByResourceType[$key], $key => $periodResultsByResourceType[$key]];
                    continue;
                }
                // Resources should be first.
                $results[$period] = ['resources' => []] + $results[$period];
                foreach ($periodResultsByResourceType as $resourceType => $values) {
                    if ($resourceType === 'resources') {
                        continue;
                    }
                    foreach ($values as $value) {
                        $v = $value['v'];
                        if (isset($results[$period]['resources'][$v])) {
                            $results[$period]['resources'][$v]['t'] += $value['t'];
                        } else {
                            $results[$period]['resources'][$v] = $value;
                        }
                    }
                }
                $results[$period]['resources'] = array_values($results[$period]['resources']);
            }
        }

        // Make the table simpler to manage in the view, nearly like a spreadsheet.
        $hasValueLabel = in_array($typeFilter, ['resource', 'uri', 'resource_class', 'resource_template' , 'owner']);

        $results = $this->mergeResultsByValue($results, $hasValueLabel);
        $totals = $this->totalsByValue($results, $originalResourceTypes, $byPeriodFilter === 'all' ? ['all'] : $periods);

        if ($compute === 'percent') {
            $results = $this->resultsPercentByValue($results, $totals, $originalResourceTypes, $byPeriodFilter === 'all' ? ['all'] : $periods);
            $totals = $this->totalsPercentByValue($totals);
        } elseif ($compute === 'evolution') {
            $results = $this->resultsEvolutionByValue($results, $originalResourceTypes, $byPeriodFilter === 'all' ? ['all'] : $periods);
            $totals = $this->totalsEvolutionByValue($totals, $originalResourceTypes, $byPeriodFilter === 'all' ? ['all'] : $periods);
        } elseif ($compute === 'variation') {
            $results = $this->resultsVariationByValue($results, $originalResourceTypes, $byPeriodFilter === 'all' ? ['all'] : $periods);
            $totals = $this->totalsVariationByValue($totals, $originalResourceTypes, $byPeriodFilter === 'all' ? ['all'] : $periods);
        }

        // TODO There is no pagination currently in stats by value.
        // TODO Manage the right paginator.
        $this->paginator(count($results));

        $view
            ->setVariable('results', $results)
            ->setVariable('totals', $totals);

        $output = $this->params()->fromRoute('output');
        if ($output) {
            return $this->exportResults($view->getVariables(), $output) ?: $view;
        }

        return $view;
    }

    /**
     * Helper to get all stats of a period.
     *
     * @todo Move the view helper Statistics.
     *
     * @param int $startPeriod Number of days before today (default is all).
     * @param int $endPeriod Number of days before today (default is now).
     * @param array $query
     * @param string $field "created" or "modified".
     * @param array $resourceTypes
     * @return array
     */
    protected function statisticsPeriod(
        ?int $startPeriod = null,
        ?int $endPeriod = null,
        array $query = [],
        string $field = 'created',
        array $resourceTypes = []
    ): array {
        $isOnlyResources = array_values($resourceTypes) === ['resources'];
        $hasResources = array_search('resources', $resourceTypes);
        if ($isOnlyResources) {
            $resourceTypes = ['item_sets', 'items', 'media'];
        } elseif ($hasResources !== false) {
            unset($resourceTypes[$hasResources]);
            $hasResources = true;
        }

        if ($startPeriod) {
            $query['datetime'][] = [
                'joiner' => 'and',
                'field' => $field,
                'type' => 'gte',
                'value' => date('Y-m-d 00:00:00', $startPeriod),
            ];
        }
        if ($endPeriod) {
            $query['datetime'][] = [
                'joiner' => 'and',
                'field' => $field,
                'type' => 'lte',
                'value' => date('Y-m-d 23:59:59', $endPeriod),
            ];
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        // Speed the computation for count via api.
        $query['limit'] = 0;

        $results = [];
        // TODO A search by resources will allow only one query, but it is not yet merged by Omeka (#1799).
        // Resource type "resources" is not included here for now.
        foreach ($resourceTypes as $resourceType) {
            $results[$resourceType] = $api->search($resourceType, $query, ['initialize' => false, 'finalize' => false])->getTotalResults();
        }

        if ($isOnlyResources) {
            return ['resources' => array_sum($results)];
        }

        return $hasResources
            // Resources should be first.
            ? ['resources' => array_sum($results)] + $results
            : $results;
    }

    /**
     * Get the results for all values for all periods, so fill empty values for
     * all resource types.
     */
    protected function mergeResultsByValue(array $results, bool $hasValueLabel = false): array
    {
        // Each result by period contains value, label, hits, inclusive hits.
        // The table of values allows to sort hits by totals directly.
        $valuesMaxCounts = [];
        // This value is the merged results.
        $valuesByPeriod = [];
        foreach ($results as $period => $periodResultsByResourceType) {
            foreach ($periodResultsByResourceType as $resourceType => $values) {
                foreach ($values as $result) {
                    $v = $result['v'];
                    if ($hasValueLabel) {
                        $valuesByPeriod[$v]['l'] = $result['l'];
                    }
                    $valuesByPeriod[$v]['t'][$period][$resourceType] = $result['t'];
                    $valuesMaxCounts[$v] = isset($valuesMaxCounts[$v]) ? max($valuesMaxCounts[$v], $result['t']) : $result['t'];
                }
            }
        }
        asort($valuesMaxCounts);
        $valuesMaxCounts = array_reverse($valuesMaxCounts, true);
        return array_replace($valuesMaxCounts, $valuesByPeriod);
    }

    /**
     * Get the totals for each column.
     */
    protected function totalsByValue(array $results, $resourceTypes, $periods): array
    {
        $totals = [];
        foreach ($results as $result) {
            foreach (array_keys($periods) as $period) {
                foreach ($resourceTypes as $resourceType) {
                    if (!empty($result['t'][$period][$resourceType])) {
                        if (isset($totals[$period][$resourceType])) {
                            $totals[$period][$resourceType] += $result['t'][$period][$resourceType];
                        } else {
                            $totals[$period][$resourceType] = $result['t'][$period][$resourceType];
                        }
                    }
                }
            }
        }
        return $totals;
    }

    protected function resultsPercentByValue(array $results, array $totals, array $resourceTypes, array $periods): array
    {
        foreach ($results as &$result) {
            foreach (array_keys($periods) as $period) {
                foreach ($resourceTypes as $resourceType) {
                    if (empty($result['t'][$period][$resourceType])) {
                        $result['t'][$period][$resourceType] = '';
                    } else {
                        $result['t'][$period][$resourceType] = sprintf('%1.2f%%', round($result['t'][$period][$resourceType] / $totals[$period][$resourceType] * 100, 2));
                    }
                }
            }
        }
        unset($result);
        return $results;
    }

    protected function totalsPercentByValue(array $totals): array
    {
        foreach ($totals as &$totalsByResourceType) {
            $totalsByResourceType = array_fill_keys(array_keys($totalsByResourceType), '100%');
        }
        unset($totalsByResourceType);
        return $totals;
    }

    protected function resultsEvolutionByValue(array $results, array $resourceTypes, $periods): array
    {
        // Periods may be missing, so use original periods and resource types.
        $output = [];
        $originalResults = $results;
        foreach ($results as $value => $result) {
            $prevPeriod = null;
            foreach (array_keys($periods) as $period) {
                foreach ($resourceTypes as $resourceType) {
                    $total = $result['t'][$period][$resourceType] ?? 0;
                    $prevTotal = $prevPeriod ? $originalResults[$value]['t'][$prevPeriod][$resourceType] ?? 0 : 0;
                    if (isset($result['l'])) {
                        $output[$value]['l'] = $result['l'];
                    }
                    if (empty($total) && empty($prevTotal)) {
                        $output[$value]['t'][$period][$resourceType] = '';
                    } elseif ($total === $prevTotal) {
                        $output[$value]['t'][$period][$resourceType] = '+0';
                    } elseif (empty($prevTotal)) {
                        $output[$value]['t'][$period][$resourceType] = '+' . $total;
                    } elseif (empty($total)) {
                        $output[$value]['t'][$period][$resourceType] = '-' . $prevTotal;
                    } else {
                        $output[$value]['t'][$period][$resourceType] = sprintf('%+d', $total - $prevTotal);
                    }
                }
                $prevPeriod = $period;
            }
        }
        return $output;
    }

    protected function totalsEvolutionByValue(array $totals, array $resourceTypes, array $periods): array
    {
        // Periods may be missing, so use original periods and resource types.
        $results = [];
        $prevPeriod = null;
        $originalTotals = $totals;
        foreach (array_keys($periods) as $period) {
            foreach ($resourceTypes as $resourceType) {
                $total = $totals[$period][$resourceType] ?? 0;
                $prevTotal = $prevPeriod ? $originalTotals[$prevPeriod][$resourceType] ?? 0 : 0;
                if (empty($total) && empty($prevTotal)) {
                    $results[$period][$resourceType] = '';
                } elseif ($total === $prevTotal) {
                    $results[$period][$resourceType] = '+0';
                } elseif (empty($prevTotal)) {
                    $results[$period][$resourceType] = '+' . $total;
                } elseif (empty($total)) {
                    $results[$period][$resourceType] = '-' . $prevTotal;
                } else {
                    $results[$period][$resourceType] = sprintf('%+d', $total - $prevTotal);
                }
            }
            $prevPeriod = $period;
        }
        return $results;
    }

    protected function resultsVariationByValue(array $results, array $resourceTypes, $periods): array
    {
        // Periods may be missing, so use original periods and resource types.
        $output = [];
        $originalResults = $results;
        foreach ($results as $value => $result) {
            $prevPeriod = null;
            foreach (array_keys($periods) as $period) {
                foreach ($resourceTypes as $resourceType) {
                    $total = $result['t'][$period][$resourceType] ?? 0;
                    $prevTotal = $prevPeriod ? $originalResults[$value]['t'][$prevPeriod][$resourceType] ?? 0 : 0;
                    if (isset($result['l'])) {
                        $output[$value]['l'] = $result['l'];
                    }
                    if (empty($total) && empty($prevTotal)) {
                        $output[$value]['t'][$period][$resourceType] = '';
                    } elseif ($total === $prevTotal) {
                        $output[$value]['t'][$period][$resourceType] = '=';
                    } elseif (empty($prevTotal)) {
                        $output[$value]['t'][$period][$resourceType] = '+';
                    } elseif (empty($total)) {
                        $output[$value]['t'][$period][$resourceType] = '-';
                    } else {
                        $output[$value]['t'][$period][$resourceType] = sprintf('%+1.2f%%', round($total / $prevTotal * 100 - 100, 2));
                    }
                }
                $prevPeriod = $period;
            }
        }
        return $output;
    }

    protected function totalsVariationByValue(array $totals, array $resourceTypes, array $periods): array
    {
        // Periods may be missing, so use original periods and resource types.
        $results = [];
        $prevPeriod = null;
        $originalTotals = $totals;
        foreach (array_keys($periods) as $period) {
            foreach ($resourceTypes as $resourceType) {
                $total = $totals[$period][$resourceType] ?? 0;
                $prevTotal = $prevPeriod ? $originalTotals[$prevPeriod][$resourceType] ?? 0 : 0;
                if (empty($total) && empty($prevTotal)) {
                    $results[$period][$resourceType] = '';
                } elseif ($total === $prevTotal) {
                    $results[$period][$resourceType] = '=';
                } elseif (empty($prevTotal)) {
                    $results[$period][$resourceType] = '+';
                } elseif (empty($total)) {
                    $results[$period][$resourceType] = '-';
                } else {
                    $results[$period][$resourceType] = sprintf('%+1.2f%%', round($total / $prevTotal * 100 - 100, 2));
                }
            }
            $prevPeriod = $period;
        }
        return $results;
    }

    /**
     * @todo Factorize with view templates.
     * @todo Factorize for all output (normalize output first as a spreadsheet).
     */
    protected function exportResults($variables, $output)
    {
        /**
         * Depend on the type, but only common are used.
         *
         * Site
         * @var array $results
         * @var string $type "site"
         * @var string[] $resourceTypes
         * @var int[] $years
         * @var int $yearFilter
         * @var int $monthFilter
         * @var bool $hasAccess
         * @var bool $hasAdvancedSearch
         *
         * Value
         * @var \Laminas\View\Renderer\PhpRenderer $this
         * @var array $results
         * @var string $type "value"
         * @var string[] $resourceTypes
         * @var int[]|null $periods
         * @var int[] $years
         * @var int $yearFilter
         * @var int $monthFilter
         * @var string $propertyFilter
         * @var string $valueTypeFilter
         * @var string $byPeriodFilter
         * @var bool $hasAccess
         * @var bool $hasAdvancedSearch
         */
        extract($variables);

        if (!count($results)) {
            $this->messenger()->addError(new Message('There is no results.')); // @translate
            return null;
        }

        if ($type === 'value' && (!$propertyFilter || is_null($periods))) {
            $this->messenger()->addError(new Message('Check the form.')); // @translate
            return null;
        }

        switch ($output) {
            case 'csv':
                $writer = WriterEntityFactory::createCSVWriter();
                $writer
                    ->setFieldDelimiter(',')
                    ->setFieldEnclosure('"')
                    // The escape character cannot be set with this writer.
                    // ->setFieldEscape($this->getParam('escape', '\\'))
                    // The end of line cannot be set with csv writer (reader only).
                    // ->setEndOfLineCharacter("\n")
                    ->setShouldAddBOM(true);
                break;
            case 'tsv':
                $writer = WriterEntityFactory::createCSVWriter();
                $writer
                    ->setFieldDelimiter("\t")
                    // Unlike import, chr(0) cannot be used, because it's output.
                    // Anyway, enclosure and escape are used only when there is a tabulation
                    // inside the value, but this is forbidden by the format and normally
                    // never exist.
                    // TODO Check if the value contains a tabulation before export.
                    // TODO Do not use an enclosure for tsv export.
                    ->setFieldEnclosure('"')
                    // The escape character cannot be set with this writer.
                    // ->setFieldEscape($this->getParam('escape', '\\'))
                    // The end of line cannot be set with csv writer (reader only).
                    // ->setEndOfLineCharacter("\n")
                    ->setShouldAddBOM(true);
                break;
            case 'ods':
                $writer = WriterEntityFactory::createODSWriter();
                break;
            case 'xlsx':
                /*
                $writer = WriterEntityFactory::createXLSXWriter();
                break;
                */
            default:
                $this->messenger()->addError(new Message('The format "%s" is not supported to export statistics.', $output)); // @translate
                return null;
        }

        $filename = $this->getFilename($type, $output);
        // $writer->openToFile($filePath);
        $writer->openToBrowser($filename);

        $translate = $this->plugin('translate');

        if (in_array($type, ['site'])) {
            $headers = [
                $translate('Site'),
                implode(' / ', $resourceTypes),
            ];
            $rowFromValues = WriterEntityFactory::createRowFromArray($headers);
            $writer->addRow($rowFromValues);
            foreach ($results as $result) {
                $cells = [
                    $result['label'],
                    implode(' / ', $result['count']),
                ];
                $rowFromValues = WriterEntityFactory::createRowFromArray($cells);
                $writer->addRow($rowFromValues);
            }
        } elseif (in_array($type, ['value'])) {
            $isSimpleValue = !in_array($valueTypeFilter, ['resource', 'uri']);
            $hasValueLabel = !$isSimpleValue;
            $isAllPeriods = $byPeriodFilter === 'all';
            $isYearPeriods = $byPeriodFilter === 'year';

            if ($isAllPeriods) {
                $period = 'all';
                $headers = $hasValueLabel
                    ? [$translate('Value'), $translate('Label')]
                    : [$translate('Value')];
                $headers += $resourceTypes;
                $rowFromValues = WriterEntityFactory::createRowFromArray($headers);
                $writer->addRow($rowFromValues);
                foreach ($results as $value => $result) {
                    $cells = $hasValueLabel
                        ? [$value, $result['l']]
                        : [$value];
                    foreach ($resourceTypes as $resourceType) {
                        $cells[] = $result['t'][$period][$resourceType] ?? '';
                    }
                    $rowFromValues = WriterEntityFactory::createRowFromArray($cells);
                    $writer->addRow($rowFromValues);
                }
            } else {
                $headers = $hasValueLabel
                    ? [$translate('Value'), $translate('Label')]
                    : [$translate('Value')];
                foreach (array_keys($periods) as $period) {
                    $headers[] = $isYearPeriods
                        ? $period
                        : sprintf('%04d-%02d', substr((string) $period, 0, 4), substr((string) $period, 4, 2));
                }
                $rowFromValues = WriterEntityFactory::createRowFromArray($headers);
                $writer->addRow($rowFromValues);
                foreach ($results as $value => $result) {
                    $cells = $hasValueLabel
                        ? [$value, $result['l']]
                        : [$value];
                    foreach (array_keys($periods) as $period) {
                        // There may be missing resource types.
                        $t = isset($result['t'][$period])
                            ? array_replace(array_fill_keys($resourceTypes, null), $result['t'][$period])
                            : array_fill_keys($resourceTypes, null);
                        $cells[] = implode(' / ', $t);
                    }
                    $rowFromValues = WriterEntityFactory::createRowFromArray($cells);
                    $writer->addRow($rowFromValues);
                }
            }
        }

        $writer->close();
        exit();
    }

    protected function getFilename($type, $extension): string
    {
        return ($_SERVER['SERVER_NAME'] ?? 'omeka')
            . '-' . $type
            . '-' . date('Ymd-His')
            . '.' . $extension;
    }
}
