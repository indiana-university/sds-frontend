<?php

namespace PageBlocks\Site\ResourcePageBlockLayout;

use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

class SmartEmbeds implements ResourcePageBlockLayoutInterface
{
    /**
     * @var bool
     */
    protected $pdfCapable;
    
    /**
     * @param ModuleManager $manager
     */
    public function __construct(ModuleManager $manager)
    {
        $this->pdfCapable = self::isPdfCapable($manager);
    }
    
    public function getLabel() : string
    {
        return 'Smart embeds'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items', 'media'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        if ($resource instanceof ItemRepresentation)
        {
            return self::shouldUseLightbox($resource->primaryMedia(), $this->pdfCapable) ?
                $view->partial('common/resource-page-block-layout/lightbox-gallery-item', [
                    'resource' => $resource
                ]) :
                $view->partial('common/resource-page-block-layout/media-embeds', [
                    'resource' => $resource
                ]);
        }
        else if ($resource instanceof MediaRepresentation)
        {
            return self::shouldUseLightbox($resource->primaryMedia(), $this->pdfCapable) ?
                $view->partial('common/resource-page-block-layout/lightbox-gallery-media', [
                    'resource' => $resource
                ]) :
                $resource->render();
        }
    }
    
    protected function shouldUseLightbox(MediaRepresentation $media, bool $pdfCapable)
    {
        return self::isLightboxType($media) && !($pdfCapable && self::isPdfType($media));
    }
    
    protected function isPdfCapable(ModuleManager $manager)
    {
        $module = $manager->getModule("SimplePdf");
        return $module && $module->getState() == ModuleManager::STATE_ACTIVE;
    }
    
    protected function isPdfType(MediaRepresentation $media)
    {
        return $media->mediaType() == "application/pdf";
    }
    
    protected function isLightboxType(MediaRepresentation $media)
    {
        return $media->renderer() == "file" && (
            strpos($media->mediaType(), "image") === 0 ||
            strpos($media->mediaType(), "video") === 0 ||
            self::isPdfType($media)
        );
    }
}