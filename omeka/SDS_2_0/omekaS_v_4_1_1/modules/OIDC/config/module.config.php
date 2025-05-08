<?php

return [
    'router' => [
        'routes' => [
            'oidc' => [
                'type' => Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/oidc', //Main URI path
                    'defaults' => [
                        '__namespace__' => 'OIDC\Controller',
                    ],
                ],
                'child_routes' => [
                    'login' => [
                        'type' => Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/login', //Initiate login process with OIDC provider
                            'defaults' => [
                                'controller' => OIDC\Controller\OIDCController::class,
                                'action' => 'login',
                            ],
                        ],
                    ],
                    'redirect' => [
                        'type' => Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/redirect', //Handle redirect from OIDC provider
                            'defaults' => [
                                'controller' => OIDC\Controller\OIDCController::class,
                                'action' => 'redirect',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'OIDC\Controller\OIDCController' => OIDC\Service\Controller\OIDCControllerFactory::class,
        ],
    ],
];
