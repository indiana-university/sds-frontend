<?php declare(strict_types=1);

namespace Statistics\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Request;
use Statistics\Api\Adapter\HitAdapter;
use Statistics\Api\Adapter\StatAdapter;
use Statistics\Api\Representation\StatRepresentation;
use Statistics\Entity\Stat;

/**
 * Helper to get some public stats.
 *
 * Note: There is no difference between total of page or download, because each
 * url is unique, but there are differences between positions and viewed pages
 * and downloaded files lists.
 */
class Analytics extends AbstractHelper
{
    /**
     * @var \Statistics\Api\Adapter\HitAdapter
     */
    protected $hitAdapter;

    /**
     * @var \Statistics\Api\Adapter\StatAdapter
     */
    protected $statAdapter;

    protected $statFields = [
        'id' => 'id',
        'type' => 'type',
        'url' => 'url',
        'entity_name' => 'entityName',
        'entity_id' => 'entityId',
        // TODO Clarify query for sort (used in some other places).
        'hits' => 'totalHits',
        'anonymous' => 'totalHitsAnonymous',
        'identified' => 'totalHitsIdentified',
        'hits_anonymous' => 'totalHitsAnonymous',
        'hits_identified' => 'totalHitsIdentified',
        'total_hits' => 'totalHits',
        'total_hits_anonymous' => 'totalHitsAnonymous',
        'total_hits_identified' => 'totalHitsIdentified',
        'hitsAnonymous' => 'totalHitsAnonymous',
        'hitsIdentified' => 'totalHitsIdentified',
        'totalHits' => 'totalHits',
        'totalHitsAnonymous' => 'totalHitsAnonymous',
        'totalHitsIdentified' => 'totalHitsIdentified',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $statusColumns = [
        'hits' => 'hits',
        'anonymous' => 'hits_anonymous',
        'identified' => 'hits_identified',
    ];

    public function __construct(HitAdapter $hitAdapter, StatAdapter $statAdapter)
    {
        $this->hitAdapter = $hitAdapter;
        $this->statAdapter = $statAdapter;
    }

    /**
     * Get the stats.
     *
     * @return self
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Hit a new page (to use only with external plugins for not managed urls).
     *
     * No filter is applied to get the eventual resource.
     *
     * @param string $url Url
     * @param \Omeka\Api\Representation\AbstractRepresentation|array $resource
     * If array, contains the resource type (api name) and resource id.
     */
    public function newHit(string $url, $resource = null): void
    {
        if (empty($url)) {
            return;
        }

        $pos = strpos($url, '?');
        if ($pos && $pos !== strlen($url)) {
            $query = substr($url, $pos + 1);
            $requestGet = [];
            parse_url($query, $requestGet);
            $query = $requestGet ?: null;
            $cleanedUrl = $this->checkAndCleanUrl(substr($url, 0, $pos));
        } else {
            $query = null;
            $cleanedUrl = $this->checkAndCleanUrl($url);
        }

        $resource = $this->checkAndPrepareResource($resource);
        $user = $this->view->identity();

        $request = new \Omeka\Api\Request(\Omeka\Api\Request::CREATE, 'hits');
        $request
            ->setContent([
                'url' => $cleanedUrl,
                'entity_name' => $resource['type'],
                'entity_id' => $resource['id'],
                'user_id' => $user ? $user->getId() : 0,
                'query' => $query,
            ])
            ->setOption('initialize', false)
            ->setOption('finalize', false)
            ->setOption('returnScalar', 'id')
        ;
        $this->hitAdapter->create($request);
    }

    /**
     * Total count of hits of the specified page.
     *
     * @uses self::totalHits()
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalPage(?string $url = null, ?string $userStatus = null): int
    {
        if (is_null($url)) {
            $url = $this->currentUrl();
        }
        return $this->totalHits([
            'type' => Stat::TYPE_PAGE,
            'url' => $url,
        ], $userStatus);
    }

    /**
     * Total count of hits of the specified resource.
     *
     * @uses self::totalHits()
     * @param \Omeka\Api\Representation\AbstractEntityRepresentation|string $resourceOrEntityName
     * @param int|null Useless if $resourceOrEntityName is a resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalResource($resourceOrEntityName, ?int $entityId = null, ?string $userStatus = null): int
    {
        $entity = $this->checkAndPrepareResource($resourceOrEntityName, $entityId);
        return $this->totalHits([
            'type' => Stat::TYPE_RESOURCE,
            'entityName' => $entity['type'],
            'entityId' => $entity['id'],
        ], $userStatus);
    }

    /**
     * Total count of hits of the specified resource type.
     *
     * @uses self::totalHits()
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalResourceType(?string $entityName, ?string $userStatus = null): int
    {
        // The total for a resource hits is not saved in one stat: this is the
        // total of all stats of this resource.
        // So either count hits with that resource name, either sum all stats
        // with that resource name.
        if (is_null($entityName)) {
            return 0;
        }

        $userStatus = $this->normalizeUserStatus($userStatus);
        if (!is_null($userStatus)) {
            $column = 'totalHits';
        } elseif (isset($this->statColumns[$userStatus])) {
            $column = $this->statColumns[$userStatus];
        } else {
            return 0;
        }

        $request = new Request(Request::SEARCH, 'stats');
        $request
            ->setContent(['entity_name' => $entityName])
            ->setOption('returnScalar', $column);
        // Here, it's not possible to check identified user.
        $result = $this->statAdapter->search($request)->getContent();
        return array_sum($result);
    }

    /**
     * Total count of hits of the specified downloaded media file.
     *
     * @uses self::totalHits()
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|string|int $value
     * - If numeric, id the downloaded Media.
     * - If string, url or id the downloaded Media.
     * - If Media, total of downloads of this media.
     * @todo Stats of total downloads for item and item set, etc.
     * - If Item, total of dowloaded files of this Item.
     * - If ItemSet, total of downloaded media of all items.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalDownload($value, ?string $userStatus = null): int
    {
        $criteria = $this->normalizeValueForDownload($value);
        return $criteria
            ? $this->totalHits($criteria, $userStatus)
            : 0;
    }

    /**
     * Total count of specified hits.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalHits(array $criteria, ?string $userStatus = null): int
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        try {
            /** @var \Statistics\Entity\Stat $stat */
            $stat = $this->statAdapter->findEntity($criteria);
        } catch (NotFoundException $e) {
            return 0;
        }
        if ($userStatus === 'anonymous') {
            return $stat->getTotalHitsAnonymous();
        }
        return $userStatus === 'identified'
            ? $stat->getTotalHitsIdentified()
            : $stat->getTotalHits();
    }

    /**
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalOfResources(?string $entityName = null, $userStatus = null): int
    {
        if (is_null($entityName)) {
            return 0;
        }
        $request = new Request(Request::SEARCH, $entityName);
        // Here, it's not possible to check identified user.
        if ($userStatus === 'anonymous') {
            $request->setContent(['is_public' => 1, 'limit' => 0]);
        } else {
            // Speed the computation for count via api.
            $request->setContent(['limit' => 0]);
        }

        return $this->statAdapter->getAdapter($entityName)
            ->search($request)
            ->getTotalResults();
    }

    /**
     * Get the position of a stat (first one is the most viewed).
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionHits(array $criteria, ?string $userStatus = null): int
    {
        // Simply count the number of stats of the same type greater than the
        // requested one. So first get the total hits.

        // Criteria should use the entity names to get the total, but the
        // columns names for the direct query below.

        $criteriaTotals = $criteria;
        if (isset($criteria['entity_name'])) {
            $criteriaTotals['entityName'] = $criteria['entity_name'];
            unset($criteriaTotals['entity_name']);
        }
        if (isset($criteria['entity_id'])) {
            $criteriaTotals['entityId'] = $criteria['entity_id'];
            unset($criteriaTotals['entityId']);
        }
        $criteria = $criteriaTotals;
        if (isset($criteria['entityName'])) {
            $criteria['entity_name'] = $criteria['entityName'];
            unset($criteria['entityName']);
        }
        if (isset($criteria['entityId'])) {
            $criteria['entity_id'] = $criteria['entityId'];
            unset($criteria['entityId']);
        }

        // Sometimes, type and resource type are not set.
        // Default type is "page", since a single type is required to get a good
        // results.
        if (!isset($criteria['type'])) {
            if (isset($criteria['entity_id']) || isset($criteriaTotals['entity_name'])) {
                $criteria['type'] = stat::TYPE_RESOURCE;
            } elseif (isset($criteria['url']) && $this->isDownload($criteria['url'])) {
                $criteria['type'] = Stat::TYPE_DOWNLOAD;
            } else {
                $criteria['type'] = Stat::TYPE_PAGE;
            }
        }

        $totalHits = $this->totalHits($criteriaTotals, $userStatus);
        if (!$totalHits) {
            return 0;
        }

        // This data is not stored in stats so do a direct query.

        $hitsColumn = $this->statusColumns[$userStatus] ?? 'hits';

        // Don't use the entity manager connection, but the dbal directly
        // (arguments are different).
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->statAdapter->getServiceLocator()->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('COUNT(DISTINCT(id)) + 1 AS num')
            ->from('stat', 'stat')
            ->where($expr->gt($hitsColumn, ':total_hits'))
            ->andWhere($expr->eq('type', ':type'));
        $bind = [
            'total_hits' => $totalHits,
            'type' => $criteria['type'],
        ];
        if (!empty($criteria['entity_name'])) {
            $qb
                ->andWhere($expr->eq('entity_name', ':entity_name'));
            $bind['entity_name'] = $criteria['entity_name'];
        }

        return (int) $connection->executeQuery($qb, $bind)->fetchOne();
    }

    /**
     * Position of a page (first one is the most viewed).
     *
     * @uses self::positionHits()
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionPage(?string $url, ?string $userStatus = null): int
    {
        if (is_null($url)) {
            $url = $this->currentUrl();
        }
        return $this->positionHits([
            'type' => Stat::TYPE_PAGE,
            'url' => $url,
        ], $userStatus);
    }

    /**
     * Position of a resource by resource type (first one is the most viewed).
     *
     * @param \Omeka\Api\Representation\AbstractEntityRepresentation|string $resourceOrEntityName
     * @param int|null Useless if $resourceOrEntityName is a resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionResource($resourceOrEntityName, ?int $entityId = null, ?string $userStatus = null): int
    {
        $entity = $this->checkAndPrepareResource($resourceOrEntityName, $entityId);
        return $this->positionHits([
            'type' => Stat::TYPE_RESOURCE,
            'entityName' => $entity['type'],
            'entityId' => $entity['id'],
        ], $userStatus);
    }

    /**
     * Position of a download (first one is the most viewed).
     *
     * @uses self::positionHits()
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|string|int $value
     * - If numeric, id the downloaded Media.
     * - If string, url or id the downloaded Media.
     * - If Media, position of this media.
     * @todo Stats of position of downloads for item and item set, etc.
     * - If Item, position of dowloaded files of this Item.
     * - If ItemSet, position of downloaded media of all items.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionDownload($value, ?string $userStatus = null): int
    {
        $criteria = $this->normalizeValueForDownload($value);
        return $criteria
            ? $this->positionHits($criteria, $userStatus)
            : 0;
    }

    /**
     * Get the most viewed rows.
     *
     * Filters events are not triggered.
     *
     * @param array $params A set of parameters by which to filter the objects
     * that get returned from the database.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function vieweds(array $params = [], ?int $page = null, ?int $limit = null): array
    {
        if ($page) {
            $params['page'] = $page;
            if ($limit) {
                $params['per_page'] = $limit;
            }
        } elseif ($limit) {
            $params['limit'] = $limit;
        }

        $request = new Request(Request::SEARCH, 'stats');
        $request
            ->setContent($params)
            ->setOption('initialize', false)
            ->setOption('finalize', false);
        $result = $this->statAdapter->search($request)->getContent();
        foreach ($result as &$stat) {
            $stat = new StatRepresentation($stat, $this->statAdapter);
        }
        return $result;
    }

    /**
     * Get the most viewed pages.
     *
     * Zero viewed pages are never returned.
     *
     * @uses self::vieweds().
     *
     *@param bool|null $hasResource Null for all pages, true or false to set
     * with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function mostViewedPages(?bool $hasResource = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_PAGE,
            'has_resource' => $hasResource,
            'not_zero' => $column,
            'sort_field' => [
                $column => 'desc',
                // This order is needed in order to manage ex-aequos.
                'modified' => 'asc',
            ],
        ];
        return $this->vieweds($criteria, $page, $limit);
    }

    /**
     * Get the most viewed specified resources.
     *
     * Zero viewed resources are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string|array $entityName If array, may contain multiple
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function mostViewedResources($entityName = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            // Needed if $entityName is empty.
            'type' => Stat::TYPE_RESOURCE,
            'entity_name' => $entityName === 'resource' ? null : $entityName,
            'not_zero' => $column,
            'sort_field' => [
                $column => 'desc',
                'modified' => 'asc',
            ],
        ];
        return $this->vieweds($criteria, $page, $limit);
    }

    /**
     * Get the most downloaded files.
     *
     * Zero viewed downloads are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function mostViewedDownloads(?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_DOWNLOAD,
            'not_zero' => $column,
            'sort_field' => [
                $column => 'desc',
                'modified' => 'asc',
            ],
        ];
        return $this->vieweds($criteria, $page, $limit);
    }

    /**
     * Get the last viewed pages.
     *
     * Zero viewed pages are never returned.
     *
     * @uses self::vieweds().
     *
     *@param bool|null $hasResource Null for all pages, true or false to set
     * with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function lastViewedPages(?bool $hasResource = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_PAGE,
            'has_resource' => $hasResource,
            'not_zero' => $column,
            'sort_by' => 'modified',
            'sort_order' => 'desc',
        ];
        return $this->vieweds($criteria, $page, $limit);
    }

    /**
     * Get the last viewed specified resources.
     *
     * Zero viewed resources are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string|array $entityName If array, may contain multiple
     * resource types.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function lastViewedResources($entityNames = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_RESOURCE,
            'entity_name' => empty($entityNames) || $entityNames === 'resource' ? null : $entityNames,
            'not_zero' => $column,
            'sort_by' => 'modified',
            'sort_order' => 'desc',
        ];
        return $this->vieweds($criteria, $page, $limit);
    }

    /**
     * Get the last viewed downloads.
     *
     * Zero viewed downloads are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function lastViewedDownloads(?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_DOWNLOAD,
            'not_zero' => $column,
            'sort_by' => 'modified',
            'sort_order' => 'desc',
        ];
        return $this->vieweds($criteria, $page, $limit);
    }

    /**
     * Get viewed pages.
     *
     * @param bool|null $hasResource Null for all pages, boolean to set with or
     * without resource.
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param int $page Offfset to set page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @param bool $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewedPages(?bool $hasResource = null, ?string $sort = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null, bool $asHtml = true)
    {
        $stats = $sort === 'last'
            ? $this->lastViewedPages($hasResource, $userStatus, $page, $limit)
            : $this->mostViewedPages($hasResource, $userStatus, $page, $limit);

        return $asHtml
            ? $this->viewedHtml($stats, Stat::TYPE_PAGE, $sort, $userStatus)
            : $stats;
    }

    /**
     * Get viewed resources.
     *
     * @param Resource|array $resourceType If array, contains resource type.
     * Can be empty, "all", "none", "page", "download", or "resource" too.
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param int $page Offfset to set page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @param bool $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewedResources($resourceType, ?string $sort = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null, bool $asHtml = true)
    {
        // Manage exceptions.
        if (empty($resourceType) || $resourceType === 'all' || (is_array($resourceType) && in_array('all', $resourceType))) {
            $resourceType = null;
        } elseif ($resourceType === 'none' || (is_array($resourceType) && in_array('none', $resourceType))) {
            return $this->viewedPages(false, $sort, $userStatus, $page, $limit, $asHtml);
        } elseif ($resourceType === Stat::TYPE_PAGE || (is_array($resourceType) && in_array(Stat::TYPE_PAGE, $resourceType))) {
            return $this->viewedPages(null, $sort, $userStatus, $page, $limit, $asHtml);
        } elseif ($resourceType === Stat::TYPE_DOWNLOAD || (is_array($resourceType) && in_array(Stat::TYPE_DOWNLOAD, $resourceType))) {
            return $this->viewedDownloads($sort, $userStatus, $page, $limit, $asHtml);
        }

        $stats = $sort === 'last'
            ? $this->lastViewedResources($resourceType, $userStatus, $page, $limit)
            : $this->mostViewedResources($resourceType, $userStatus, $page, $limit);

        return $asHtml
            ? $this->viewedHtml($stats, Stat::TYPE_RESOURCE, $sort, $userStatus)
            : $stats;
    }

    /**
     * Get viewed downloads.
     *
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param int $page Offfset to set page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @param bool $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewedDownloads(?string $sort = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null, bool $asHtml = true)
    {
        $stats = $sort === 'last'
            ? $this->lastViewedDownloads($userStatus, $page, $limit)
            : $this->mostViewedDownloads($userStatus, $page, $limit);

        return $asHtml
            ? $this->viewedHtml($stats, Stat::TYPE_DOWNLOAD, $sort, $userStatus)
            : $stats;
    }

    /**
     * Retrieve a count of distinct rows for a field. Empty is not count.
     *
     * @param array $query optional Set of search filters upon which to base
     * the count.
     */
    public function countFrequents(array $query = []): int
    {
        $field = $this->checkFieldForFrequency($query);
        if (!$field) {
            return 0;
        }

        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper((string) $query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Here, it's not possible to check identified user.
        if (!$this->view->identity()) {
            $query['is_public'] = 1;
        }

        // Remove empty values.
        $query['not_empty'] = $field;

        $request = new Request(Request::SEARCH, 'hits');
        $request
            ->setContent($query)
            ->setOption('initialize', false)
            ->setOption('finalize', false);

        // Build the search query. No event.
        $entityManager = $this->hitAdapter->getEntityManager();
        $qb = $entityManager
            ->createQueryBuilder()
            ->select("COUNT(DISTINCT(omeka_root.$field))")
            ->from(\Statistics\Entity\Hit::class, 'omeka_root');
        $this->hitAdapter->buildBaseQuery($qb, $query);
        $this->hitAdapter->buildQuery($qb, $query);
        // No group here.
        // $qb->groupBy('omeka_root.id');

        // No limit or order: we get only total results.

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get the most frequent data in a field. Empty values are never returned.
     *
     * The main difference with hit search() is that values are not resources,
     * but array of synthetic values.
     *
     * Default sort is descendant (most frequents first).
     *
     * @param array $params A set of parameters by which to filter the objects
     *   that get returned from the database. It should contains a 'field' for
     *   the name of the column to evaluate.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return array Data and total hits.
     */
    public function frequents(array $query = [], ?int $page = null, ?int $limit = null): array
    {
        $queryField = $query['field'] ?? null;

        $field = $this->checkFieldForFrequency($query);
        if (!$field) {
            return [];
        }

        if ($page) {
            $query['page'] = $page;
            if ($limit) {
                $query['per_page'] = $limit;
            }
        } elseif ($limit) {
            $query['limit'] = $limit;
        }

        $fieldKey = $this->normalizeFieldForQueryKey($queryField);

        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper((string) $query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Here, it's not possible to check identified user.
        if (!$this->view->identity()) {
            $query['is_public'] = 1;
        }

        // Remove empty values.
        $query['not_empty'] = $field;

        $request = new Request(Request::SEARCH, 'hits');
        $request
            ->setContent($query)
            ->setOption('initialize', false)
            ->setOption('finalize', false);

        // Build the search query. No event.
        $entityManager = $this->hitAdapter->getEntityManager();
        $qb = $entityManager
            ->createQueryBuilder()
            ->select(
                "omeka_root.$field AS $fieldKey",
                "COUNT(omeka_root.$field) AS hits"
            )
            ->from(\Statistics\Entity\Hit::class, 'omeka_root');
        $this->hitAdapter->buildBaseQuery($qb, $query);
        $this->hitAdapter->buildQuery($qb, $query);
        // Don't group by id, but by field.
        $qb->groupBy("omeka_root.$field");
        $this->hitAdapter->limitQuery($qb, $query);

        // Frequent cannot sort query to avoid issue with "only_full_group_by".
        // TODO Merge with HitAdapter::sortQuery().
        // $this->hitAdapter->sortQuery($qb, $query);
        if (isset($query['sort_field']) && is_array($query['sort_field'])) {
            $query['sort_field'] = array_intersect_key($query['sort_field'], ['hits' => null, $field]);
            foreach ($query['sort_field'] as &$sortOrder) {
                $sortOrder = strtolower((string) $sortOrder) === 'asc' ? 'asc' : 'desc';
            }
            unset($sortOrder);
            $query['sort_field'] = array_intersect_key($query['sort_field'], ['hits' => null, $field]);
        }
        if (isset($query['sort_by'])) {
            if (in_array($query['sort_by'], ['hits', $field])) {
                $query['sort_order'] = isset($query['sort_order']) && strtolower((string) $query['sort_order']) === 'asc' ? 'asc' : 'desc';
            } else {
                $query['sort_by'] = null;
            }
        }
        $this->hitAdapter->sortQuery($qb, $query);

        if ($queryField === 'language') {
            $qb
                ->select(
                    "SUBSTRING(omeka_root.$field, 1, 2) AS $fieldKey",
                    "COUNT(SUBSTRING(omeka_root.$field, 1, 2)) AS hits"
                )
                ->groupBy($fieldKey);
        }

        // Return an array with two columns.
        return $qb->getQuery()->getScalarResult();
    }

    /**
     * Get the most frequent data in a field.
     *
     * @param string $field Name of the column to evaluate.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return array Data and total of the according total hits
     */
    public function mostFrequents(string $field, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $query = [];
        $query['field'] = $field;
        $query['user_status'] = $userStatus;
        $query['sort_field'] = [
            'hits' => 'DESC',
            // This sort is needed in order to manage ex-aequos.
            // TODO Fix in mysql (only_full_group_by).
            'created' => 'ASC',
        ];
        return $this->frequents($query, $page, $limit);
    }

    /**
     * Get the most viewed specified rows with url, resource and total.
     *
     * This method uses the table Hits directly, so query is different..
     * Zero viewed rows are never returned.
     *
     * Main difference with search() is that values are not resources, but array
     * of synthetic values.
     *
     * @param array $params A set of parameters by which to filter the objects
     *   that get returned from the database.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return array of Hits + column total.
     */
    public function viewedHits(array $query = [], ?int $page = null, ?int $limit = null): array
    {
        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper((string) $query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Here, it's not possible to check identified user.
        if (!$this->view->identity()) {
            $query['user_status'] = 'anonymous';
        }

        $request = new Request(Request::SEARCH, 'hits');
        $request
            ->setContent($query)
            ->setOption('initialize', false)
            ->setOption('finalize', false);

        // Build the search query. No event.
        $entityManager = $this->hitAdapter->getEntityManager();
        $qb = $entityManager
            ->createQueryBuilder()
            ->select(
                'omeka_root.url AS url',
                'omeka_root.entity_name AS entity_name',
                'omeka_root.entity_id AS entity_id',
                'COUNT(url) AS hits'
                // "@position:=@position+1 AS position"
            )
            ->from(\Statistics\Entity\Hit::class, 'omeka_root');
        $this->hitAdapter->buildBaseQuery($qb, $query);
        $this->hitAdapter->buildQuery($qb, $query);
        // Don't group by id.
        $qb->groupBy('omeka_root.url');
        $this->hitAdapter->limitQuery($qb, $query);
        $this->hitAdapter->sortQuery($qb, $query);
        $qb->addOrderBy('omeka_root.id', $query['sort_order']);

        // Return an array with four columns.
        return $qb->getQuery()->getScalarResult();
    }

    /**
     * Get the most viewed specified pages with url, resource and total.
     *
     * This method uses the table Hits directly, so query is different..
     * Zero viewed rows are never returned.
     *
     *@param bool|null $hasResource Null for all pages, true or false to set
     *   with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return array of Hits + column total.
     */
    public function mostViewedHitsPages(?bool $hasResource = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $query = [];
        if (!is_null($hasResource)) {
            $query['has_resource'] = (bool) $hasResource;
        }
        $query['user_status'] = $userStatus;
        $query['sort_field'] = [
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'created' => 'ASC',
        ];
        return $this->viewedHits($query, $page, $limit);
    }

    /**
     * Get the most viewed specified resources with url, resource and total.
     *
     * This method uses the table Hits directly, so query is different..
     * Zero viewed resources are never returned.
     *
     * @param string|array|\Omeka\Api\Representation\AbstractResourceEntityRepresentation $resourceType If array, may contain multiple
     *   resource types.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return array of Hits + column total.
     */
    public function mostViewedHitsResources($entityName = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $query = [];
        $query['entity_name'] = $entityName;
        $query['user_status'] = $userStatus;
        $query['sort_field'] = [
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'created' => 'ASC',
        ];
        return $this->viewedHits($query, $page, $limit);
    }

    /**
     * Get the last viewed specified pages with url, resource and total.
     *
     * This method uses the table Hits directly, so query is different..
     * Zero viewed rows are never returned.
     *
     *@param bool|null $hasResource Null for all pages, true or false to set
     *   with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return array of Hits + column total.
     */
    public function lastViewedHitsPages(?bool $hasResource = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $query = [];
        if (!is_null($hasResource)) {
            $query['has_entity'] = (bool) $hasResource;
        }
        $query['user_status'] = $userStatus;
        $query['sort_by'] = 'created';
        $query['sort_order'] = 'DESC';
        return $this->viewedHits($query, $page, $limit);
    }

    /**
     * Get the last viewed specified resources with url, resource and total.
     *
     * This method uses the table Hits directly, so query is different..
     * Zero viewed resources are never returned.
     *
     * @param string|array|\Omeka\Api\Representation\AbstractResourceEntityRepresentation $entityName If array, may contain multiple
     *   resource types.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $page Page to retrieve.
     * @param int $limit Number of objects to return per "page".
     * @return array of Hits + column total.
     */
    public function lastViewedHitsResources($entityName = null, ?string $userStatus = null, ?int $page = null, ?int $limit = null): array
    {
        $query = [];
        $query['entity_name'] = $entityName;
        $query['user_status'] = $userStatus;
        $query['sort_by'] = 'created';
        $query['sort_order'] = 'DESC';
        return $this->viewedHits($query, $page, $limit);
    }

    /**
     * Get the stat view for the selected page.
     *
     * @param string $url Url (current url if empty).
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string Html code from the theme.
     */
    public function textPage(?string $url = null, ?string $userStatus = null): string
    {
        if (empty($url)) {
            $url = $this->currentUrl();
        }
        $userStatus = $this->normalizeUserStatus($userStatus);
        $stat = $this->view->api()->searchOne('stats', ['url' => $url, 'type' => Stat::TYPE_PAGE])->getContent();
        return $this->view->partial('common/analytics-value', [
            'type' => Stat::TYPE_PAGE,
            'stat' => $stat,
            'userStatus' => $userStatus,
        ]);
    }

    /**
     * Get the stat view for the selected resource.
     *
     * @param Resource|array $resource If array, contains resource type and resource id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string Html code from the theme.
     */
    public function textResource($resource, ?string $userStatus = null): string
    {
        // Check and get resource.
        $resource = $this->checkAndPrepareResource($resource);
        if (empty($resource['type'])) {
            return '';
        }
        $stat = $this->view->api()->searchOne('stats', ['entity_name' => $resource['type'], 'entity_id' => $resource['id'], 'type' => Stat::TYPE_RESOURCE])->getContent();
        $userStatus = $this->normalizeUserStatus($userStatus);
        return $this->view->partial('common/analytics-value', [
            'type' => Stat::TYPE_RESOURCE,
            'stat' => $stat,
            'userStatus' => $userStatus,
        ]);
    }

    /**
     * Get the stat view for the selected download.
     *
     * @param string|int $downloadId Url or id of the downloaded file.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string Html code from the theme.
     */
    public function textDownload($downloadId, ?string $userStatus = null): string
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        $stat = $this->view->api()->searchOne(
            'stats',
            is_numeric($downloadId)
                ? ['entity_name' => 'media', 'entity_id' => $downloadId, 'type' => Stat::TYPE_DOWNLOAD]
                : ['url' => $downloadId, 'type' => Stat::TYPE_DOWNLOAD]
        )->getContent();
        return $this->view->partial('common/analytics-value', [
            'type' => Stat::TYPE_DOWNLOAD,
            'stat' => $stat,
            'userStatus' => $userStatus,
        ]);
    }

    /**
     * Helper to get the human name of the resource type.
     *
     * @param string $resourceType
     * @param string $defaultEmpty Return this string if empty
     */
    public function humanResourceType($resourceType, ?string $defaultEmpty = null): string
    {
        if (empty($resourceType)) {
            return (string) $defaultEmpty;
        }
        $cleanResourceType = $this->normalizeResourceType($resourceType);
        $translate = $this->view->plugin('translate');
        switch ($cleanResourceType) {
            // Api names
            case 'annotations':
                return $translate('Annotation');
            case 'items':
                return $translate('Item');
            case 'item_sets':
                return $translate('Item set');
            case 'media':
                return $translate('Media');
            case 'resources':
                return $translate('Resource');
            case 'site_pages':
                return $translate('Page');
            default:
                return $translate($resourceType);
        }
    }

    /**
     * Get default user status. This functions is used to allow synonyms.
     *
     * @param string $userStatus
     *
     * @return string
     */
    public function humanUserStatus(?string $userStatus): string
    {
        $translate = $this->view->plugin('translate');
        $userStatus = $this->normalizeUserStatus();
        switch ($userStatus) {
            case 'anonymous':
                return $translate('anonymous users');
            case 'identified':
                return $translate('identified users');
            case 'hits':
            default:
                return $translate('all users');
        }
    }

    /**
     * Determine whether or not the hit is from a bot/webcrawler
     *
     * @return bool True if hit is from a bot, otherwise false
     */
    public function isBot(?string $userAgent): bool
    {
        // For dev purpose.
        // print "<!-- UA : " . $this->resource->getUserAgent() . " -->";
        $crawlers = 'bot|crawler|slurp|spider|check_http';
        return (bool) preg_match("~$crawlers~", strtolower((string) $userAgent));
    }

    /**
     * Determine whether or not the hit is a direct download.
     *
     * Of course, only files stored locally can be hit.
     * @todo Manage a specific path.
     *
     * @return bool True if hit has a resource, even deleted.
     */
    public function isDownload(?string $url): bool
    {
        return strpos((string) $url, '/files/') === 0;
    }

    /**
     * Helper to get params from a resource. If no resource, return empty resource.
     *
     * This allows resource to be an object or an array. This is useful to avoid
     * to fetch a resource when it's not needed, in particular when it's called
     * from the theme.
     *
     * Recommended forms are object and associative array with 'type' (api name)
     * and 'id' as keys.
     *
     * @return array Associative array with resource type and id.
     */
    public function checkAndPrepareResource($resourceOrEntityName, ?int $entityId = null): array
    {
        if (is_object($resourceOrEntityName)) {
            /** @var \Omeka\Api\Representation\AbstractRepresentation $resource */
            $type = $resourceOrEntityName->getControllerName();
            $id = $resourceOrEntityName->id();
        } elseif (is_array($resourceOrEntityName)) {
            if (count($resourceOrEntityName) === 1) {
                $id = reset($resourceOrEntityName);
                $type = key($resourceOrEntityName);
            } elseif (count($resourceOrEntityName) === 2) {
                if (is_numeric(key($resourceOrEntityName))) {
                    $type = array_shift($resourceOrEntityName);
                    $id = array_shift($resourceOrEntityName);
                } else {
                    $type = $resourceOrEntityName['type']
                        ?? $resourceOrEntityName['resource_type']
                        ?? $resourceOrEntityName['name']
                        ?? $resourceOrEntityName['entity_name'];
                    $id = $resourceOrEntityName['id']
                        ?? $resourceOrEntityName['resource_id']
                        ?? $resourceOrEntityName['entity_id'];
                }
            } else {
                return ['type' => '', 'id' => 0];
            }
        } elseif ($resourceOrEntityName && $entityId) {
            return ['type' => (string) $resourceOrEntityName, 'id' => (int) $entityId];
        } else {
            return ['type' => '', 'id' => 0];
        }
        $type = $this->normalizeResourceType($type);
        return empty($type) || empty($id)
            ? ['type' => '', 'id' => 0]
            : ['type' => (string) $type, 'id' => (int) $id];
    }

    /**
     * Get default user status if not set.
     */
    protected function normalizeUserStatus(?string $userStatus = null): string
    {
        $userStatuses = [
            'hits',
            'anonymous',
            'identified',
        ];
        if (in_array($userStatus, $userStatuses)) {
            return $userStatus;
        }
        return $this->view->status()->isAdminRequest()
            ? (string) $this->view->setting('statistics_default_user_status_admin', 'hits')
            : (string) $this->view->setting('statistics_default_user_status_public', 'anonymous');
    }

    protected function normalizeResourceType(?string $type): ?string
    {
        $apiNames = [
            // Api names
            'items' => 'items',
            'item_sets' => 'item_sets',
            'media' => 'media',
            'resources' => 'resources',
            'site_pages' => 'site_pages',
            // Json-ld names
            'o:Item' => 'items',
            'o:ItemSet' => 'item_sets',
            'o:Media' => 'media',
            'o:SitePage' => 'site_pages',
            // Classes.
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Media::class => 'media',
            \Omeka\Entity\Resource::class => 'resource',
            \Omeka\Entity\SitePage::class => 'site_pages',
            \Omeka\Api\Representation\ItemRepresentation::class => 'items',
            \Omeka\Api\Representation\ItemSetRepresentation::class => 'item_sets',
            \Omeka\Api\Representation\MediaRepresentation::class => 'media',
            \Omeka\Api\Representation\ResourceReference::class => 'resource',
            \Omeka\Api\Representation\SitePageRepresentation::class => 'site_pages',
            // Other names.
            'resource' => 'resources',
            'resource:item' => 'items',
            'resource:itemset' => 'item_sets',
            'resource:media' => 'media',
            // Other resource types or badly written types.
            'o:item' => 'items',
            'o:item_set' => 'item_sets',
            'o:media' => 'media',
            'item' => 'items',
            'item_set' => 'item_sets',
            'item-set' => 'item_sets',
            'itemset' => 'item_sets',
            'resource:item_set' => 'item_sets',
            'resource:item-set' => 'item_sets',
            'page' => 'site_pages',
            'pages' => 'site_pages',
            'site_page' => 'site_pages',
        ];
        return empty($type) ? null : $apiNames[$type] ?? $apiNames[strtolower($type)] ?? null;
    }

    /**
     * Normalize a value argument to check downloads.
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|string|int $value
     */
    protected function normalizeValueForDownload($value): ?array
    {
        $criteria = ['type' => Stat::TYPE_DOWNLOAD];
        if (is_numeric($value)) {
            $criteria['entity_name'] = 'media';
            $criteria['entity_id'] = (int) $value;
        } elseif (is_string($value)) {
            $criteria['entity_name'] = 'media';
            $criteria['url'] = $value;
        } elseif (is_object($value)) {
            if ($value instanceof \Omeka\Api\Representation\MediaRepresentation) {
                $criteria['entity_name'] = 'media';
                $criteria['entity_id'] = $value->id();
            } elseif ($value instanceof \Omeka\Api\Representation\ItemRepresentation) {
                $criteria['entity_name'] = 'items';
                $criteria['entity_id'] = $value->id();
            } elseif ($value instanceof \Omeka\Api\Representation\ItemSetRepresentation) {
                $criteria['entity_name'] = 'item_sets';
                $criteria['entity_id'] = $value->id();
            } elseif ($value instanceof \Omeka\Entity\Media) {
                $criteria['entity_name'] = 'media';
                $criteria['entity_id'] = $value->getId();
            } elseif ($value instanceof \Omeka\Entity\Item) {
                $criteria['entity_name'] = 'items';
                $criteria['entity_id'] = $value->getId();
            } elseif ($value instanceof \Omeka\Entity\ItemSet) {
                $criteria['entity_name'] = 'item_sets';
                $criteria['entity_id'] = $value->getId();
            } else {
                // Download are only for media files.
                return null;
            }
        } else {
            return null;
        }
        return $criteria;
    }

    /**
     * Get the current site from the view or the root view (main layout).
     */
    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        return $this->view->site ?? $this->view->site = $this->view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }

    /**
     * Get the current url.
     */
    protected function currentUrl(): string
    {
        static $currentUrl;

        if (is_null($currentUrl)) {
            $currentUrl = $this->view->url(null, [], true);
            $basePath = $this->view->basePath();
            if ($basePath && $basePath !== '/') {
                $start = substr($currentUrl, 0, strlen($basePath));
                // Manage specific paths for files.
                if ($start === $basePath) {
                    $currentUrl = substr($currentUrl, strlen($basePath));
                }
            }
        }

        return $currentUrl;
    }

    /**
     * Clean the url to get better results (remove the domain and base path).
     */
    protected function checkAndCleanUrl(string $url): string
    {
        $plugins = $this->view->getHelperPluginManager();
        $serverUrl = $plugins->get('serverUrl')->__invoke();
        $basePath = $plugins->get('basePath')->__invoke();

        $url = trim($url);

        // Strip out the protocol, host, base URL, and rightmost slash before
        // comparing the URL to the current one
        $stripOut = [$serverUrl . $basePath, @$_SERVER['HTTP_HOST'], $basePath];
        $cleanedUrl = rtrim(str_replace($stripOut, '', $url), '/');

        if (substr($cleanedUrl, 0, 4) === 'http' || substr($cleanedUrl, 0, 1) !== '/') {
            return '';
        }
        return $cleanedUrl;
    }

    /**
     * Check if there is a key 'field' with a column name for frequency queries.
     */
    protected function checkFieldForFrequency($params): ?string
    {
        $fields = [
            'id' => 'id',
            'url' => 'url',
            'entity_name' => 'entityName',
            'entity_id' => 'entityId',
            'user_id' => 'userId',
            'ip' => 'ip',
            'query' => 'query',
            'referrer' => 'referrer',
            'user_agent' => 'userAgent',
            'accept_language' => 'acceptLanguage',
            'created' => 'created',
            // For simplicity, but not recommended.
            'entityName' => 'entityName',
            'entityId' => 'entityId',
            'userId' => 'userId',
            'userAgent' => 'userAgent',
            'acceptLanguage' => 'acceptLanguage',
            'language' => 'acceptLanguage',
        ];
        return $fields[$params['field'] ?? null] ?? null;
    }

    /**
     * Check if there is a key 'field' with a column name for frequency queries.
     */
    protected function normalizeFieldForQueryKey(string $field): ?string
    {
        $fields = [
            'entityName' => 'entity_name',
            'entityId' => 'entity_id',
            'userId' => 'user_id',
            'userAgent' => 'user_agent',
            'acceptLanguage' => 'accept_language',
            'language' => 'language',
        ];
        return $fields[$field] ?? $field;
    }

    /**
     * Helper to get string from list of stats.
     *
     * @param array $stats Array of stats.
     * @param string $type "page", "resource" or "download".
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string html
     */
    protected function viewedHtml($stats, $type, $sort, $userStatus): string
    {
        if (empty($stats)) {
            return '<div class="stats">'
                . $this->view->translate('None.') // @translate
                . '</div>';
        }

        // TODO Use partial loop.
        $partial = $this->view->plugin('partial');
        $html = '';
        foreach ($stats as $key => $stat) {
            $params = [
                'type' => $type,
                'stat' => $stat,
                'sort' => $sort,
                'userStatus' => $userStatus,
                'position' => $key + 1,
            ];
            $html .= $partial('common/analytics-single', $params);
        }
        return $html;
    }
}
