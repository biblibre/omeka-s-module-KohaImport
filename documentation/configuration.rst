Configuration
=============

Add koha credentials
-----------------------

On `module.config.php` file add yours credentials to allow module to access to Koha API (plugin OmekaExport needed):

.. code-block:: php

    'koha-import' => [
        'url' => '',
        'client_id' => '',
        'client_secret' => '',
    ],

Add your custom mapping
-----------------------

To add your own custom mapping add it on `module.config.php` file like:

.. code-block:: php

    'koha_import_mapping' => [
        'factories' => [
            'your_mapping' => Path\To\Your\Mapping::class,
        ],
    ];

Add S3 bucket as medias storage
-------------------------------

Add it on `module.config.php` file:

.. code-block:: php

    'koha-import_bucket' => [
        'key' => '',
        'secret' => '',
        'region' => '',
        'bucket_name' => '',
    ];
