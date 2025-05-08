<?php declare(strict_types=1);

namespace Statistics\Mvc\Controller\Plugin;

use Interop\Container\ContainerInterface;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Request;
use Statistics\Entity\Hit;

/**
 * Adapted:
 * @see \Statistics\Mvc\MvcListeners
 * @see \Statistics\Mvc\Controller\Plugin\LogCurrentUrl
 */
class LogCurrentUrl extends AbstractPlugin
{
    /**
     * @var \Interop\Container\ContainerInterface
     */
    protected $services;

    public function __construct(ContainerInterface $services)
    {
        $this->services = $services;
    }

    /**
     * Log the current public url.
     *
     * Note: normally, this plugin should not be used because all requests are
     * now managed via the MVC event "Finish", that is triggered in all cases.
     *
     * @see \Statistics\Mvc\MvcListeners
     */
    public function __invoke(): ?Hit
    {
        // Don't store server ping or internal redirect on root or some proxies.
        if (empty($_SERVER['HTTP_HOST'])
            // || ($_SERVER['REQUEST_URI'] === '/' && $_SERVER['QUERY'] === '' && $_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['CONTENT_LENGTH'] === '0')
        ) {
            return null;
        }

        // TODO Log first access to a file in all cases, included when the user does not start at the beginning of a video.

        // Log the statistic for the url even if the file is missing or protected.
        // Log file access only for the first request.
        $hasRange = !empty($_SERVER['HTTP_RANGE'])
            && $_SERVER['HTTP_RANGE'] !== 'bytes=0-';
        if ($hasRange) {
            return;
        }

        // Don't log admin pages.

        /** @var \Omeka\Mvc\Status $status */
        $status = $this->services->get('Omeka\Status');

        if ($status->isAdminRequest()) {
            return null;
        }

        $routeMatch = $status->getRouteMatch();
        $routeName = $routeMatch->getMatchedRouteName();

        // Don't log some routes.
        if (in_array($routeName, [
            // Omeka S.
            'install',
            'migrate',
            'login',
            'logout',
            'create-password',
            'forgot-password',
            // Module CSS Editor.
            'site/css-editor',
        ])) {
            return null;
        }

        // Don't log download request for admin users but non-admins and guests.
        // It's not simple to determine from server if the request comes from a
        // visitor on the site or something else. So use referrer and identity.
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        if ($referrer
            // Guest user should not be logged.
            && strpos($referrer, '/admin/')
            && in_array($routeName, ['access-file', 'access-resource-file', 'download'])
            // Only check if there is a user: no useless check for users who
            // can't go admin (guest), and checked below anyway.
            && $this->services->get('Omeka\AuthenticationService')->getIdentity()
            // Slower but manage extra roles and modules permissions.
            // && in_array($user->getRole(), ['global_admin', 'site_admin', 'editor', 'reviewer']);
            // Guests don't have the right to view all resources, neither author
            // and researcher.
            && $this->services->get('Omeka\Acl')->userIsAllowed(\Omeka\Entity\Resource::class, 'view-all')
        ) {
            $urlAdminTop = $this->services->get('ControllerPluginManager')->get('url')->fromRoute('admin', [], ['force_canonical' => true]) . '/';
            if (strpos($referrer, $urlAdminTop) === 0) {
                return null;
            }
        }

        // For performance, use the adapter directly, not the api.
        // TODO Use direct sql query to store hits?

        /** @var \Statistics\Api\Adapter\HitAdapter $hitAdapter */
        $hitAdapter = $this->services->get('Omeka\ApiAdapterManager')->get('hits');

        $includeBots = (bool) $this->services->get('Omeka\Settings')->get('statistics_include_bots');
        if (empty($includeBots)) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($userAgent && $hitAdapter->isBot($userAgent)) {
                return null;
            }
        }

        $request = new Request(Request::CREATE, 'hits');
        $request
            ->setOption('initialize', false)
            ->setOption('finalize', false)
        ;
        try {
            // The entity manager is automatically flushed by default.
            return $hitAdapter->create($request)->getContent();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // An issue may occur, so skip when:
            // - the controller is not found for an image;
            // - the same image is loaded multiple times on the same page.
            return null;
        } catch (\Exception $e) {
            $logger = $this->services->get('Omeka\Logger');
            $logger->err(new \Omeka\Stdlib\Message(
                'Exception when storing hit/stat: %1$s', // @translate
                $e->getMessage()
            ));
            return null;
        }
    }
}
