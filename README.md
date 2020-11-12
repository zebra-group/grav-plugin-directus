# Directus Plugin

The **Directus** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav). a directus api plugin

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
directus:
  token: 1234567
  email: your@email.com
  password: supersavepassword
  directusAPIUrl: http://your.api.com
  projectName: project-name
  hookPrefix: your-prefix
```

In this section, you have to configure the Directus API Access. There are two ways of authentification. The first way is to use a static token. You can find the token in the table with your user credentials on Directus server. Otherwise you have to enter your username and password. This is not neccessary if a security token is given. The plugin will request a temporary authentification token from the API. If a static token is given, the username and password are not used.

The hook prefix is a key for the hook routes. You can change it for security reasons. So, the hooks are accessible under e.g. https://your.site/your-prefix/refresh-global for the global update hook. 
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
            operator: eq
            value: filter_value
        ...
    limit: 6 
---
```
### Settings overview
You have to configure your Directus request in the header section of your .md page file like in the example. The only mandatory setting is the collection argument. If the request settings are correct, the plugin creates a data.json file from the response on the first page reload. You can load the response file in the twig template with {{ directus.get() }}.

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
            operator: eq
            value: John Doe
        birth_date:
            operator: not
            value: 1984
---
```
Here is a configuration sample for the filters section. An overview with all possible operators you can find here: https://docs.directus.io/api/query/filter.html
If no operator is set, the default operator is "=".

#### limit
This is self explaining. This limits the amount of json array items to given limit. default: -1 (all items) 

## To Do

- [flexible Webhooks] - this features is planned for the near future. With flexible webhooks it will be possible to refresh single pages per remote request.

