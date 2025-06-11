# KohaImport (Omeka S module)

This Omeka S module allows to import bibliographic records from Koha.

The complete documentation of KohaImport can be found [here](https://biblibre.github.io/omeka-s-module-KohaImport/).

## Requirements

* PHP >= 8.1
* [Koha plugin OmekaExport](https://git.biblibre.com/biblibre/koha-plugin-OmekaExport)
  must be installed and enabled.
* Koha REST API and its "client credentials" authentication method (system
  preference: RESTOAuth2ClientCredentials) must be enabled.
* [Koha custom vocabulary](https://git.biblibre.com/omeka-s/custom-vocabularies/src/branch/master/vocabularies/koha.ttl)
  must be installed.

## Quick start

1. [Add the module to Omeka S](https://omeka.org/s/docs/user-manual/modules/#adding-modules-to-omeka-s)
2. Add the following code to `config/local.config.php`:

    ``` php
    return [
        'koha-import' => [
          'url' => '',
          'client_id' => '',
          'client_secret' => '',
      ],
    ];
    ```
3. To generate `client_id` and `client_secret`, see [Koha documentation](https://koha-community.org/manual/latest/en/html/webservices.html#api-key-management-interface-for-patrons)
4. Use the module

_Optionnal:_
- You can also define custom mapping by adding this to your `config/local.config.php`:

``` php
  'koha-import_mapping' => [
    'factories' => [
        'custom_mapping' => YourCustomMapping::class,
    ],
  ],
```

- You have possibility to serve your media from a bucket by adding to your `config/local.config.php`:

``` php
  'koha-import_bucket' => [
    'key' => '',
    'secret' => '',
    'region' => '',
    'bucket_name' => '',
  ],
```

## Features

This module allows you to import records from Koha and configure each import as follows:

Define the type of resource (mapping of fields and subfields) to be imported; if only one is chosen, mapping can be optional (to filter records, for example).
Add media from a local folder or from a bucket (in the latter case, don't forget to configure the bucket in `config/local.config.php`).

Define the sites to be imported, the since parameter to filter records from to, the force parameter to "force to" update resources and the delete parameter to clean up Koha records already present in OmekaS resources but no longer to be exported.

This is done for each import, after which you can configure the import to map different resource types, resource models, resource classes and so on.

To summarize the import, there's a “past imports” view to see the various jobs linked to KohaImport.

## How to contribute

You can contribute to this module in many ways. Discover how by reading
[Contributing](CONTRIBUTING.md).

## Contributors / Sponsors

KohaImport was sponsored by:
* Ecole du Louvre

KohaImport also received contributions from:
* [ThibaudGLT](https://github.com/ThibaudGLT)

## License

KohaImport is distributed under the GNU General Public License, version 3 (GPLv3).
The full text of this license is given in the `LICENSE` file.