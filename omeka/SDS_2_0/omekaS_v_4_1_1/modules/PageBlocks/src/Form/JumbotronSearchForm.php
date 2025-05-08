<?php
namespace PageBlocks\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class JumbotronSearchForm extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][header]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Header text', // @translate
            ],
            'attributes' => [
                'required' => true
            ]
        ]);
        
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][subheader]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Subheader text', // @translate
            ]
        ]);
    }
}
?>