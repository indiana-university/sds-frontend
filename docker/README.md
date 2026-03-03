# Notes:
The new method is based on [Omeka-S-Cli](https://github.com/GhentCDH/Omeka-S-Cli)
Omeka S version 4.2.0
Php version 8.4

# Docker

Build docker image from the offical Omekas release [Omeka S v4.1.1](https://github.com/omeka/omeka-s/releases/download/v4.1.1/omeka-s-4.1.1.zip)

## Omeka Modules:
| Module           | Repository Link                                                           | Notes              |
|------------------|----------------------------------------------------------------------------|--------------------|
| OIDC             | https://github.com/indiana-university/omeka-s-module-oidc/                 | *fix/package_update |
| BulkEdit         | https://gitlab.com/Daniel-KM/Omeka-S-module-BulkEdit                       |                    |
| BulkExport       | https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport                     |                    |
| CSVImport        | https://github.com/Omeka-s-modules/CSVImport                               |                    |
| CustomOntology   | https://gitlab.com/Daniel-KM/Omeka-S-module-CustomOntology                 |                    |
| FacetedBrowse    | https://github.com/omeka-s-modules/FacetedBrowse                           |                    |
| FileSideload     | https://github.com/omeka-s-modules/FileSideload                            |                    |
| HideProperties   | https://github.com/zerocrates/HideProperties                               |                    |
| Log              | https://gitlab.com/Daniel-KM/Omeka-S-module-Log                            |                    |
| NumericDataTypes | https://github.com/omeka-s-modules/NumericDataTypes                        |                    |
| RestrictedSites  | https://github.com/ManOnDaMoon/omeka-s-module-RestrictedSites              |                    |
| Shortcode        | https://github.com/Daniel-KM/Omeka-S-module-Shortcode                      |                    |
| Sitemaps         | https://github.com/ManOnDaMoon/omeka-s-module-Sitemaps                     |                    |
| Statistics       | https://github.com/Daniel-KM/Omeka-s-module-Statistics                     |                    |
| ValueSuggest     | https://github.com/omeka-s-modules/ValueSuggest                            |                    |

## Modifications
omeka/SDS_2_0/omekaS_v_4_1_1

* application/asset/css
* application/view/common
* application/view/layout
* application/view/omeka
* themes/default

## 1. Pakcage omeka
Run ./zip_omekas.sh to generate omeka-s.zip 
## 2. Setup database
Modify development.env and database.ini for db setup if necessary
## 3. Build and run docker
``` docker compose up --build ```
## 4. Test
http://localhost:8000

reference: https://github.com/giocomai/omeka-s-docker
