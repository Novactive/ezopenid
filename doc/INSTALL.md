Installing eZOpenId
===================

Requirements:
-------------
- eZ Publish 4.x or eZ Publish Legacy
- Use composer for dependency installation 

Installing:
-----------

1. Edit your composer.json file to add the new dependency

   ```json
    "require": {
        "novactive/ezopenid": "@dev"
    },
    "repositories" : [
        {
             "type": "vcs",
             "url": "https://github.com/Novactive/ezopenid.git"
        }
    ]
   ```

2. Install the dependency with composer

   ```bash
   $ php composer.phar update novactive/ezopenid --prefer-dist
   ```
   
3. Enable the extension in eZ Publish. Do this by opening settings/override/site.ini.append.php ,
   and add in the `[ExtensionSettings]` block:

   ```ini
   ActiveExtensions[]=ezopenid
   ```
4. Enable OpenID SSO handler for your siteaccess. Do this by opening your siteaccess site.ini.append.php file ,
   and add in the `[UserSettings]` block:
   
   ```ini
   SingleSignOnHandlerArray[]=OpenID
   ```
   
5. Update the class autoloads by running the script:

   ```bash
   $ php bin/php/ezpgenerateautoloads.php -e
   ```

Configuration:
--------------

*. Clone the ini file ezopenid/settings/ezopenid.ini.append.php in settings/override/,
   and edit the `[OpenIDSettings]` block.
