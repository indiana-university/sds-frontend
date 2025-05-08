<?php declare(strict_types=1);

namespace PageBlocks;

use Omeka\Module\AbstractModule;
use Laminas\EventManager\SharedEventManagerInterface;
use PageBlocks\Form\TopicsListSidebarForm;
use Laminas\ServiceManager\ServiceLocatorInterface;

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
    
    public function attachListeners(SharedEventManagerInterface $sharedEventManager) 
    {
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Page',
            'view.edit.before',
            [$this, 'addSidebar']
        );
    }
    
    function addSidebar($event) {
        $view = $event->getTarget();
        echo $view->sidebar('topic-sidebar', TopicsListSidebarForm::class);
    }

  
    
     public function install(ServiceLocatorInterface $services)
    {
        $connection = $services->get('Omeka\Connection');
        $connection->exec('
  CREATE TABLE userjobs (
  iduserjobs int NOT NULL AUTO_INCREMENT,
  email varchar(45) COLLATE utf8mb4_bin DEFAULT NULL,
  jobid varchar(45) COLLATE utf8mb4_bin DEFAULT NULL,
  dateCreated datetime DEFAULT NULL,
  jobSize int DEFAULT NULL,
  jobStatus varchar(45) COLLATE utf8mb4_bin DEFAULT NULL,
  collection varchar(1500) COLLATE utf8mb4_bin DEFAULT NULL,
  downloadURL varchar(1500) COLLATE utf8mb4_bin DEFAULT NULL,
  sourceIP varchar(15) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (iduserjobs)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;');

    }
    
   public function uninstall(ServiceLocatorInterface $services)
    {
        $connection = $services->get('Omeka\Connection');
        $connection->exec('DROP TABLE IF EXISTS userjobs');
    } 
    
}
?>