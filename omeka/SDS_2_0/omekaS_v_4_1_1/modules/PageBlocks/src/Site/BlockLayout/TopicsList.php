<?php
namespace PageBlocks\Site\BlockLayout;

use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use PageBlocks\Form\TopicsListForm;
use PageBlocks\Form\TopicsListSidebarForm;

class TopicsList extends AbstractBlockLayout
{
    /**
     * @var FormElementManager
     */
    protected $formElementManager;
    
    /**
     * @param FormElementManager $formElementManager
     */
    public function __construct(FormElementManager $formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }
    
    public function getLabel()
    {
        return 'List of topics'; // @translate
    }
    
    public function prepareForm(PhpRenderer $view)
    {
        $view->headLink()->prependStylesheet($view->assetUrl('css/advanced-search.css', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/advanced-search.js', 'Omeka'));
        $view->headLink()->appendStylesheet($view->assetUrl('css/query-form.css', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/query-form.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/topics-list.js', 'PageBlocks'));
        $view->headLink()->appendStylesheet($view->assetUrl('css/admin.css', 'PageBlocks'));
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        $form = $this->formElementManager->get(TopicsListForm::class);
            
        if ($block && $block->data()) {
            $form->populateValues([
                'o:block[__blockIndex__][o:data][header]' => $block->dataValue('header'),
                'o:block[__blockIndex__][o:data][button_color]' => $block->dataValue('button_color'),
                'o:block[__blockIndex__][o:data][text_color]' => $block->dataValue('text_color')
            ]);
        }
        
        return $view->formCollection($form) . $view->partial('common/admin/topics-list', [
            'topics' => ($block) ? $block->dataValue('topics') : []
        ]);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block-layout/topics-list', [
            'header' => $block->dataValue('header'),
            'buttonColor' => $block->dataValue('button_color'),
            'textColor' => $block->dataValue('text_color'),
            'topics' => $block->dataValue('topics')
        ]);
    }
}
?>