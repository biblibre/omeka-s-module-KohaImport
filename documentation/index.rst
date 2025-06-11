Introduction
============

How does it work ?
------------------

This Omeka S module allows to import bibliographic records from Koha.

Requirements
------------

* `Koha plugin OmekaExport <https://git.biblibre.com/biblibre/koha-plugin-OmekaExport>`_
  must be installed and enabled.
* Koha REST API and its "client credentials" authentication method (system
  preference: RESTOAuth2ClientCredentials) must be enabled.
* `Koha custom vocabulary <https://git.biblibre.com/omeka-s/custom-vocabularies/src/branch/master/vocabularies/koha.ttl>`_
  must be installed.


Where is the configuration
--------------------------

Actually `Standard` mapping is include in this module but you can add your own custom mapping on `module.config.php` file. (Also see :doc:`configuration`)

.. toctree::
   :maxdepth: 2
   :caption: Contents

   configuration
   features
   tutorials