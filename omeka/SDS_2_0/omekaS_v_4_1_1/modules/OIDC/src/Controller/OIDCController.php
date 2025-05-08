<?php

namespace OIDC\Controller;

use DateTime;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Authentication\AuthenticationService;
use Laminas\Session\Container;
use Laminas\View\Helper\BasePath;
use Laminas\Log\Logger;
use Omeka\Entity\User;
use Omeka\Entity\SitePermission;
use Omeka\Permissions\Acl;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Service\Builder\UserInfoServiceBuilder;
use Facile\OpenIDClient\Token\TokenSet;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\ServerRequestFactory;
use function Facile\OpenIDClient\base64url_encode;

class OIDCController extends AbstractActionController
{
    protected $entityManager;
    protected $auth;
    protected $logger;
    private $basePath;
    private $redirect;
    private $authorizationService;
    private $config;

    public function __construct(EntityManager $entityManager, AuthenticationService $auth, BasePath $basePath, array $config, Logger $logger)
    {
        $this->entityManager = $entityManager;
        $this->auth = $auth;
        $this->basePath = $basePath;
        $this->redirect = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $basePath() . "/oidc/redirect";
        $this->authorizationService = (new AuthorizationServiceBuilder())->build();
        $this->config = $config;
        $this->logger = $logger;
    }

    public function loginAction()
    {
        if ($this->auth->hasIdentity()) {
            return $this->redirect()->toRoute('top');
        }

        $session = Container::getDefaultManager()->getStorage();
        $client = $this->getClient();
        $authorizationService = $this->authorizationService;

        // Authorization
        $state = base64url_encode(random_bytes(32));
        $nonce = base64url_encode(random_bytes(32));

        //State and nonce need to be in session for redirect
        $session->state = $state;
        $session->nonce = $nonce;

        //Use this uri to redirect the user for authN
        $redirectAuthorizationUri = $authorizationService->getAuthorizationUri(
            $client,
            [
                'login_hint' => 'username@example.com',
                'scope' => 'openid email',
                'nonce' => $nonce,
                'state' => $state,

            ] // custom params
        );
        return $this->redirect()->toUrl($redirectAuthorizationUri);
    }

    public function redirectAction()
    {
        $log = $this->logger();
        $client = $this->getClient();
        $session = Container::getDefaultManager()->getStorage();
        $authorizationService = $this->authorizationService;

        $request  = $this->request;
        $code = $request->getQuery('code');
        if (isset($code)) {
            $location = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $request->getUriString();
            $location = filter_var($location, FILTER_SANITIZE_URL);
            $serverRequestFactory = new ServerRequestFactory();
            $serverRequest = $serverRequestFactory->createServerRequest('GET', $location);

            $callbackParams = $authorizationService->getCallbackParams($serverRequest, $client);

            //Verify the state is the same one that we sent
            if($session->state != $callbackParams['state']) {
                $log->info("OIDC: Invalid state parameter on redirect");
                $this->redirect()->toRoute('top');
            }

            $tokenSet = $authorizationService->callback($client, $callbackParams);

            $expiration = time() + $tokenSet->getExpiresIn();
            $session->expiration_timestamp = $expiration;

            // Get user info
            $userInfoService = (new UserInfoServiceBuilder())->build();
            $userInfo = $userInfoService->getUserInfo($client, $tokenSet);
            $email = $userInfo['email'];
            //TODO: need to add some error checking here
            $sessionManager = Container::getDefaultManager();
            $user = $this->getUser($email);
            if (!isset($user)) {
                $log->info("OIDC: Invalid user (" . $user . ")");
                $this->redirect()->toRoute('top');
            }
            $sessionManager->regenerateId();
            $this->auth->getStorage()->write($user);
            $this->redirect()->toRoute('top');
        }
        else {
            $log->info("OIDC: Missing code on redirect");
            $this->redirect()->toRoute('top');
        }
    }

    protected function getUser($oidc)
    {
            $em = $this->entityManager;
            $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['email' => $oidc]);

            //Create user if they do not already exist in Omeka
            if (!$user)
            {
                $user = new User();
                $user->setName($oidc);
                $user->setEmail($oidc);
                $user->setRole($this->settings()->get('oidc_role', Acl::ROLE_RESEARCHER));
                $user->setIsActive(true);
                $dt = new DateTime('now');
                $user->setCreated($dt);
                $user->setModified($dt);
                $em->persist($user);
                $em->flush();
        
                //TODO: Add user to "public" site(s) if the setting exists
            }
            return $user;
    }

    protected function getClient()
    {
        $redirect = $this->redirect;
        $discoveryDocumentURI = $this->settings()->get('oidc_discovery');
        $issuer = (new IssuerBuilder())->build($discoveryDocumentURI);
	    $config = $this->config;

	    $clientId = $config['oidc']['client_id'];
	    $clientSecret = $config['oidc']['client_secret'];

        $clientMetadata = ClientMetadata::fromArray([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'token_endpoint_auth_method' => 'client_secret_basic', // the auth method for the token endpoint
            'redirect_uris' => [
                    $redirect,
            ],
        ]);

        $client = (new ClientBuilder())
            ->setIssuer($issuer)
            ->setClientMetadata($clientMetadata)
            ->build();
        return $client;
    }
}

