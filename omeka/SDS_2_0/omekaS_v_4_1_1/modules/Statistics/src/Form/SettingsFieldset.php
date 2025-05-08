<?php declare(strict_types=1);

namespace Statistics\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Statistics'; // @translate

    protected $elementGroups = [
        'statistics' => 'Statistics', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'statistics')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'statistics_privacy',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'Level of privacy for new hits', // @translate
                    'value_options' => [
                        'anonymous' => 'Anonymous', // @translate
                        'hashed' => 'Hashed IP', // @translate
                        'partial_1' => 'Partial IP (first hex)', // @translate
                        'partial_2' => 'Partial IP (first 2 hexs)', // @translate
                        'partial_3' => 'Partial IP (first 3 hexs)', // @translate
                        'clear' => 'Clear IP', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'statistics_privacy',
                    'value' => 'anonymous',
                ],
            ])
            ->add([
                'name' => 'statistics_include_bots',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'Include crawlers/bots', // @translate
                    'info' => 'By checking this box, all hits which user agent contains the term "bot", "crawler", "spider", etc. will be included.', // @translate
                ],
                'attributes' => [
                    'id' => 'statistics_include_bots',
                ],
            ])

            ->add([
                'name' => 'statistics_default_user_status_admin',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'User status for admin pages', // @translate
                    'value_options' => [
                        'hits' => 'Total hits', // @translate
                        'anonymous' => 'Anonymous', // @translate
                        'identified' => 'Identified users', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'statistics_default_user_status_admin',
                ],
            ])
            ->add([
                'name' => 'statistics_default_user_status_public',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'User status for public pages', // @translate
                    'value_options' => [
                        'hits' => 'Total hits', // @translate
                        'anonymous' => 'Anonymous', // @translate
                        'identified' => 'Identified users', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'statistics_default_user_status_public',
                ],
            ])
            ->add([
                'name' => 'statistics_per_page_admin',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'Results per page (admin)', // @translate
                ],
                'attributes' => [
                    'id' => 'statistics_per_page_admin',
                    'min' => 0,
                ],
            ])
            ->add([
                'name' => 'statistics_per_page_public',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'Results per page (public)', // @translate
                ],
                'attributes' => [
                    'id' => 'statistics_per_page_public',
                    'min' => 0,
                ],
            ])

            ->add([
                'name' => 'statistics_public_allow_statistics',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'Allow public to access statistics', // @translate
                ],
                'attributes' => [
                    'id' => 'statistics_public_allow_statistics',
                ],
            ])

            ->add([
                'name' => 'statistics_public_allow_summary',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'Allow public to access analytics summary', // @translate
                ],
                'attributes' => [
                    'id' => 'statistics_public_allow_summary',
                ],
            ])
            ->add([
                'name' => 'statistics_public_allow_browse',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'statistics',
                    'label' => 'Allow public to access detailled analytics', // @translate
                ],
                'attributes' => [
                    'id' => 'statistics_public_allow_browse',
                ],
            ])
        ;
    }
}
