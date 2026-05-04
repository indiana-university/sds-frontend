This directory has current and past versions of the Omeka code that is modified for SDS. 

All core code modifications occur in these files and the themes directory:
   - application/src/Service/ViewHelper/MyDatabaseHelperFactory.php
   - application/src/Site/BlockLayout/MyDownloadsBlock.php
   - application/src/View/Helper/MyDatabaseHelper.php
   - application/config/module.config.php
   - application/data/install/schema.sql
   - themes/default/SDS_Theme


After downloading the current core code from Omeka's repository (https://github.com/omeka/omeka-s), you will need to replace and/or update the above files/directories with the ones from the SDS repo here.

You will also need to install the following modules:
- OIDC (RDS-managed module)
- CSVImport
- CustomOntology
- HideProperties
- NumericDataTypes
- Restricted Sites
- Shortcode
- Sitemap



