# KohaImport

This Omeka S module allows to import bibliographic records from Koha.

## Requirements

* PHP >= 8.1
* [Koha plugin OmekaExport](https://git.biblibre.com/biblibre/koha-plugin-OmekaExport)
  must be installed and enabled.
* Koha REST API and its "client credentials" authentication method (system
  preference: RESTOAuth2ClientCredentials) must be enabled.
* [Koha custom vocabulary](https://git.biblibre.com/omeka-s/custom-vocabularies/src/branch/master/vocabularies/koha.ttl)
  must be installed.

## Configuration

On configuration form module you must define url, client id and client secret.

To generate `client_id` and `client_secret`, see
[Koha documentation](https://koha-community.org/manual/latest/en/html/webservices.html#api-key-management-interface-for-patrons)
