<?php declare(strict_types=1);

namespace Shortcode\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Shortcode\Shortcode\Manager as ShortcodeManager;

class Shortcodes extends AbstractHelper
{
    /**
     * @var \Shortcode\Shortcode\Manager
     */
    protected $shortcodeManager;

    /**
     * @param array
     */
    protected $shortcodes;

    public function __construct(ShortcodeManager $shortcodeManager)
    {
        $this->shortcodeManager = $shortcodeManager;
        $this->shortcodes = $shortcodeManager->getRegisteredNames();
    }

    /**
     * Render all shortcodes present in a string.
     *
     * @link https://core.trac.wordpress.org/browser/tags/5.8/src/wp-includes/shortcodes.php
     * @link https://github.com/omeka/Omeka/blob/master/application/views/helpers/Shortcodes.php
     */
    public function __invoke($content): string
    {
        // Quick check.
        if (mb_strpos($content, '[') === false) {
            return $content;
        }

        // WordPress pattern handles more cases but it is too much complex.
        // Furthermore, it requires a "/" and may be divided in two parts.

        // Process all strings looking like a shortcode in all the content.
        $pattern = '/\[(\w+)\s*([^\]]*)\]/s';
        return preg_replace_callback($pattern, [$this, 'handleShortcode'], $content);
    }

    /**
     * Parse a detected shortcode and replace it with its actual content, or return it unchanged.
     *
     * @link https://github.com/omeka/Omeka/blob/master/application/views/helpers/Shortcodes.php
     */
    protected function handleShortcode(array $matches): string
    {
        $shortcodeName = $matches[1];
        if (!in_array($shortcodeName, $this->shortcodes)) {
            return $matches[0];
        }

        $args = $this->parseShortcodeAttributes($matches[2]);

        return $this->shortcodeManager
            ->get($shortcodeName)
            ->setShortcodeName($shortcodeName)
            ->setView($this->view)
            ->render($args);
    }

    /**
     * Retrieve all attributes from the shortcodes tag as an associative array.
     *
     * Attributes without keys have numerical keys in the array.
     *
     * It supports html encoded single and double quotes.
     *
     * @link https://core.trac.wordpress.org/browser/tags/5.8/src/wp-includes/shortcodes.php
     * @link https://github.com/omeka/Omeka/blob/master/application/views/helpers/Shortcodes.php
     *
     * @return array Unlike WordPress, always return an array. The attributes
     * without keys are listed as numeric.
     */
    protected function parseShortcodeAttributes(string $attributes): array
    {
        $attributes = trim($attributes);
        if (!strlen($attributes)) {
            return [];
        }

        $args = [];

        // From WordPress. Fix special spaces.
        $attributes = preg_replace('/[\x{00a0}\x{200b}]+/u', ' ', $attributes);

        // The html text area converts single and double quotes into entities.
        // They are replaced before with control characters and reset after to
        // manage it. It handles most of the real life cases (except ocr, but it
        // will be rare that a random string looks like a shortcode).
        $attributes = str_replace(['&quot;', '&#39;'], [chr(2), chr(3)], $attributes);
        $pattern = '/(?J)'
            // key="val ue"
            . '(?<key>[\w-]+)\s*=\s*"(?<value>[^"]*)"(?:\s|$)'
            // key='val ue'
            . '|(?<key>[\w-]+)\s*=\s*\'(?<value>[^\']*)\'(?:\s|$)'
            // key=val
            . '|(?<key>[\w-]+)\s*=\s*(?<value>[^\s\'"\x02\x03]+)(?:\s|$)'

            // Manage html entities for single and double quotes.
            // key=&quot;val ue&quot;
            . '|(?<key>[\w-]+)\s*=\s*\x02(?<value>[^\x02]*)\x02(?:\s|$)'
            // key=&#39;val ue&#39;
            . '|(?<key>[\w-]+)\s*=\s*\x03(?<value>[^\x03]*)\x03(?:\s|$)'

            // "val ue"
            . '|"(?<value>[^"]*)"(?:\s|$)'
            // 'val ue'
            . '|\'(?<value>[^\']*)\'(?:\s|$)'

            // &quot;val ue&quot;
            . '|\x02(?<value>[^\x02]*)\x02(?:\s|$)'
            // &#39;val ue&#39;
            . '|\x03(?<value>[^\x03]*)\x03(?:\s|$)'

            // val
            . '|(?<value>\S+)(?:\s|$)'
            . '/';

        $matches = [];
        if (preg_match_all($pattern, $attributes, $matches, PREG_SET_ORDER, 0)) {
            foreach ($matches as $m) {
                $value = $m['value'];
                if (strlen($value) > 1) {
                    $first = mb_substr($value, 0, 1);
                    $last = mb_substr($value, 0, -1);
                    if (($first === chr(2) && $last === chr(2))
                      || ($first === chr(3) && $last === chr(3))
                    ) {
                        $value = mb_substr($value, 1, -1);
                    }
                }
                $value = stripcslashes(str_replace([chr(2), chr(3)], ['&quot;', '&#39;'], $value));
                if ($m['key']) {
                    $args[strtolower($m['key'])] = $value;
                } else {
                    $args[] = $value;
                }
            }

            // Reject any unclosed HTML elements.
            foreach ($args as &$value) {
                if (strpos($value, '<') !== false
                    && preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value) !== 1
                ) {
                    $value = '';
                }
            }
        } else {
            $args = [
                ltrim($attributes),
            ];
        }

        return $args;
    }
}
