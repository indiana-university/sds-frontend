<?php declare(strict_types=1);

namespace Shortcode\Shortcode;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;

abstract class AbstractShortcode implements ShortcodeInterface
{
    /**
     * @var string
     */
    protected $shortcodeName;

    /**
     * @var \Laminas\View\Renderer\PhpRenderer
     */
    protected $view;

    /**
     * @var array
     */
    protected $resourceNames = [
        'annotation' => 'annotations',
        'annotations' => 'annotations',
        'asset' => 'assets',
        'assets' => 'assets',
        'item' => 'items',
        'items' => 'items',
        'item_set' => 'item_sets',
        'item_sets' => 'item_sets',
        'media' => 'media',
        'medias' => 'media',
        'resource' => 'resources',
        'resources' => 'resources',
        'site_pages' => 'site_pages',
        'site' => 'sites',
        'sites' => 'sites',
        'value_annotation' => 'value_annotations',
        'value_annotations' => 'value_annotations',
        // Aliases.
        'collection' => 'item_sets',
        'collections' => 'item_sets',
        'page' => 'site_pages',
        'pages' => 'site_pages',
    ];

    /**
     * @var array
     */
    protected $resourceVars = [
        'annotations' => 'annotations',
        'assets' => 'assets',
        'items' => 'items',
        'item_sets' => 'itemSets',
        'media' => 'medias',
        'resources' => 'resources',
        'site_pages' => 'site_pages',
        'sites' => 'sites',
        'value_annotations' => 'value_annotations',
    ];

    /**
     * @var array
     */
    protected $resourceTypes = [
        'annotations' => 'annotation',
        'assets' => 'asset',
        'items' => 'item',
        'item_sets' => 'item-set',
        'media' => 'media',
        'resources' => 'resource',
        'site_pages' => 'page',
        'sites' => 'site',
        'value_annotations' => 'value_annotation',
    ];

    public function setShortcodeName(string $shortcodeName): ShortcodeInterface
    {
        $this->shortcodeName = $shortcodeName;
        return $this;
    }

    public function setView(PhpRenderer $view): ShortcodeInterface
    {
        $this->view = $view;
        return $this;
    }

    abstract public function render(array $args = []): string;

    protected function renderMeta(AbstractEntityRepresentation $resource, array $args = []): string
    {
        if (empty($args['meta'])) {
            return '';
        }

        $meta = $args['meta'];

        if (!strpos($meta, ':') && strpos($meta, '@') === false) {
            $meta = 'o:' . $meta;
        }

        $jsonLd = $resource->getJsonLd();
        if (!isset($jsonLd[$meta])) {
            // For compatibility with Omeka Classic.
            if ($meta === 'o:added' && isset($jsonLd['o:created'])) {
                $meta = 'o:created';
            } elseif ($meta === 'o:updated' && isset($jsonLd['o:modified'])) {
                $meta = 'o:modified';
            }
            // Fix specific names to avoid common issues.
            elseif ($meta === 'o:description'
                && $resource instanceof \Omeka\Api\Representation\SiteRepresentation
            ) {
                $meta = 'o:summary';
            } elseif ($meta === 'o:title'
                && $resource instanceof \Omeka\Api\Representation\AssetRepresentation
            ) {
                $meta = 'o:name';
            } elseif ($meta === 'o:description'
                && $resource instanceof \Omeka\Api\Representation\AssetRepresentation
            ) {
                $meta = 'o:alt_text';
            } else {
                return '';
            }
        }

        $metadata = $jsonLd[$meta];

        if (is_array($metadata)) {
            $metadata = $metadata['@value'] ?? reset($metadata);
        }

        if (is_null($metadata) || $metadata === '') {
            return '';
        }

        $plugins = $this->view->getHelperPluginManager();
        $escape = $plugins->get('escapeHtml');

        $hasSpan = array_key_exists('span', $args);

        if (is_scalar($metadata)) {
            return $hasSpan
                ? $this->wrapSpan($escape($metadata), $args['span'])
                : $escape($metadata);
        }

        // JsonLd() is a list of objects, unlike json_decode().
        if (!is_object($metadata)) {
            return '';
        }

        if (method_exists($metadata, 'asHtml')) {
            $metadata = (string) $metadata->asHtml();
            return $hasSpan
                ? $this->wrapSpan($metadata, $args['span'], false)
                : $metadata;
        }

        if (method_exists($metadata, '__toString')) {
            $metadata = (string) $metadata;
        } elseif ($metadata instanceof \Omeka\Stdlib\DateTime) {
            $metadata = $this->view->i18n()->dateFormat($metadata->getDateTime(), $args['date'] ?? null, $args['time'] ?? null);
        } elseif ($metadata instanceof \DateTime) {
            $metadata = $this->view->i18n()->dateFormat($metadata, $args['date'] ?? null, $args['time'] ?? null);
        } elseif (method_exists($metadata, 'displayTitle')) {
            $metadata = $metadata->displayTitle();
        } elseif (method_exists($metadata, 'title')) {
            $metadata = $metadata->title();
        } elseif (method_exists($metadata, 'label')) {
            $metadata = $metadata->label();
        } else {
            return '';
        }

        return $hasSpan
            ? $this->wrapSpan($escape($metadata), $args['span'])
            : $escape($metadata);
    }

