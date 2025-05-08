Shortcode (module for Omeka S)
==============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Shortcode] is a module for [Omeka S] that allows to insert shortcuts in site
pages in order to display more content via a simple string.

The shortcodes are well known in Wikipedia (named [wikitext]), [WordPress], [Omeka Classic],
and many other cms. The format that is used is the one of WordPress and Omeka Classic,
like `[shortcode key=value]`. For example, `[media id=51 player=mirador]`
will render the media #51 in the html code with [Mirador]. Or `[items num=3 is_featured=true sort=random]`
will display a list of three featured items. A simple link can be `[link 51]` or
`[link 52 file=original]`. The last update of a site page can be `[page meta=modified]`.

Shortcodes can be used nearly anywhere in site pages, even in titles. So it is
possible to set a title with a dynamic data: `[count resource=items query="fulltext_search=xxx"]`
will display the total of items according to the query.

Furthermore, all core shortcodes of Omeka Classic are integrated and other ones
are gradually implemented in order to make migration easier.

Of course, other modules can add new shortcodes: just add it as a config key
under `[shortcodes]`.


Installation
------------

Uncompress files and rename module folder `Shortcodes`. Then install it like any
other Omeka module and follow the config instructions.

See general end user documentation for [Installing a module].

Some arguments of some shortcodes require the module [Advanced Search].


Quick start
-----------

A shortcode is a string to add in a textarea field a site page block. It looks
like `[shortcode key=value]`. There can be multiple features and values can
be protected with quotes or double quotes when they contain spaces: `[shortcode key1="my value" key2='my "value"']`.
When the shortcode is not identified, it is displayed as it.

Shortcodes work exactly like in Omeka Classic, with the same shortcuts, so you
can consult the [Omeka Classic] user manual. Nevertheless, some shortcuts are
marked deprecated and it is recommended to use the more semantic equivalent ones
for Omeka S.

For example, to render the media #51 with the default renderer, you can use:
`[media id=51 player=default]`. This is the equivalent of `[file id=51]` that
was used in Omeka Classic, and that is now a simple alias.


Shortcodes
----------

### Available shortcodes

#### No operation

The shortcode `[noop]` is skipped automatically, whatever its arguments.

#### Count

The shortcode `[count]` allows to get the total of resources. It requires a
resource type : `[count resource=items]`.

The count can be limited by argument `query` or any other generic params: `id`,
`is_featured`, `has_image`, `has_media`, `owner`, `item_set`, `collection`,
`class`, `class_label`, `template`, `template_label`, `tag`.

When the generic params are present, they override the matching api argument set
in the query: `[count resource=items query="resource_class_id=32" class=dctype:PhysicalObject]`.
Note: the shortcodes arguments are a simplified variant of the keys used in the
api.

As most of shortcodes, the argument `span=xxx` allows to wrap the result with a
`span` with the specified value as class.

#### Single resource

The following shortcodes can be use to display a single resource:

- `resource`
- `item`
- `media`
- `item_set`
- `collection` (alias of `item_set`)
- `asset`
- `page`
- `site`
- `annotation` (with module [Annotate])

It displays a single resource. The internal id is required to get it. Example: `[item id=51]`.
The name of the argument `id` can be omitted: `[item 51]`. The numeric id should
be the first argument without key. Other arguments are sent to the partial. For
page and site, the `id` is the internal id or the slug. For pages, the argument
`site` should be used to specify the site, else the current site is used.

The template is the name of the resource by default.

##### As block

By default, a single resource is displayed as a block, similar to the site page
showcase.

##### As link

Use the view template `link` to display the resource as a link: `[item 51 view=link]`.
You can use the shortcode for it `[link 51]` in that case too (see below).

Note that for media, this is the link to the resource page, not to the file. Use
argument `file=original` or `file=large`, etc. to get it (see below).

##### As url

If you just need the url, use the view template `url` to display the resource as
a simple url: `[item id=51 view=url]`, or simply `[item 51 url]`.

##### As a metadata

To display the metadata of a resource, use the key `meta`: `[item 51 meta=o:modified]`.
The field names are the ones used in the json-ld representation, included any
property term, for example `[item 51 meta=dcterms:relation]`.

When multiple values are available, only the first one is displayed.

The standard Omeka metadata starting with `o:` can be shortened, so to get the
creation date of the current page, the shortcode can be `[page meta=created date=long]`.

For dates (created or modified), the args `date` and `time` can be added, with
possible values `none` (default for time), `short`, `medium` (default for date),
`long`, and `full`.

##### Through a player

