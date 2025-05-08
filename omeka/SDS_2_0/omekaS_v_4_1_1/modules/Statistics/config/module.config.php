<?php declare(strict_types=1);

namespace Statistics;

return [
    'listeners' => [
        Mvc\MvcListeners::class,
    ],
    'service_manager' => [
        'invokables' => [
            Mvc\MvcListeners::class => Mvc\MvcListeners::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'hits' => Api\Adapter\HitAdapter::class,
            'stats' => Api\Adapter\StatAdapter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'apiResourcesTotalResults' => View\Helper\ApiResourcesTotalResults::class,
        ],
        'factories' => [
            'analytics' => Service\ViewHelper\AnalyticsFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'Statistics\Controller\Analytics' => Service\Controller\AnalyticsControllerFactory::class ,
            'Statistics\Controller\Download' => Service\Controller\DownloadControllerFactory::class,
            'Statistics\Controller\Statistics' => Service\Controller\StatisticsControllerFactory::class ,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'logCurrentUrl' => Service\ControllerPlugin\LogCurrentUrlFactory::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'analytics' => [
                'label' => 'Analytics', // @translate
                'route' => 'admin/analytics',
                'controller' => 'Analytics',
                'action' => 'index',
                'resource' => 'Statistics\Controller\Analytics',
                'class' => 'o-icon- fa-chart-line',
                'pages' => [
                    [
                        'route' => 'admin/analytics/default',
                        'visible' => false,
                    ],
                ],
            ],
            'statistics' => [
                'label' => 'Statistics', // @translate
                'route' => 'admin/statistics',
                'controller' => 'Statistics',
                'action' => 'index',
                'resource' => 'Statistics\Controller\Statistics',
                'class' => 'o-icon- fa-chart-line',
                'pages' => [
                    [
                        'route' => 'admin/statistics/default',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'analytics' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/analytics',
                            'defaults' => [
                                '__NAMESPACE__' => 'Statistics\Controller',
                                '__SITE__' => true,
                                'controller' => 'Analytics',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'statistics' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/statistics',
                            'defaults' => [
                                '__NAMESPACE__' => 'Statistics\Controller',
                                '__SITE__' => true,
                                'controller' => 'Statistics',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'output' => [
                                        'type' => \Laminas\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '.:output',
                                            'constraints' => [
                                                'output' => 'csv|tsv|ods',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'analytics' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/analytics',
                            'defaults' => [
                                '__NAMESPACE__' => 'Statistics\Controller',
                                '__ADMIN__' => true,
                                'controller' => 'Analytics',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'statistics' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/statistics',
                            'defaults' => [
                                '__NAMESPACE__' => 'Statistics\Controller',
                                '__ADMIN__' => true,
                                'controller' => 'Statistics',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'output' => [
                                        'type' => \Laminas\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '.:output',
                                            'constraints' => [
                                                'output' => 'csv|tsv|ods',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'download' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    // See module Access too.
                    // Manage module Archive repertory, that can use real names and subdirectories.
                    // For any filename, either use `:filename{?}`, or add a constraint `'filename' => '.+'`.
                    'route' => '/download/files/:type/:filename{?}',
                    'constraints' => [
                        'type' => '[^/]+',
                        'filename' => '.+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Statistics\Controller',
                        'controller' => 'Download',
                        'action' => 'file',
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'shortcodes' => [
        'invokables' => [
            'stat' => Shortcode\Stat::class,
            'stat_total' => Shortcode\Stat::class,
            'stat_position' => Shortcode\Stat::class,
            'stat_vieweds' => Shortcode\Stat::class,
        ],
    ],
    'statistics' => [
        'settings' => [
            // Privacy settings.
            'statistics_privacy' => 'anonymous',
            'statistics_include_bots' => false,
            // Display.
            'statistics_default_user_status_admin' => 'hits',
            'statistics_default_user_status_public' => 'anonymous',
            'statistics_per_page_admin' => 100,
            'statistics_per_page_public' => 10,
            // Without roles.
            'statistics_public_allow_statistics' => false,
            'statistics_public_allow_summary' => false,
            'statistics_public_allow_browse' => false,
            // With roles, in particular if Guest is installed.
            /*
            'statistics_roles_summary' => [
                'admin',
            ],
            'statistics_roles_browse_pages' => [
                'admin',
            ],
            'statistics_roles_browse_resources' => [
                'admin',
            ],
            'statistics_roles_browse_downloads' => [
                'admin',
            ],
            'statistics_roles_browse_fields' => [
                'admin',
            ],
            'statistics_roles_browse_item_sets' => [
                'admin',
            ],
            */
            /*
            'statistics_display_by_hooks' => [
                'admin_dashboard',
                'admin_item_show_sidebar',
                'admin_item_set_show_sidebar',
                'admin_media_show_sidebar',
                // Some filters don't exist in Omeka S or are available through BlocksDisposition.
                // 'admin_item_browse_simple_each',
                // 'admin_item_browse_detailed_each',
                // 'public_item_show',
                // 'public_item_browse_each',
                // 'public_item_set_show',
                // 'public_item_set_browse_each',
            ],
            */
        ],
    ],
];
