<?php declare(strict_types=1);

namespace Shortcode\Shortcode;

class Count extends AbstractShortcode
{
    /**
     * @link https://github.com/omeka/Omeka/blob/master/application/views/helpers/Shortcodes.php
     *
     * {@inheritDoc}
     * @see \Shortcode\Shortcode\AbstractShortcode::render()
     */
    public function render(?array $args = null): string
    {
        $span = empty($args['span']) ? false : $this->view->escapeHtmlAttr($args['span']);

        $partial = $this->getViewTemplate($args);

        if (empty($args['resource'])
            || !isset($this->resourceNames[$args['resource']])
            // TODO Support count of "resources".
            || $this->resourceNames[$args['resource']] === 'resources'
        ) {
            if ($partial) {
                return $this->view->partial($partial, [
                    'resourceType' => null,
                    'count' => 0,
                    'options' => $args,
                ]);
            }
            return $span
                ? '<span class="' . $span . '">0</span>'
                : '0';
        }

        $resourceName = $this->resourceNames[$args['resource']];

        $query = $this->apiQuery($args);

        unset(
            $query['page'],
            $query['per_page'],
            $query['offset'],
            $query['limit'],
            $query['sort_by'],
            $query['sort_order']
        );

        // Speed the computation for count via api.
        $query['limit'] = 0;

        $total = (string) $this->view->api()->search($resourceName, $query)->getTotalResults();

        if ($partial) {
            return $this->view->partial($partial, [
                'resourceName' => $resourceName,
                'resourceType' => $this->resourceTypes[$resourceName],
                'count' => $total,
                'options' => $args,
            ]);
        }

        return $span
            ? '<span class="' . $span . '">' . $total . '</span>'
            : $total;
    }
}
