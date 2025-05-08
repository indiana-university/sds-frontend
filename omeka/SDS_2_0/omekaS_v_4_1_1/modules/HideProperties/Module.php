<?php
namespace HideProperties;

use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->delete('hidden_properties_properties');
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            '*',
            'rep.resource.display_values',
            [$this, 'filterDisplayValues']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $adminShowAll = $settings->get('hidden_properties_admin_show_all');
        $hiddenProperties = $settings->get('hidden_properties_properties', []);
        return $renderer->render('hide-properties/config-form', [
            'adminShowAll' => $adminShowAll,
            'hiddenProperties' => $hiddenProperties,
        ]);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $adminShowAll = (bool) $controller->params()->fromPost('admin_show_all');
        $hiddenProperties = $controller->params()->fromPost('hidden-properties', []);
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('hidden_properties_admin_show_all', $adminShowAll);
        $settings->set('hidden_properties_properties', $hiddenProperties);
    }

    public function filterDisplayValues(Event $event)
    {
        $services = $this->getServiceLocator();
        $status = $services->get('Omeka\Status');
        $settings = $services->get('Omeka\Settings');
        if ($status->isAdminRequest() && $settings->get('hidden_properties_admin_show_all')) {
            return;
        }

        $hiddenProperties = $settings->get('hidden_properties_properties', []);
        $values = $event->getParams()['values'];
        $values = array_diff_key($values, array_flip($hiddenProperties));
        $event->setParam('values', $values);
    }
}
