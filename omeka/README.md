This directory has the Omeka S code that is modified for SDS. See [REQUIRED_FILES.md](./REQUIRED_FILES.md) for the full list of files and which build step uses them.

## Contents

- `SDS Core Code Files to Replace/application/` - core code modifications, copied over Omeka S `application/` by `docker/Dockerfile`:
   - application/src/Service/ViewHelper/MyDatabaseHelperFactory.php
   - application/src/Site/BlockLayout/MyDownloadsBlock.php
   - application/src/View/Helper/MyDatabaseHelper.php
   - application/config/module.config.php
   - application/view/omeka/login/login.phtml
- `SDS_theme.zip` - the SDS theme, installed at container startup.
- `Static_files/rivetlandingpagescripts/` - external css files referenced by the phtml files.
- `IEEE_SciVis_Contest/` - customized view and css folders that `docker/build_omeka.sh` copies over a fresh Omeka S download:
   - application/asset/css
   - application/view/common
   - application/view/layout
   - application/view/omeka

After downloading the current core code from Omeka's repository (https://github.com/omeka/omeka-s/releases), replace the above files/directories with the ones from the SDS repo here. `docker/build_omeka.sh` does this for `IEEE_SciVis_Contest/`, and `docker/Dockerfile` does it for the core code files, theme and static files.

You will also need to install the following modules:
- OIDC (RDS-managed module)
- CSVImport
- CustomOntology
- HideProperties
- NumericDataTypes
- Restricted Sites
- Shortcode
