<?php declare(strict_types=1);

namespace Statistics\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Helper to count total results of all resources.
 *
 * This feature is not supported currently by official release of Omeka, so sum
 * each resource type.
 *
 * @see https://github.com/omeka/omeka-s/pull/1799
 */
class ApiResourcesTotalResults extends AbstractHelper
{
    /**
     * Count total results of all resources.
     */
    public function __invoke(array $query = []): int
    {
        $query['limit'] = 0;

        $resourceTypes = [
            'item_sets',
            'items',
            'media',
        ];

        $api = $this->getView()->plugin('api');

        $results = [];
        foreach ($resourceTypes as $resourceType) {
            $results[$resourceType] = $api->search($resourceType, $query, ['initialize' => false, 'finalize' => false])->getTotalResults();
        }

        return array_sum($results);
    }
}
