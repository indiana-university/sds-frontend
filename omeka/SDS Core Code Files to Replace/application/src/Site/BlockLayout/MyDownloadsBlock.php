<?php
namespace Omeka\Site\BlockLayout;


use Omeka\Entity\SitePageBlock;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractActionController;


class MyDownloadsBlock extends AbstractBlockLayout
{
    
    
    public function getLabel()
    {
        return 'My Downloads Block'; // @translate
    }

     public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        return $view->escapeHtml($page->title());
    }
    
    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block-layout/mydownloadsblock', [
            'header' => $block->dataValue('header'),
            'subheader' => $block->dataValue('subheader')
        ]);
    }
}




?>