The param `player` allows to render the resource through a player, for example
`[item id=51 player=Mirador]`. The player should be installed via a module, for
example [LightGallery], [Mirador], [UniversalViewer], [Diva], [ViewerJs], [Verovio],
or anything else as long as the matching view helper exists and can be called
with `__invoke(AbstractResourceEntityRepresentation $resource, array $options = [])`.
Warning: the case of the value should be checked when needed (the first letter
is automatically lower-cased).

When the player doesn't exist, the resource is rendered as a block, except for
media, that is rendered with its own default renderer. The value can be `default`
in that case too: `[media id=52 player=default]`.

The player `image` allows to display the thumbnail of the resource. The
shortcode `[image]` is a shortcode to it: `[image 51]` is like `[item 51 player=image]`.

Specific options are sent to the partial and to the renderer and to the player.

For media, there are specific options for the default renderer:
  - `thumbnail`, the thumbnail type, that can be `large`, `medium` (default) or
    `square`,
  - `align`, to align the thumbnail on `left` (default), `right` or `center`,
  - `show_title`, to specify the type of the title: `item_title` or `file_mane`.

- Deprecated shortcode name for compatibility with Omeka classic:
  - `file`
    Shortcut to `[media player=default]`.

#### Resource link or url

The shortcode `link` can be used to display a link to the resource page: `[link 51]`.

To set the title, use argument `title`, else it will use the default title of
the resource: `[link 51 title="Title of the resource"]`. To use the url as title,
skip the title: use `[link 51 title=]`.

To get only the url, use the view `url`: `[link 51 view=url]`, or simply `[link 51 url]`.
Note that it is no more a link: use `[link 51 title=]` to get the link.

The argument `span` allow to wrap the link or url with a specific class.

_Warning_: This is the link to the resource page, even for media. To get the
link or url to the media file, use `[link 51 file=original]`, where file can be
any of the thumbnail types.

#### List of resources

The following shortcodes can be use to list resources:

- `items`
- `medias` (with a `s` for multiple medias)
- `item_sets`
- `collections` (alias of `item_sets`)
- `pages`
- `sites`
- `annotations` (with module [Annotate])

  To limit resources, use the same arguments than the shortcode `count`.
  The resources are listed according to `num`, `sort`, and `order` if any.

  Assets cannot be listed for now.

- Deprecated shortcode names for compatibility with Omeka classic:
  - `recent_items`
    Shortcut to `[items num=5 sort=created order=desc]`.
  - `featured_items`
    Shortcut to `[items num=1 is_featured=true sort=random]`.
  - `recent_collections`
    Shortcut to `[items num=5 sort=created order=desc]`.
  - `featured_collections`
    Shortcut to `[items num=1 is_featured=true sort=random]`.

### Generic arguments

Some generic arguments are used in many shortcodes, so they are gathered here.

(If the table is not readable, go to the original page of the module [Shortcode]).

| Argument name     | Purpose                                                               | Argument value                    | Example                                                           |
| ----------------- | --------------------------------------------------------------------- | --------------------------------- | ----------------------------------------------------------------- |
| `id`              | Get a list of resources by id.                                        | List or range of integers         | `[items id=15,30,51]`, `[items id=15-51]`                         |
| `ids`             | Deprecated alias of `id`                                              | List or range of integers         | See `id`                                                          |
| `site`            | Get only resources from the specified site                            | Integer                           | `[items site=2]`                                                  |
| `owner`           | Get only resources owned by a specific user                           | Integer                           | `[items owner=2]`                                                 |
| `user`            | Deprecated alias of `owner`                                           | Integer                           | See `owner`                                                       |
| `item_set`        | Get only resources from an item set                                   | List or range of integers         | `[items item_set=7]`                                              |
| `collection`      | Alias of `item_set`                                                   | List or range of integers         | `[items collection=7]`                                            |
| `class`         * | Get only resources from one or multiple classes                       | List of terms or ids              | `[items class=dctype:StillImage,26]`                              |
| `class_label`     | Get only resources from a class via its label                         | String                            | `[items class_label="Physical Object"]`                           |
| `item_type`       | Deprecated alias of `class_label`)                                    | String                            | See `class_label`                                                 |
| `template`        | Get only resources from one or multiple templates                     | List or range of integers         | `[items template=1,2`]                                            |
| `template_label`  | Get only resources from a template via its label                      | String                            | `[items template_label="Base Resource"]`                          |
| `tag`           * | Get only resources with the specified tag (`curation:tag`)            | List of strings                   | `[items tag="alpha, beta, gamma"]`                                |
| `tags`          * | Deprecated alias of `tag`                                             | List of strings                   | See `tag`                                                         |
| `is_featured`     | Get only resources with a value `curation:featured`.                  | Boolean                           | `[collections is_featured=1]`                                     |
| `has_image`     * | Resource with an image (a thumbnail), or not.                         | Boolean                           | `[featured_items has_image=false]`                                |
| `has_media`     * | Resource with a media, or not.                                        | Boolean                           | `[items has_media=true]`                                          |
| `query`           | Specify a query to limit resources                                    | String (advanced search url)      | `[items query="search=xxx"]`                                      |
| `num`             | Number of resources returned. "0" means unlimited. Default: 10.       | Integer                           | `[featured_items num=4]`                                          |
| `sort`            | Property term or specific api value to sort the resource by.          | Property term or json-ld keys     | `[items sort=dcterms:date]`, `[items sort=created]`               |
| `order`           | Order of the sorting                                                  | `a`, `d`, `asc`, or `desc`        | `[items sort=dcterms:title order=asc]`                            |
| `meta`            | Get the specified metadata                                            | Property term or json-ld keys     | `[item 51 meta=dcterms:creator]`, `[item 51 meta=title]`          |
| `span`            | Wrap the output with a span and optionally a class                    | String|Null                       | `[item 51 span=author]`                                           |
| `view`            | Specify a special theme template for specific rendering               | String                            | `[items view=items]`                                              |

