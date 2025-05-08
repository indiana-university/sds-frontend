<?php

namespace OIDC;

use Omeka\Module\AbstractModule;
use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    /** Module body **/

    /**
     * Get this module's configuration array.
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
	    parent::onBootstrap($event);
	    $acl = $this->getServiceLocator()->get('Omeka\Acl');
	    $acl->allow(null, 'OIDC\Controller\OIDCController');

	    require_once __DIR__ . '/vendor/autoload.php';
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator) {
        $omekaSettings = $this->getServiceLocator()->get('Omeka\Settings');
        $omekaSettings->delete('oidc_discovery');
        $omekaSettings->delete('oidc_role');
        $omekaSettings->delete('oidc_site');
    }

    public function handleConfigForm(AbstractController $controller) {
        //Grab the current settings for Omeka
        $omekaSettings = $this->getServiceLocator()->get('Omeka\Settings');

        //Fetch the OIDCForm from the form element manager
        $formElementManager = $this->getServiceLocator()->get('FormElementManager');
        $form = $formElementManager->get('OIDC\Form\OIDCForm');

        //Validation
        $form->setData($controller->params()->fromPost());
        if (! $form->isValid()) {
            return False;
        }

        //Update Omeka settings
        $OIDCConfig = $form->getData();
        $omekaSettings->set('oidc_discovery', $OIDCConfig['oidc_discovery']);
	    //$omekaSettings->set('oidc_role', $OIDCConfig['oidc_role']);
        //$omekaSettings->set('oidc_site', $OIDCConfig['oidc_site']);
    }

    public function getConfigForm(PhpRenderer $renderer) {
        //Grab the current settings for Omeka
        $omekaSettings = $this->getServiceLocator()->get('Omeka\Settings');

        //Fetch the OIDCForm from the form element manager
        $formElementManager = $this->getServiceLocator()->get('FormElementManager');
        $form = $formElementManager->get('OIDC\Form\OIDCForm');
	    $form->setData([
            'oidc_discovery' => $omekaSettings->get('oidc_discovery'),
            //'oidc_role' => $omekaSettings->get('oidc_role'),
            //'oidc_site' => $omekaSettings->get('oidc_site')
        ]);

        //Return the form using the provided renderer
        return $renderer->formCollection($form, false);
    }
}
