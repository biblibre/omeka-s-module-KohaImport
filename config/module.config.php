<?php

namespace KohaImport;

return [
    'controllers' => [
        'factories' => [
            'KohaImport\Controller\Admin\Index' => Service\Controller\Admin\IndexControllerFactory::class,
            'KohaImport\Controller\Admin\Config' => Service\Controller\Admin\ConfigControllerFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            'KohaImport\Form\MappingForm' => Service\Form\MappingFormFactory::class,
        ],
        'invokables' => [
            Form\Element\ItemFieldset::class => Form\Element\ItemFieldset::class,
            Form\Element\ItemSetFieldset::class => Form\Element\ItemSetFieldset::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'KohaImport\MappingManager' => Service\MappingManagerFactory::class,
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
            'koha_import_import' => Api\Adapter\ImportAdapter::class,
            'koha_import_record' => Api\Adapter\RecordAdapter::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Import from Koha',
                'route' => 'admin/koha-import',
                'resource' => 'KohaImport\Controller\Admin\Index',
                'pages' => [
                    [
                        'label' => 'Configure import',
                        'route' => 'admin/koha-import',
                        'resource' => 'KohaImport\Controller\Admin\Index',
                    ],
                    [
                        'label' => 'Configure mappings',
                        'route' => 'admin/koha-import/map',
                        'resource' => 'KohaImport\Controller\Admin\Index',
                        'visible' => false,
                    ],
                    [
                        'label' => 'Past imports',
                        'route' => 'admin/koha-import/past-imports',
                        'resource' => 'KohaImport\Controller\Admin\Index',
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'koha-import' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/koha-import',
                            'defaults' => [
                                '__NAMESPACE__' => 'KohaImport\Controller\Admin',
                                'controller' => 'Index',
                                'action' => 'config',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'map' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/map',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'KohaImport\Controller\Admin',
                                        'controller' => 'Index',
                                        'action' => 'map',
                                    ],
                                ],
                            ],
                            'import' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'KohaImport\Controller\Admin',
                                        'controller' => 'Index',
                                        'action' => 'import',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'config' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/config[/:action]',
                                            'constraints' => [
                                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                            ],
                                            'defaults' => [
                                                '__NAMESPACE__' => 'KohaImport\Controller\Admin',
                                                'controller' => 'Config',
                                                'action' => 'show',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'past-imports' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/past-imports',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'KohaImport\Controller\Admin',
                                        'controller' => 'Index',
                                        'action' => 'past-imports',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
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
    'koha-import' => [
        'url' => '',
        'client_id' => '',
        'client_secret' => '',
    ],
    'koha-import_mapping' => [
        'factories' => [
            'standard' => Service\StandardMappingFactory::class,
        ],
    ],
    'koha-import_bucket' => [
        'key' => '',
        'secret' => '',
        'region' => '',
        'bucket_name' => '',
    ],
];
