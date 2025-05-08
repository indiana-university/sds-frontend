<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class TwoColumnForm extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][html1]',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'First column' // @translate
            ],
            'attributes' => [
                'class' => 'block-html full wysiwyg'
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][html2]',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Second column' // @translate
            ],
            'attributes' => [
                'class' => 'block-html full wysiwyg'
            ]
        ]);
    }
}
?>