    /**
     * Wrap a string with a span and optionally a class.
     *
     * @param string $hml The value should be escaped if needed.
     * @param string $class
     * @return string
     */
    protected function wrapSpan(string $html, ?string $class = null): string
    {
        if (is_null($class) || trim($class) === '') {
            return '<span>'
                . $html
                . '</span>';
        }
        $plugins = $this->view->getHelperPluginManager();
        $escapeAttr = $plugins->get('escapeHtmlAttr');
        return '<span class="' . $escapeAttr($class) . '">'
            . $html
            . '</span>';
    }

    /**
     * Check if a value is a boolean.
     *
     * For api, it is recommended to use boolean().
     */
    protected function bool(string $value): bool
    {
        return !in_array(strtolower($value), ['0', 'false'], true);
    }

    /**
     * Check if a value is a boolean and return "0" or "1".
     *
     * The casting is required to keep at least one character to the value, else
     * it will be managed as a null in the api.
     */
    protected function boolean(string $value): int
    {
        return (int) !in_array(strtolower($value), ['0', 'false'], true);
    }

    /**
     * Get a list of integers from a string with comma-separated values or range.
     *
     * @return int[]
     */
    protected function listIds(string $value): array
    {
        if (strpos($value, ',') === false) {
            if (strpos($value, '-') === false) {
                return [(int) $value];
            }
            [$from, $to] = explode('-', $value);
            $from = (int) $from;
            $to = (int) $to;
            return range(min($from, $to), max($from, $to));
        }
        return array_map('intval', explode(',', $value));
    }

    /**
     * Get a list of integers from a string with comma-separated values.
     *
     * @return int[]|int A single value can be returned for perfomance.
     */
    protected function singleOrListIds(string $value)
    {
        return strpos($value, ',') === false
            ? (int) $value
            : array_map('intval', explode(',', $value));
    }

    /**
     * Get a list of terms or ids from a string with comma-separated values.
     */
    protected function listTermsOrIds(string $value): array
    {
        return strpos($value, ',') === false
            ? [$value]
            : array_map('trim', explode(',', $value));
    }

    /**
     * Get a list of terms or ids from a string with comma separated values.
     *
     * @return array|string A single value can be returned for perfomance.
     */
    protected function singleOrListTermsOrIds(string $value)
    {
        return strpos($value, ',') === false
            ? $value
            : array_map('trim', explode(',', $value));
    }

    protected function getViewTemplate(array $args): ?string
    {
        if (isset($args['view']) && strpos($args['view'], '.') === false) {
            $partial = 'common/shortcode/' . $args['view'];
            return $this->view->resolver($partial) ? $partial : null;
        }
        return null;
    }

