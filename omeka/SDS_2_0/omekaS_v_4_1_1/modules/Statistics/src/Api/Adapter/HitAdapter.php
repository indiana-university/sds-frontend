<?php declare(strict_types=1);

namespace Statistics\Api\Adapter;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\User;
use Omeka\Stdlib\ErrorStore;
use Statistics\Api\Representation\HitRepresentation;
use Statistics\Entity\Hit;
use Statistics\Entity\Stat;

/**
 * The Hit table.
 *
 * Get stats about hits. Generally, it's quicker to use the Stat table.
 *
 * Adapted:
 * @see \AnalyticsSnippet\Tracker\HitData
 * @see \Statistics\Api\Adapter\HitAdapter
 */
class HitAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'url' => 'url',
        'entity_id' => 'entityId',
        'entity_name' => 'entityName',
        'site_id' => 'siteId',
        'user_id' => 'userId',
        'ip' => 'ip',
        // To sort query has no meaning.
        // 'query' => 'query',
        'referrer' => 'referrer',
        'user_agent' => 'userAgent',
        'accept_language' => 'acceptLanguage',
        // TODO Clarify query for sort.
        'entityName' => 'entityName',
        'entityId' => 'entityId',
        'userAgent' => 'userAgent',
        'acceptLanguage' => 'acceptLanguage',
        'created' => 'created',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'url' => 'url',
        'entity_id' => 'entityId',
        'entity_name' => 'entityName',
        'site' => 'siteId',
        'user' => 'userId',
        'ip' => 'ip',
        'query' => 'query',
        'referrer' => 'referrer',
        'user_agent' => 'userAgent',
        'accept_language' => 'acceptLanguage',
        'created' => 'created',
    ];

    public function getResourceName()
    {
        return 'hits';
    }

    public function getEntityClass()
    {
        return Hit::class;
    }

    public function getRepresentationClass()
    {
        return HitRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['url'])) {
            if (is_array($query['url'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, $query['url'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, $query['url'])
                ));
            }
        }

        // The query may use "resource_type" or "entity_name".
        if (isset($query['resource_type'])) {
            $query['entity_name'] = $query['resource_type'];
        }
        if (isset($query['entity_name'])) {
            if (is_array($query['entity_name'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.entityName',
                    $this->createNamedParameter($qb, $query['entity_name'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.entityName',
                    $this->createNamedParameter($qb, $query['entity_name'])
                ));
            }
        }

        // The query may use "resource_id" or "entity_id".
        if (isset($query['resource_id'])) {
            $query['entity_id'] = $query['resource_id'];
        }
        if (isset($query['entity_id'])) {
            if (is_array($query['entity_id'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, $query['entity_id'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, $query['entity_id'])
                ));
            }
        }

        if (isset($query['has_resource'])) {
            $query['has_entity'] = $query['has_resource'];
        }
        if (isset($query['has_entity'])) {
            $qb
                ->andWhere(
                    $query['has_entity']
                        ? $expr->neq('omeka_root.entityName', $this->createNamedParameter($qb, ''))
                        : $expr->eq('omeka_root.entityName', $this->createNamedParameter($qb, ''))
                );
        }

        if (isset($query['has_entity']) && $query['has_entity'] !== '') {
            if ($query['has_entity']) {
                $qb->andWhere($expr->neq(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, '0')
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, '0')
                ));
            }
        }

        if (isset($query['site_id'])) {
            if (is_array($query['site_id'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.siteId',
                    $this->createNamedParameter($qb, $query['site_id'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.siteId',
                    $this->createNamedParameter($qb, $query['site_id'])
                ));
            }
        }

        if (isset($query['user_id'])) {
            if (is_array($query['user_id'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, $query['user_id'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, $query['user_id'])
                ));
            }
        }

        if (isset($query['user_status'])
            && in_array($query['user_status'], ['identified', 'anonymous'])
        ) {
            if ($query['user_status'] === 'identified') {
                $qb->andWhere($expr->neq(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, 0)
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, 0)
                ));
            }
        }

        if (isset($query['is_download']) && $query['is_download'] !== '') {
            if ($query['is_download']) {
                $qb->andWhere($expr->like(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, '/files/%')
                ));
            } else {
                $qb->andWhere($expr->notLike(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, '/files/%')
                ));
            }
        }

        if (isset($query['ip'])) {
            if (is_array($query['ip'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.ip',
                    $this->createNamedParameter($qb, $query['ip'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.ip',
                    $this->createNamedParameter($qb, $query['ip'])
                ));
            }
        }

        if (isset($query['referrer'])) {
            if (is_array($query['referrer'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.referrer',
                    $this->createNamedParameter($qb, $query['referrer'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.referrer',
                    $this->createNamedParameter($qb, $query['referrer'])
                ));
            }
            // This special filter allows to get external referrers only.
            $serverUrlHelper = $this->serviceLocator->get('ViewHelperManager')->get('ServerUrl');
            $baseUrlPath = $this->serviceLocator->get('Router')->getBaseUrl();
            $webRootLike = $serverUrlHelper($baseUrlPath ? $baseUrlPath . '/%' : '/%');
            $qb
                ->andWhere($expr->notLike(
                    'omeka_root.referrer',
                    $this->createNamedParameter($qb, $webRootLike)
                ));
        }

        if (isset($query['user_agent'])) {
            if (is_array($query['user_agent'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.userAgent',
                    $this->createNamedParameter($qb, $query['user_agent'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.userAgent',
                    $this->createNamedParameter($qb, $query['user_agent'])
                ));
            }
        }

        if (isset($query['accept_language'])) {
            if (is_array($query['accept_language'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.acceptLanguage',
                    $this->createNamedParameter($qb, $query['accept_language'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.acceptLanguage',
                    $this->createNamedParameter($qb, $query['accept_language'])
                ));
            }
        }

        // TODO @experimental or @deprecated Use "has_value".
        if (isset($query['field'])
            && in_array($query['field'], ['query', 'referrer', 'user_agent', 'accept_language', 'userAgent', 'acceptLanguage'])
        ) {
            $columns = [
                'query' => 'query',
                'referrer' => 'referrer',
                'user_agent' => 'userAgent',
                'accept_language' => 'acceptLanguage',
                'userAgent' => 'userAgent',
                'acceptLanguage' => 'acceptLanguage',
            ];
            $field = $columns[$query['field']];
            if ($field === 'query') {
                $qb->andWhere($expr->isNotNull(
                    'omeka_root.' . $field
                ));
            } else {
                $qb->andWhere($expr->neq(
                    'omeka_root.' . $field,
                    $this->createNamedParameter($qb, '')
                ));
            }
            if ($field === 'referrer') {
                // This special filter allows to get external referrers only.
                $serverUrlHelper = $this->serviceLocator->get('ViewHelperManager')->get('ServerUrl');
                $baseUrlPath = $this->serviceLocator->get('Router')->getBaseUrl();
                $webRootLike = $serverUrlHelper($baseUrlPath ? $baseUrlPath . '/%' : '/%');
                $qb
                    ->andWhere($expr->notLike(
                        'omeka_root.referrer',
                        $this->createNamedParameter($qb, $webRootLike)
                    ));
            }
        }

        // TODO @experimental or @deprecated
        if (isset($query['not_empty'])
            && in_array($query['not_empty'], ['query', 'referrer', 'user_agent', 'accept_language', 'userAgent', 'acceptLanguage'])
        ) {
            $columns = [
                'query' => 'query',
                'referrer' => 'referrer',
                'user_agent' => 'userAgent',
                'accept_language' => 'acceptLanguage',
                'userAgent' => 'userAgent',
                'acceptLanguage' => 'acceptLanguage',
            ];
            $field = $columns[$query['not_empty']];
            if ($field === 'query') {
                $qb->andWhere($expr->isNotNull(
                    'omeka_root.' . $field
                ));
            } else {
                $qb->andWhere($expr->neq(
                    'omeka_root.' . $field,
                    $this->createNamedParameter($qb, '')
                ));
            }
        }

        if (isset($query['since']) && strlen((string) $query['since'])) {
            // Adapted from Omeka classic.
            // Accept an ISO 8601 date, set the tiemzone to the server's default
            // timezone, and format the date to be MySQL timestamp compatible.
            $date = new \DateTime((string) $query['since'], new \DateTimeZone(date_default_timezone_get()));
            // Don't return result when date is badly formatted.
            if (!$date) {
                $qb->andWhere($expr->eq(
                    'omeka_root.created',
                    $this->createNamedParameter($qb, 'since_error')
                ));
            } else {
                // Select all dates that are greater than the passed date.
                $qb->andWhere($expr->gte(
                    'omeka_root.created',
                    $this->createNamedParameter($qb, $date->format('Y-m-d H:i:s'))
                ));
            }
        }

        if (isset($query['until']) && strlen((string) $query['until'])) {
            $date = new \DateTime((string) $query['until'], new \DateTimeZone(date_default_timezone_get()));
            // Don't return result when date is badly formatted.
            if (!$date) {
                $qb->andWhere($expr->eq(
                    'omeka_root.created',
                    $this->createNamedParameter($qb, 'until_error')
                ));
            } else {
                // Select all dates that are lower than the passed date.
                $qb->andWhere($expr->lte(
                    'omeka_root.created',
                    $this->createNamedParameter($qb, $date->format('Y-m-d H:i:s'))
                ));
            }
        }
    }

    public function sortQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['sort_field']) && is_array($query['sort_field'])) {
            foreach ($query['sort_field'] as $by => $order) {
                if ($by === 'hits') {
                    $qb->addOrderBy('hits', $order);
                } else {
                    parent::sortQuery($qb, [
                        'sort_by' => $by,
                        'sort_order' => $order,
                    ]);
                }
            }
        }

        // Sort by "hits" is not a sort by field, but a sort by count.
        if (isset($query['sort_by']) && $query['sort_by'] === 'hits') {
            $qb->addOrderBy('hits', $query['sort_order'] ?? 'asc');
        }

        parent::sortQuery($qb, $query);
    }

    /**
     * No need to validate: missing data are taken from current request.
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::validateRequest()
     *
     * {@inheritDoc}
     */
    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \Statistics\Entity\Hit $entity */
        // A hit cannot be updated here: it's a static resource.
        if (Request::UPDATE === $request->getOperation()) {
            return;
        }

        $data = $request->getContent();
        $data = $this->fillHit($data);

        // This is quicker than using inflector.
        $keyMethods = [
            // Since it is a creation, id is set automatically.
            // 'o:id' => 'setId',
            'o:url' => 'setUrl',
            'o:entity_id' => 'setEntityId',
            'o:entity_name' => 'setEntityName',
            'o:site_id' => 'setSiteId',
            'o:user_id' => 'setUserId',
            'o:ip' => 'setIp',
            'o:query' => 'setQuery',
            'o:referrer' => 'setReferrer',
            'o:user_agent' => 'setUserAgent',
            'o:accept_language' => 'setAcceptLanguage',
            // 'o:created' => 'setCreated',
        ];
        foreach ($data as $key => $value) {
            $keyName = substr($key, 0, 2) === 'o:' ? $key : 'o:' . $key;
            if (!isset($keyMethods[$keyName])) {
                continue;
            }
            $method = $keyMethods[$keyName];
            if (in_array($key, ['o:entity_id', 'o:site_id', 'o:user_id'])) {
                $value = (int) $value;
            } elseif ($key === 'o:query') {
                // The value "query" should be an array or null (doctrine json).
                if (empty($value) || !is_array($value)) {
                    $value = null;
                }
            }
            $entity->$method($value);
        }

        $now = new DateTime('now');
        $entity->setCreated($now);

        /** @var \Statistics\Api\Adapter\StatAdapter $statAdapter */
        $statAdapter = $this->getAdapter('stats');
        $entityManager = $this->getEntityManager();

        // Stat is created if not exists.
        // "page" and "download" are mutually exclusive.
        $url = $entity->getUrl();
        $isDownload = $this->isDownload($url);
        $entityName = $entity->getEntityName();
        $entityId = $entity->getEntityId();

        $stat = $this->findStatForHit($entity);
        if ($stat) {
            $stat
                ->setModified($now);
        } else {
            $stat = new Stat();
            $stat
                ->setType($isDownload ? Stat::TYPE_DOWNLOAD : Stat::TYPE_PAGE)
                ->setUrl($url)
                ->setEntityName($entityName)
                ->setEntityId($entityId)
                ->setCreated($now)
                ->setModified($now)
            ;
        }
        $statAdapter->increaseHits($stat);
        $entityManager->persist($stat);

        // A second stat is needed to manage resource count.
        if (!$entityName || !$entityId) {
            return;
        }

        $statResource = $this->findStatForHit($entity, true);
        if ($statResource) {
            $statResource
                ->setModified($now);
        } else {
            $statResource = new Stat();
            $statResource
                ->setType(Stat::TYPE_RESOURCE)
                ->setUrl($url)
                ->setEntityName($entityName)
                ->setEntityId($entityId)
                ->setCreated($now)
                ->setModified($now)
            ;
        }
        $statAdapter->increaseHits($statResource);
        $entityManager->persist($statResource);
    }

    /**
     * Find the matching Stat from a hit, without event and exception.
     */
    public function findStatForHit(Hit $hit, bool $statResource = false): ?Stat
    {
        $url = $hit->getUrl();
        $parameters = new ArrayCollection([
            new Parameter('url', $url, ParameterType::STRING),
        ]);

        $qb = $this->getEntityManager()->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select('omeka_root')
            ->from(Stat::class, 'omeka_root')
            ->where($expr->eq('omeka_root.url', ':url'))
            ->andWhere($expr->eq('omeka_root.type', ':type'))
            ->setMaxResults(1);

        if ($statResource) {
            $entityName = $hit->getEntityName();
            $entityId = $hit->getEntityId();
            if (!$entityName || !$entityId) {
                return null;
            }
            $qb
                ->andWhere($expr->eq('omeka_root.entityName', ':entity_name'))
                ->andWhere($expr->eq('omeka_root.entityId', ':entity_id'));
            $parameters[] = new Parameter('type', Stat::TYPE_RESOURCE, ParameterType::STRING);
            $parameters[] = new Parameter('entity_name', $entityName, ParameterType::STRING);
            $parameters[] = new Parameter('entity_id', $entityId, ParameterType::INTEGER);
        }

        // Stat is created and filled via getStat() if not exists.
        // "page" and "download" are mutually exclusive.
        elseif ($this->isDownload($url)) {
            $qb
                ->andWhere($expr->eq('omeka_root.entityName', ':entity_name'))
                ->andWhere($expr->eq('omeka_root.entityId', ':entity_id'));
            $parameters[] = new Parameter('type', Stat::TYPE_DOWNLOAD, ParameterType::STRING);
            $parameters[] = new Parameter('entity_name', 'media', ParameterType::STRING);
            $parameters[] = new Parameter('entity_id', $hit->getEntityId(), ParameterType::INTEGER);
        } else {
            $parameters[] = new Parameter('type', Stat::TYPE_PAGE, ParameterType::STRING);
        }

        return $qb
            ->setParameters($parameters)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Fill data with data of the current request.
     *
     * @param array $data
     * @return array
     */
    public function fillHit(array $data = []): array
    {
        // Use "o:" only to manage api.
        $keys = [
            'id' => 'o:id',
            'url' => 'o:url',
            'entity_id' => 'o:entity_id',
            'entity_name' => 'o:entity_name',
            'site_id' => 'o:site_id',
            'user_id' => 'o:user_id',
            'ip' => 'o:ip',
            'query' => 'o:query',
            'referrer' => 'o:referrer',
            'user_agent' => 'o:user_agent',
            'accept_language' => 'o:accept_language',
            'created' => 'o:created',
        ];

        $currentDataFromRoute = $this->currentDataFromRoute();
        $currentRequest = $this->currentRequest();
        $result = array_fill_keys($keys, null);
        foreach ($keys as $key => $keyName) {
            if (isset($data[$keyName])) {
                $value = $data[$keyName];
            } elseif (isset($data[$key])) {
                $value = $data[$key];
            } else {
                switch ($keyName) {
                    case 'o:id':
                        $value = null;
                        break;
                    case 'o:url':
                        $value = substr((string) $currentRequest['url'], 0, 1024);
                        break;
                    case 'o:entity_id':
                        $value = $currentDataFromRoute['id'];
                        break;
                    case 'o:entity_name':
                        $value = $currentDataFromRoute['name'];
                        break;
                    case 'o:site_id':
                        $value = $currentDataFromRoute['site_id'];
                        break;
                    case 'o:user_id':
                        $value = $this->currentUser();
                        $value = $value ? $value->getId() : null;
                        break;
                    case 'o:ip':
                        $value = $this->privacyIp();
                        break;
                    case 'o:query':
                        if (empty($currentRequest['query'])) {
                            $value = null;
                        } elseif (is_string($currentRequest['query'])) {
                            parse_str($currentRequest['query'], $value);
                            unset(
                                $value['key_credential'],
                                $value['key_identity'],
                                $value['password']
                            );
                        } elseif (is_array($currentRequest['query'])) {
                            $value = $currentRequest['query'];
                            unset(
                                $value['key_credential'],
                                $value['key_identity'],
                                $value['password']
                            );
                        } else {
                            $value = null;
                        }
                        break;
                    case 'o:referrer':
                        // Use substr: headings should be us-ascii.
                        $value = substr((string) $currentRequest['referrer'], 0, 1024);
                        break;
                    case 'o:user_agent':
                        $value = substr((string) $currentRequest['user_agent'], 0, 1024);
                        break;
                    case 'o:accept_language':
                        $value = substr((string) $currentRequest['accept_language'], 0, 190);
                        break;
                    case 'o:created':
                        $value = new DateTime('now');
                        break;
                    default:
                        $value = null;
                        break;
                }
            }
            $result[$keyName] = $value;
        }
        return $result;
    }

    /**
     * Adapted
     * @see \AnalyticsSnippet\Tracker\HitData::getCurrentRequest()
     * @see \Statistics\Api\Adapter\HitAdapter::currentRequest()
     */
    protected function currentRequest(): array
    {
        /**
         * @var \Laminas\Mvc\MvcEvent $event
         * @var \Laminas\Http\PhpEnvironment\Request $request
         */
        $services = $this->getServiceLocator();
        $event = $services->get('Application')->getMvcEvent();
        $request = $event->getRequest();
        $currentUrl = $request->getRequestUri();

        // Remove the base path, that is useless.
        $basePath = $request->getBasePath();
        if ($basePath && $basePath !== '/') {
            $start = substr($currentUrl, 0, strlen($basePath));
            // Manage specific paths for files.
            if ($start === $basePath) {
                $currentUrl = substr($currentUrl, strlen($basePath));
            }
        }

        // The restricted files are redirected from .htaccess, so it is useless
        // to store the path "/access/" (module Access).
        if (substr($currentUrl, 0, 8) === '/access/') {
            $currentUrl = substr($currentUrl, 7);
        }
        // The downloaded files are redirected from .htaccess, so it is useless
        // to store the path "/download/".
        elseif (substr($currentUrl, 0, 10) === '/download/') {
            $currentUrl = substr($currentUrl, 9);
        }

        $pos = strpos($currentUrl, '?');
        if ($pos !== false) {
            $currentUrl = substr($currentUrl, 0, $pos);
        }

        // Same query via laminas (as string).
        // $query = $request->getUri()->getQuery();
        $query = $_SERVER['QUERY_STRING'] ?? '';
        // $query = strlen($query) ? urldecode($query) : null;
        // Use "_get" instead of "_request" to avoid to store the password (the
        // login form is a post).
        // $query = $_SERVER['_GET'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        return [
            'url' => $currentUrl,
            'query' => empty($query) ? null : $query,
            'referrer' => empty($referrer) ? null : (string) $referrer,
            'user_agent' => empty($userAgent) ? null : (string) $userAgent,
            'accept_language' => empty($acceptLanguage) ? null : (string) $acceptLanguage,
        ];
    }

    /**
     * Get the name and id of the current entity and the site id from the route.
     *
     * An entity is commonly a resource (item, item set, media) or a site page.
     *
     * The filter event "stats_resource" from Omeka classic is useless now.
     */
    protected function currentDataFromRoute(): array
    {
        /**
         * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Laminas\Mvc\MvcEvent $event
         */
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $event = $services->get('Application')->getMvcEvent();
        $routeMatch = $event->getRouteMatch();
        $routeName = $routeMatch->getMatchedRouteName();
        $routeParams = $routeMatch->getParams();

        // Get site id first. The site id may be added by module CleanUrl.
        if (!empty($routeParams['site_id'])) {
            $siteId = (int) $routeParams['site_id'];
        } elseif (!empty($routeParams['site-slug'])) {
            // Most of the time, the site is already or will be loaded, so it is
            // already fetched by the entity manager.
            $siteId = $entityManager->getRepository(\Omeka\Entity\Site::class)
                ->findOneBy(['slug' => $routeParams['site-slug']]);
            $siteId = $siteId ? (int) $siteId->getId() : null;
        } else {
            $siteId = null;
        }

        $result = [
            'site_id' => $siteId,
            'name' => null,
            'id' => null,
        ];

        $controllerName = $routeParams['__CONTROLLER__'] ?? $routeParams['controller'] ?? $routeParams['resource'] ?? null;
        if (!$controllerName) {
            return $result;
        }

        if ($controllerName === 'Access\Controller\AccessFileController'
            || $controllerName === 'AccessResource\Controller\AccessFileController'
            || $controllerName === 'Access'
            || $controllerName === 'AccessResource'
            || $controllerName === 'Download'
        ) {
            $result['id'] = $this->currentMediaId($routeParams);
            if ($result['id']) {
                $result['name'] = 'media';
            }
            return $result;
        }

        // TODO Get the full mapping from controllers to api names.
        $controllerToNames = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'site_page' => 'site_pages',
            'annotation' => 'annotations',
            'Item' => 'items',
            'ItemSet' => 'item_sets',
            'Media' => 'media',
            'SitePage' => 'site_pages',
            'Annotation' => 'annotations',
            'Omeka\Controller\Site\Item' => 'items',
            'Omeka\Controller\Site\ItemSet' => 'item_sets',
            'Omeka\Controller\Site\Media' => 'media',
            'Omeka\Controller\Site\Page' => 'site_pages',
            'Annotate\Controller\Site\Annotation' => 'annotations',
            'contribution' => 'contributions',
        ];

        // Routes are used when the same controller is used for multiple
        // resource names.
        $routeToNames = [
            // Module IIIF Server.
            'iiifserver/id' => 'items',
            'iiifserver/manifest' => 'items',
            'iiifserver/collection' => 'item_sets',
            'iiifserver/collection-manifest' => 'item_sets',
            // Query in fact.
            // 'iiifserver/set' => null,
            'mediaserver/id' => 'media',
            'mediaserver/info' => 'media',
            'mediaserver/media' => 'media',
            'mediaserver/media-bad' => 'media',
            'mediaserver/placeholder' => 'media',
            // Module Image Server.
            'imageserver/id' => 'media',
            'imageserver/info' => 'media',
            'imageserver/media' => 'media',
            'imageserver/media-bad' => 'media',
            'imageserver/placeholder' => 'media',
        ];

        if (isset($controllerToNames[$controllerName])) {
            $resourceName = $controllerToNames[$controllerName];
        } elseif (isset($routeToNames[$routeName])) {
            $resourceName = $routeToNames[$routeName];
        // } elseif ($controllerName === 'GuestBoard') {
        //     return $result;
        // } elseif (strpos($controllerName, '\\')) {
        //     // This is an unknown controller.
        //     return $result;
        } elseif ($routeName === 'api/default'
            // TODO Route "api-local/default" is normally used only in admin, so not stored for stats.
            // || $routeName === 'api-local/default'
        ) {
            // Continue below.
            $resourceName = $routeParams['resource'] ?? null;
        } else {
            return $result;
        }

        // Manage exception for item sets (the item set id is get below).
        if ($resourceName === 'items'
            && $routeName !== 'api/default'
            && ($routeParams['action'] ?? 'browse') === 'browse'
        ) {
            $resourceName = 'item_sets';
        }

        // Check for a resource (item, item set, media).
        $id = $routeParams['id']
            ?? $routeParams['resource-id']
            ?? $routeParams['media-id']
            ?? $routeParams['item-id']
            ?? $routeParams['item-set-id']
            ?? null;
        if ($id) {
            $result['name'] = $resourceName;
            $result['id'] = $id;
            return $result;
        }

        // Check for a site page.
        // Get site id first. The site id may be added by module CleanUrl.
        if (!empty($routeParams['site_page_id'])) {
            $result['name'] = 'site_pages';
            $result['id'] = (int) $routeParams['site_page_id'];
        } elseif (!empty($routeParams['page-slug'])) {
            // The page is already or will be loaded, so it is already fetched.
            $pageId = $entityManager->getRepository(\Omeka\Entity\SitePage::class)
                ->findOneBy(['slug' => $routeParams['page-slug']]);
            if ($pageId) {
                $result['name'] = 'site_pages';
                $result['id'] = (int) $pageId;
            }
        }

        return $result;
    }

    protected function currentMediaId(array $params): ?int
    {
        if (empty($params['type']) || empty($params['filename'])) {
            return null;
        }

        // For compatibility with module ArchiveRepertory, don't take the
        // filename, but remove the extension.
        // $storageId = pathinfo($filename, PATHINFO_FILENAME);
        $filename = (string) $params['filename'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $storageId = mb_strlen($extension)
            ? mb_substr($filename, 0, -mb_strlen($extension) - 1)
            : $filename;

        // "storage_id" is not available through default api, so use core entity
        // manager. Nevertheless, the call to the api allows to check rights.
        if (!$storageId) {
            return null;
        }

        try {
            $media = $this->getAdapter('media')->findEntity(['storageId' => $storageId]);
            return $media->getId();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return null;
        }
    }

    protected function currentUser(): ?User
    {
        return $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
    }

    /**
     * Determine whether or not the hit is from a bot/webcrawler
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
     * Get the ip of the client.
     *
     * @todo Use the laminas http function.
     */
    public function getClientIp(): string
    {
        // Some servers add the real ip.
        $ip = $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR'];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            return $ip;
        }
        return '::';
    }

    /**
     * Manage privacy settings for an ip address.
     *
     * @todo Fix for ipv6.
     */
    public function privacyIp(?string $ip = null): string
    {
        if (is_null($ip)) {
            $ip = $this->getClientIp();
        }

        if (!$ip || $ip === '::') {
            return '::';
        }

        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        switch ($settings->get('statistics_privacy')) {
            default:
            case 'anonymous':
                return '::';
            case 'hashed':
                return md5($ip);
            case 'partial_1':
                $partial = explode('.', $ip);
                $partial[1] = '---';
                $partial[2] = '---';
                $partial[3] = '---';
                return implode('.', $partial);
            case 'partial_2':
                $partial = explode('.', $ip);
                $partial[2] = '---';
                $partial[3] = '---';
                return implode('.', $partial);
            case 'partial_3':
                $partial = explode('.', $ip);
                $partial[3] = '---';
                return implode('.', $partial);
            case 'clear':
                return $ip;
        }
    }
}
