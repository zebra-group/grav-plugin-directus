# Directus Plugin

The **Directus** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav). a directus api plugin 1

## Installation

Installing the Directus plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](http://learn.getgrav.org/advanced/grav-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install directus

This will install the Directus plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/directus`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `directus`. You can find these files on [GitHub](https://github.com//grav-plugin-directus) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/directus

> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com//grav-plugin-directus/blob/master/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/directus/directus.yaml` to `user/config/plugins/directus.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
```
Note that if you use the Admin Plugin, a file with your configuration named directus.yaml will be saved in the `user/config/plugins/`-folder once the configuration is saved in the Admin.

```yaml
imageServer: false
```
partially implemented - placeholder only yet

```yaml
disableCors: true
```
with this setting, the check for ssl on directus api can be disabled or enabled. By default CORS is disabled.

```yaml
directus:
  token: 1234567
  email: your@email.com
  password: supersavepassword
  directusAPIUrl: http://your.api.com
  hookPrefix: your-prefix
  synchronizeTables:
      country:
          depth: 2
      currency:
          depth: 2
```

In this section, you have to configure the Directus API Access. There are two ways of authentification. The first way is to use a static token. You can find the token in the table with your user credentials on Directus server. Otherwise you have to enter your username and password. This is not neccessary if a security token is given. The plugin will request a temporary authentification token from the API. If a static token is given, the username and password are not used.

The hook prefix is a key for the hook routes. You can change it for security reasons. So, the hooks are accessible under e.g. https://your.site/your-prefix/refresh-global for the global update hook.

## Flex Objects
Since GRAV 1.7 you can use the Flex-Objects Plugin for Datahandling. The Directus plugins offers to synchronize Directus collections with the flex-objects. For this you have to create flex-object blueprints for your collections. Some simple sample blueprints are in blueprints/flex-objects.

In the next step you have to configure your collection mapping. This is the part in the config under "synchronizeTables". The Key is the table name and the depth is the depth of the directus request.

### Webhook API Endpoints
http://your.api.com/your-prefix//sync-flexobjects - synchronizes all configured directus collections with the flex-objects

http://your.api.com/your-prefix//sync-flexobject - synchronizes one directus collection entity with flex-objects. For use in Directus Webhook module only.
## Usage

```md
---
title: My Page
directus:
    collection: table_name
    depth: 4
    id: 12
    filter:
        field_name:
            operator: _eq
            value: filter_value
        ...
    limit: 6
    sort: sorting_field
---
```
### Settings overview
You have to configure your Directus request in the header section of your .md page file like in the example. The only mandatory setting is the collection argument. If the request settings are correct, the plugin creates a data.json file from the response on the first page reload. You can load the response file in the twig template with {% set content = directus.get(page) %}.

### optional parameters

#### depth
The depth parameter sets the array depth in the response. Default: 2

#### id
With the optional id parameter, you can request a single element from a collection.

#### filters
with the filters parameter it is possible to define multiple filters for the request.
```md
---
title: My Page
directus:
    collection: users
    filter:
        full_name:
            operator: _eq
            value: John Doe
        birth_date:
            operator: _not
            value: 1984
---
```
Here is a configuration sample for the filters section. An overview with all possible operators you can find here: https://docs.directus.io/api/query/filter.html
If no operator is set, the default operator is "=".

It is possible to filter over deep nested relations. For this you have to define the path as dot separated string.

```md
directus:
    collection: users
    filter:
        groups.groups_id.address.phone:
            operator: _eq
            value: +4955512345
```

#### limit
This is self explaining. This limits the amount of json array items to given limit. default: -1 (all items)

#### sort
The sort parameter defines, which field is used for the response array sorting. More options here: https://docs.directus.io/api/query/sort.html

## Translations

When using directus build in translation functionality there is a helper function `directus.translate` for twig:

```twig
{% set currentLang = grav.language.getActive ?: grav.config.site.default_lang %}
{% set refData = directus.get( page, currentLang ) %}
{% set description = directus.translate( refData.related_content_elements[0].related_content_id, currentLang ) %}
{{description.content_description|markdown|raw }}
```

We assume the language is defined as two character code like `de` or `en`. `directus.translate` will need the (sub-) object to translate as first parameter and the language code as second. The object must have the directus generated `translations` in the first level. The function returns the orignal object but replaced all fields available in the first level with its translation.

Before:
```yaml
object:
  id: 4
  content_description: 'english original content'
  …
  translations:
    content_description: 'deutsche Überseztung'
```

After:
```yaml
object:
  id: 4
  content_description: 'deutsche Überseztung'
  …
  translations:
    content_description: 'deutsche Überseztung'
```

When calling `directus.get` with a language as second parameter it will do the same as `directus.translate` with the first level of the data.

## Misc Twig Tags
```md
<img src="{{ directusFile(image, {width: 570, height: 620}) }}" loading="lazy" />
```

Directus 9 uses the assets endpoint for requesting files only. The twig Tag downloads the file, if it does not exist, and returns the relative url to the file. The first parameter is the file reference object from directus' API. The file will be downloaded to the folder defined in the plugin configuration under ``assetsFolderName`` which defaults to ``assets``. Please add this name to ``pages.ignore_folders`` in your system.yaml.
The second parameter is an array with you image manipulation parameters. For a complete parameter list, look here: https://docs.directus.io/reference/api/assets.html

## CLI commands

### bin/plugin directus cleanup

```md
Description:
Deletes all assets and data.json from page tree

Usage:
cleanup [options]

Options:
-j, --json            delete data.json files only
-a, --assets          delete assets folders and its content only
-h, --help            Display this help message
-q, --quiet           Do not output any message
-V, --version         Display this application version
--ansi            Force ANSI output
--no-ansi         Disable ANSI output
-n, --no-interaction  Do not ask any interactive question
--env[=ENV]       Use environment configuration (defaults to localhost)
--lang[=LANG]     Language to be used (defaults to en)
-v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

```
