# Required files in `omeka/`

Everything else under `omeka/` was removed as unused (the old SDS_2_0 overlay and its Omeka core/vendor/modules, the
`SDS Theme for Omeka S` source folder, which is identical to `SDS_theme.zip`, and `.DS_Store`/`.swp` files).

## 1. Used by `docker/Dockerfile` (image build)

| Path | Used for |
|------|----------|
| `SDS_theme.zip` | Copied to `/sds-frontend/omeka/SDS_theme.zip`; installed at startup by `docker-php-entrypoint` |
| `SDS Core Code Files to Replace/application/**` | Overlaid on Omeka S `application/` |
| `Static_files/rivetlandingpagescripts/**` | Copied to `/var/www/html/rivetlandingpagescripts/` |

```
SDS Core Code Files to Replace/application/config/module.config.php
SDS Core Code Files to Replace/application/src/Service/ViewHelper/MyDatabaseHelperFactory.php
SDS Core Code Files to Replace/application/src/Site/BlockLayout/MyDownloadsBlock.php
SDS Core Code Files to Replace/application/src/View/Helper/MyDatabaseHelper.php
SDS Core Code Files to Replace/application/view/omeka/login/login.phtml
Static_files/rivetlandingpagescripts/microsite.css
Static_files/rivetlandingpagescripts/styles.css
SDS_theme.zip
```

## 2. Used by `docker/build_omeka.sh` (via `CUSTOM_SOURCE` = `omeka/IEEE_SciVis_Contest`)

The script replaces these folders in a fresh Omeka S download:

- `IEEE_SciVis_Contest/application/asset/css`
- `IEEE_SciVis_Contest/application/view/common`
- `IEEE_SciVis_Contest/application/view/layout`
- `IEEE_SciVis_Contest/application/view/omeka`

## 3. Documentation kept

- `README.md` (linked from the root README)
- `Static_files/README.md`
