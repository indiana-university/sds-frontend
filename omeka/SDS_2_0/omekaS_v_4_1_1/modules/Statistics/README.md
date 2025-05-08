Statistics and Analytics (module for Omeka S)
=============================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Statistics] is a module for [Omeka S] that computes statistics about resources
ant that counts views of pages by visitors. It allows to get data about items,
item sets, media, properties, etc., and it allows to know the least popular
resource and the most viewed pages. It provides useful infos on visitors too
(language, referrer...). So this is a statistics tools and an analytics tool
like [Matomo] (open source), [Google Analytics] (proprietary, no privacy) and
other hundreds of such [web loggers].

It has some advantages over them:
- internal statistics about resources;
- simple to manage (a normal module, with same interface);
- adapted (statistics can be done by resource and not only by page);
- integrated, so statistics can be displayed on any page easily;
- informative (query, referrer, user agent and language are saved; all
  statistics can be browsed by public);
- count of direct download of files;
- full control of pruducted data;
- respect of privacy by default.

On the other hand, some advanced features are not implemented, especially
a detailled board with advanced filters. Furthermore, many analytics on the
users are not available. So the two tools may be needed according to your needs.

Logs and data can be exported as a spreadsheet. All the viewers hits are stored
in the database and can be exported via sql to a spreadsheet like [LibreOffice]
or another specialized statistic tool, where many statistics can be calculated.

Of course, you must respect privacy of users and visitors.

This module is a direct upgrade of the plugin [Statistics for Omeka Classic],
with many improvments.


Installation
------------

### Module

See general end user documentation for [installing a module].

To get statistics and not only analytics, the module [Advanced Search] is
required in order to do advanced queries, in particular to get results by
period.

This module can use the optional module [Generic].

The module uses an external library, so use the release zip to install it, or
use and init the source.

* From the zip

