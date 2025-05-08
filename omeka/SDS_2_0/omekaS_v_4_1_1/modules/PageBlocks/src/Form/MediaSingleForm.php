<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class MediaSingleForm extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][html]',
            'type' => Element\Textarea::class,
            'attributes' => [
                'class' => 'block-html full wysiwyg'
            ]
        ]);
    }
}
?>