    protected function currentSiteId(): ?int
    {
        static $siteId;
        if (is_null($siteId)) {
            $site = $this->view->site ?? $this->view->site = $this->view
                ->getHelperPluginManager()
                ->get('Laminas\View\Helper\ViewModel')
                ->getRoot()
                ->getVariable('site');
            $siteId = $site ? $site->id() : 0;
        }
        return $siteId ?: null;
    }

    protected function apiQuery(array $args): array
    {
        // Don't check or cast data here but in api.

        $query = [];

        if (!empty($args['query'])) {
            parse_str(ltrim($args['query'], "? \t\n\r\0\x0B"), $query);
        }

        if (isset($args['site'])) {
            $query['site_id'] = $args['site'];
        } else {
            // Force the current site by default (null is skipped by api).
            $query['site_id'] = $this->currentSiteId();
        }

        /** @deprecated "ids" is deprecated, use singular "id". */
        if (isset($args['id'])) {
            $query['id'] = $this->listIds($args['id']);
        } elseif (isset($args['ids'])) {
            $query['id'] = $this->listIds($args['ids']);
        }

        /** @deprecated "user" is deprecated, use "owner". */
        if (isset($args['owner'])) {
            $query['owner_id'] = $args['owner'];
        } elseif (isset($args['user'])) {
            $query['owner_id'] = $args['user'];
        }

        // "collection" is an alias of "item_set".
        if (isset($args['item_set'])) {
            $query['item_set_id'] = $this->listIds($args['item_set']);
        } elseif (isset($args['collection'])) {
            $query['item_set_id'] = $this->listIds($args['collection']);
        }

        if (isset($args['class'])) {
            $query['resource_class_term'] = $this->listTermsOrIds($args['class']);
        }

        /** @deprecated "item_type" is deprecated, use "class_label" or"class". */
        if (isset($args['class_label'])) {
            $query['resource_class_label'] = $args['class_label'];
        } elseif (isset($args['item_type'])) {
            $query['resource_class_label'] = $args['item_type'];
        }

        if (isset($args['template'])) {
            $query['resource_template_id'] = $this->listIds($args['template']);
        }

        if (isset($args['template_label'])) {
            $query['resource_template_label'] = $args['template_label'];
        }

        // Require module AdvancedSearch.
        if (isset($args['tag'])) {
            $query['property'][] = [
                'property' => 'curation:tag',
                'joiner' => 'and',
                'type' => 'list',
                'text' => array_map('trim', explode(',', $args['tag'])),
            ];
        } elseif (isset($args['tags'])) {
            $query['property'][] = [
                'property' => 'curation:tag',
                'joiner' => 'and',
                'type' => 'list',
                'text' => array_map('trim', explode(',', $args['tags'])),
            ];
        }

        if (isset($args['is_featured'])) {
            $isFeatured = $this->boolean($args['is_featured']);
            $query['property'][] = [
                'property' => 'curation:featured',
                'joiner' => 'and',
                'type' => $isFeatured ? 'ex' : 'nex',
            ];
        }

        // Require module AdvancedSearch.
        if (isset($args['has_image'])) {
            $query['has_thumbnails'] = $this->boolean($args['has_image']);
        }

        // Require module AdvancedSearch.
        if (isset($args['has_media'])) {
            $query['has_media'] = $this->boolean($args['has_media']);
        }

        if (isset($args['sort'])) {
            $query['sort_by'] = $args['sort'] === 'added' ? 'created' : $args['sort'];
        }

        if (isset($args['order'])) {
            $query['sort_order'] = in_array(strtolower($args['order']), ['d', 'desc']) ? 'desc' : 'asc';
        }

        if (isset($args['num'])) {
            $limit = (int) $args['num'];
            // Unlike Omeka classic, the results are unlimited by default, so
            // "0" means "0".
            if ($limit > 0) {
                $query['limit'] = $limit;
            }
        } else {
            $query['limit'] = 10;
        }

        return $query;
    }
}