Download the last release [Statistics.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Statistics`, go to the root module, and run:

```sh
composer install --no-dev
```

### Count downloads of files

If you don't use apache logs or another specialized tool ot count files
download, you can enable it in the module and to config the file `.htaccess` at
the root of the server.

Just add a line in the beginning off `.htaccess`, after `RewriteEngine on`:

```apache
RewriteRule ^files/original/(.*)$ https://example.org/download/files/original/$1 [NC,L]
```

In case of some issues in Apache or with an internal proxy, you can use (the use
of http is not unsecure, because the redirect is internal):

```apache
RewriteRule ^files/original/(.*)$ http://%{HTTP_HOST}/download/files/original/$1 [NC,L]
```

If you use the module [Access] to allow access to some private files to some
users, **you must use its redirection**, so **use `/access/`** instead of `/download/`
in the redirect url of the rewrite rule. The module Access records the url for
Statistics too. If you keep the redirection with `download`, the check for
restricted access won't be done, so **a private file will become public**, even
if a user as a no restricted access to it.

```apache
# Redirect direct access to files to the module Access.
RewriteRule "^files/(original|large)/(.*)$" "/access/files/$1/$2" [P]

# Redirect direct download of files to the module Access.
RewriteRule "^download/files/(original|large)/(.*)$" "http://%{HTTP_HOST}/access/files/$1/$2" [P]
```

In fact, if not redirected, it acts the same way than a direct access to a
private file in Omeka: they are not protected and everybody who knows the url,
in particular Google via Gmail, Chrome, etc., will have access to it.

If you use the anti-hotlinking feature of [Archive Repertory] to avoid bandwidth
theft, you should keep its rule. Statistics for direct downloads of files will
be automatically added.

You can count large files too, but this is not recommended, because in the
majority of themes, hits may increase even when a simple page is opened.
Nevertheless, it may be required when you control access with module [Access Resource].

If you use module [Derivative Media], don't forget to add them to the htaccess file.


Usage
-----

### Browse analytics

A summary of statistics is displayed at `/statistics/analytics`.

Lists of statistics by page, by resource or by field are available too. They can
be ordered and filtered by anonymous / identified users, resource types, etc.

These pages can be made available to authorized users only or to all public.

**Warning:** On the public side, this page will be replaced by a block.

### Displaying some analytics in the theme

Statistics of a page or resource can be displayed on any page via three mechanisms.

#### Events

An option allows to append the statistics automatically on some resource `show`
and `browse` pages via the events. Just enable them through the module [Blocks Disposition].

#### Helper "statistic"

The helpers `statistic` can be used for more flexibility:

```php
echo $this->statistic()->positionResource($resource);
echo $this->statistic()->textPage($currentUrl);
```

#### Shortcodes

Shortcode are available through the module [Shortcode].

Some illustrative examples:

```
[stat]
[stat_total url="/login"]
[stat_total resource="items"]
[stat_total resource="items" id=1]
[stat_position]
[stat_position url="/item/search"]
[stat_position resource="item_sets" id=1]
[stat_vieweds]
[stat_vieweds type="none"]
[stat_vieweds sort="last" type="items"]
[stat_vieweds sort="most" type="download" number=1]
```

Arguments for Omeka Classic were adapted for Omeka S.

All arguments are optional. Arguments are:
* For `stat_total` (alias of `stat`) and `stat_position`
  - `type`: If "download", group all downloaded files linked to the specified
  resource (all files of an item, or all files of all items of a).
  Else, the type is automatically detected ("resource" if a resource is set,
  "page" if an url is set or if nothing is set).
  - `resource` (Omeka classic: `record_type`): one or multiple Omeka resource
  type, e.g. "items" or "item_sets", or "media". By default, a viewed resource
  is counted for each hit on the dedicated page of a resource, like "/item/xxx".
  Alternatively, the url can be used (with the argument `url`, but to count the
  downloaded files, this is an obfuscated one except if [Archive Repertory] is
  used.
  - `id` (Omeka classic: `record_id`): the identifier of the resource (not the
  slug if any). It implies one specific `resource` and only one. With
  `stat_position`, `id` is required when searching by resource.
  - `url`: the url of a specific page. A full url is not needed; a partial Omeka
  url without web root is enough (in any case, web root is removed
  automatically). This argument is used too to know the total or the position of
  a file. This argument is not used if `resource` argument is set.

* For `stat_vieweds`
  - `type`: If "page" or "download", most or last viewed pages or downloaded
  files will be returned. If empty or "all", it returns only pages with a
  dedicated resource. If "none", it returns pages without dedicated resource. If
  one or multiple Omeka resource type, e.g. "items" or "item_sets", most or last
  resources of this resource type will be returned.
  - `sort`: can be "most" (default) or "last".
  - `page`: page number to return (the first one by default).
  - `number`: number of resources to return (10 by default).

The event and the helper return the partial from the theme.

`stat_total` and `stat_position` return a simple number, surrounded by a
`span` tag when shortcode is used.
`stat_vieweds` returns an html string that can be themed.


Notes
-----

- Hits of anonymous users and identified users are counted separately.
- Only pages of the public theme are counted.
- Reload of a page generates a new hit (no check).
- IP can be hashed (default) or truncated for privacy purpose.
- Currently, screen size is not detected.


TODO
----

- [ ] Fixme: stats by value is wrong when a property has duplicate values for a resource (so fix it or warn about deduplicating values regularly (module BulkEdit)).
- [x] Fix and finalize statistics for public side and shortcodes.
- [ ] Replace public statistics pages by a block and remove public routes.
- [ ] Statistics for api.
- [x] Add summary in public side.
- [ ] Move some options to site settings.
- [x] Store the site id in hits.
- [ ] Store the site id in stats and update all stats and queries.
- [x] Add stats by site.
- [ ] Check CleanUrl.
- [ ] Merge the stats page/download and resource.
- [ ] Improve rights to read/create or filter visitors data on api.
- [x] Move all statistics methods from Stat and Hit models to Statistic Helper?
- [ ] Improve stats by item sets and move to helper Statistic.
- [ ] Enlarge item sets to any resource (item for media, periods, item set tree).
- [ ] Add tests.
- [ ] Add stored generated index (year, month, day, hour) (doctrine 2.11, so Omeka 4).
- [ ] Add a table to store search (q, fulltext_search, others?).
- [ ] Include the right sidebar search form (the one used in site resources and pages) in by-value statistics.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2014-2023 (see [Daniel-KM] on GitLab)


[Statistics]: https://gitlab.com/Daniel-KM/Omeka-S-module-Statistics
[Omeka S]: https://omeka.org/s
[Matamo]: https://matomo.org
[Google Analytics]: https://www.google.com/analytics
[web loggers]: https://en.wikipedia.org/wiki/List_of_web_analytics_software
[LibreOffice]: https://www.documentfoundation.org
[Statistics for Omeka Classic]: https://gitlab.com/Daniel-KM/Omeka-plugin-Stats
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Advanced Search]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch
[Shortcode]: https://gitlab.com/Daniel-KM/Omeka-S-module-Shortcode
[Archive Repertory]: https://gitlab.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Access]: https://gitlab.com/Daniel-KM/Omeka-S-module-Access
[Derivative Media]: https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Statistics/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: https://github.com/sandywalker/webui-popover/blob/master/LICENSE.txt
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
