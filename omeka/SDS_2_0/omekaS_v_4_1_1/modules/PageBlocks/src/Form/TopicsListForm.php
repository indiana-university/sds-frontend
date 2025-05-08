<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element\ColorPicker;

class TopicsListForm extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][header]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Header text', // @translate
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][button_color]',
            'type' => ColorPicker::class,
            'options' => [
                'label' => 'Button color', // @translate
            ],
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][text_color]',
            'type' => ColorPicker::class,
            'options' => [
                'label' => 'Text color', // @translate
            ],
        ]);
    }
}
?>