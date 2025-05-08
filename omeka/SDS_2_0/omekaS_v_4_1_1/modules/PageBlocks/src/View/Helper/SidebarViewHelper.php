<?php
namespace PageBlocks\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SidebarViewHelper extends AbstractHelper
{
    protected $formElementManager;

    public function __construct($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    public function __invoke($id, $formClass)
    {
        $view = $this->getView();
        $form = $view->formCollection(
            $this->formElementManager->get($formClass));
        
        return $view->partial('common/admin/sidebar', [
            'id' => $id,
            'form' => $form
        ]);
    }
}
?>