This directory has current and past versions of the Omeka code that is modified for SDS. 

All core code modifications occur in these files and the themes directory:
   - application/src/Service/ViewHelper/MyDatabaseHelperFactory.php
   - application/src/Site/BlockLayout/MyDownloadsBlock.php
   - application/src/View/Helper/MyDatabaseHelper.php
   - application/config/module.config.php
   - themes/default/SDS_Theme_


After downloading the current core code from Omeka's repository (), you will need to replace the above files/directories with the ones from the SDS repo here.

You will also need to install the following modules:
- OIDC (RDS-managed module)
- CSVImport
- CustomOntology
- HideProperties
- NumericDataTypes
- Restricted Sites
- Shortcode
-test