The arguments marked with a `*` require the module [Advanced Search].

For boolean values, a `0` or a `false` are false, anything else is true.

A list of integers is a comma-separated list of number or a range separated by
an hyphen: `15,30,51`, `15-51`

For a list of terms, it is recommended to use terms instead of ids, because they
are more portable. The case must be respected for now: `dcterms:ispartof` or `dctype:stillimage`
don't exist, you should use `dcterms:isPartOf` and `dcterms:StillImage`.

The sort specific strings are the one used by the api, like `created`, `modified`,
`random`, and the property terms.

The current site is automatically added. To get results for all sites, use an
empty string: `[items site=]`

The partial template set with `view` should be available under `common/shortcode/`.
For security, the name must not contain a dot. If the template is missing, the
default one is used. Some shortcodes doesn't use a template by default (`count`,
`link`) and you can use argument `span` to wrap it with a class if you don't
need a full template. The templates `link` and `url` are fake templates.

Take care of copy-pasting: often, span and attributes are inserted automatically.
You may need to check the source code of an html text area when an issue occurs.


Development
-----------

Any module can add new shortcode: just add it as a config key under `[shortcodes]`.

The shortcode can be any class that can be invoked; just add the interface
`Shortcode\Shortcode\ShortcodeInterface` to it, or extends the class from the
abstract class `Shortcode\Shortcode\AbstractShortcode`. If a partial is needed,
it is recommended to put it in directory common/shortcode.


TODO
----

- [ ] WordPress shortcodes (alias to Omeka ones, for example `[gallery]`)?
- [ ] Shortcoder any.


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

* Copyright Roy Rosenzweig Center for History and New Media, 2014
* Copyright Daniel Berthereau, 2021 (see [Daniel-KM])

The shortcode parser is an improved version of the one that is used [in WordPress]
since 2008 (version 2.5). The same is used [in Omeka Classic] since 2014
(version 2.2) too, and it can be found in older various places.


[Shortcode]: https://gitlab.com/Daniel-KM/Omeka-S-module-Shortcode
[Omeka S]: https://omeka.org/s
[wikitext]: https://en.wikipedia.org/wiki/Help:Wikitext#Links_and_URLs
[WordPress]: https://wordpress.com/support/shortcodes/
[Omeka Classic]: https://omeka.org/classic/docs/Content/Shortcodes/
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Advanced Search]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch
[Annotate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate
[LightGallery]: https://gitlab.com/Daniel-KM/Omeka-S-module-LightGallery
[Mirador]: https://gitlab.com/Daniel-KM/Omeka-S-module-Mirador
[UniversalViewer]: https://gitlab.com/Daniel-KM/Omeka-S-module-UniversalViewer
[Diva]: https://gitlab.com/Daniel-KM/Omeka-S-module-Diva
[ViewerJs]: https://gitlab.com/Daniel-KM/Omeka-S-module-ViewerJs
[Verovio]: https://gitlab.com/Daniel-KM/Omeka-S-module-Verovio
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Shortcode/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[in WordPress]: https://developer.wordpress.org/reference/functions/get_shortcode_atts_regex/
[in Omeka Classic]: https://github.com/omeka/Omeka/blob/master/application/views/helpers/Shortcodes.php#L96-L117
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
