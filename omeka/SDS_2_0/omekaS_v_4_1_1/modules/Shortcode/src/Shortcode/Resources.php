<?php declare(strict_types=1);

namespace Shortcode\Shortcode;

class Resources extends AbstractShortcode
{
    /**
     * @link https://github.com/omeka/Omeka/blob/master/application/views/helpers/Shortcodes.php
     *
     * {@inheritDoc}
     * @see \Shortcode\Shortcode\AbstractShortcode::render()
     */
    public function render(?array $args = null): string
    {
        // It's not possible to search resources for now, so use items.
        $shortcodeToResources = [
            'annotations' => 'annotations',
            'assets' => 'assets',
            'collections' => 'item_sets',
            'items' => 'items',
            'item_sets' => 'item_sets',
            'media' => 'media',
            'medias' => 'media',
            'pages' => 'site_pages',
            'resources' => 'items',
            'site_pages' => 'site_pages',
            'sites' => 'sites',

            'featured_annotations' => 'annotations',
            'featured_collections' => 'item_sets',
            'featured_item_sets' => 'item_sets',
            'featured_items' => 'items',
            'featured_media' => 'media',
            'featured_medias' => 'media',
            'featured_resources' => 'items',

            'recent_annotations' => 'annotations',
            'recent_collections' => 'item_sets',
            'recent_item_sets' => 'item_sets',
            'recent_items' => 'items',
            'recent_media' => 'media',
            'recent_medias' => 'media',
            'recent_resources' => 'items',
        ];

        $resourceName = $shortcodeToResources[$this->shortcodeName] ?? null;
        if (!$resourceName) {
            return '';
        }

        // Manage shortcuts from Omeka Classic.
        $recents = [
            'recent_annotations',
            'recent_collections',
            'recent_item_sets',
            'recent_items',
            'recent_media',
            'recent_medias',
            'recent_resources',
        ];
        $featureds = [
            'featured_annotations',
            'featured_collections',
            'featured_item_sets',
            'featured_items',
            'featured_media',
            'featured_medias',
            'featured_resources',
        ];
        if (in_array($this->shortcodeName, $recents)) {
            if (!isset($args['num'])) {
                $args['num'] = '5';
            }
            $args['sort'] = 'created';
            $args['order'] = 'desc';
        } elseif (in_array($this->shortcodeName, $featureds)) {
            $args['is_featured'] = '1';
            if (!isset($args['num'])) {
                $args['num'] = '1';
            }
            $args['sort'] = 'random';
        }

        // By default the ten oldest resources.
        if (empty($args)) {
            $args = [
                'sort' => 'created',
                'order' => 'asc',
            ];
        }

        $query = $this->apiQuery($args);

        $resources = $this->view->api()->search($resourceName, $query)->getContent();

        $resourceTemplates = [
            'annotations' => 'annotations',
            'assets' => 'assets',
            'items' => 'items',
            'item_sets' => 'item-sets',
            'media' => 'medias',
            'resources' => 'resources',
            'site_pages' => 'pages',
            'sites' => 'sites',
        ];

        $partial = $this->getViewTemplate($args) ?? 'common/shortcode/' . $resourceTemplates[$resourceName];
        return $this->view->partial($partial, [
            'resources' => $resources,
            $this->resourceVars[$resourceName] => $resources,
            'resourceName' => $resourceName,
            'resourceType' => $this->resourceTypes[$resourceName],
            'options' => $args,
        ]);
    }
}
