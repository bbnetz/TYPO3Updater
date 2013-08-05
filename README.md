TYPO3Updater
============

Updates local TYPO3 instances to current TYPO3 version if requested. Current TYPO3 version means 4.5.19 to 4.5.20 and not to another branch. Only BugFixes and SecurityFixes, no update to a new Branch! So no breaking changes are going to happen!

What it does
------------

This small script is looking for TYPO3 instances under given `path` and `pathLevel` and checking if downloadable TYPO3 Versions are newer.

* Will download latest\_stable, latest\_old_stable, latest\_lts and latest\_deprectated to local path
* Will search for TYPO3 instances under path with pathLevel
* Will check for each TYPO3 instance if is updateable and update if not running in dryMode
* Is able to use certain updateModes 
    * Copy every file
    * Symlink from a global server path
    * Copy typo3src to folder and symlinks to that typo3src
    * Find out what is currently used and use this way!

### Why this is usefull

#### Check if everything is up-to-date

Be happy! Your PatchDay is now done in a few minutes. If you are lucky you are not in the need of updateing DB settings. If you are afraid of possible missbehaviour stay in dryMode! You will see every command that is recommend to update your instances.

#### Automatically updates

If you tested this script enought you are able to setup a crontab line to automatically update your TYPO3 instances. You will be informed via mail from which version to which version your instances are going to be updated. Of yource you are also able to run dryMode to do a manually update with given commands in less then a few hours. But why doing this work by yourself?

How to use it
-------------

##### General Usage:

**It is highliy recommend to run this script in drymode for the first runs to check if everything works like expected!**

    $ ./update.php --templatePath="..." --templateOwner="..." --path="..." --pathLevel="2" --workMode="..." [--forceUpdate] [--pathOwner="..."] [--dryRun] [--suppressOutDated]

##### My CronJob:

    30 4 * * * /var/www/TYPO3Updater/update.php --templatePath="/var/www/TYPO3Templates/" --templateOwner="www-data:www-data" --path="/var/www/" --pathLevel="2" --workMode="current" --suppressOutDated

### Params

##### templatePath

The path where the localCopys of the TYPO3 Template should be stored. For example `/var/www/TYPO3Template/` or `/home/`.

*Has to end with a slash!*

##### templateOwner

The user ( and group ) that should own the TYPO3 Templates. For example `root:root` or `www-data`.

##### Path

The path where the search for local instances should start. For example `/var/www/` or `/home/`

*Has to end with a slash!*

##### PathLevel

The depth of your architecture. For example:

`$path = '/var/www/'` and TYPO3 instances in `/var/www/$DOMAIN/htdocs/` then use `2`.

    /var/www/ => PATH
    $DOMAIN/  => Level 1
    htdocs/   => Level 2

##### workMode

WorkMode for updating. Currently supported:

* `copy` *- Copies all important files from the templatesFolder to the TYPO3 instance*
* `current` *- Figgures out what is currenty used and is able to use different workModes for each instance*
* `symlink` *- Symlinks from global source. Exactly Symlinks from templateFolder*
* `symlink_copy` *- Copies needed templateFiles to localDir and symlinks to local copy of template*

##### forceUpdate

If set will try to update everything, even if no update is needed.

Nice way to figgure out which TYPO3 instances are running on which version or to change workmode globally.

##### pathOwner

The user ( and group ) that should own the modified files and pathes for each TYPO3 instance. For example `root:root` or `www-data`.

If not set will use owner and group of parent directory of index.php

##### dryRun

If set will only do a dryRun without executing a command. This is highly recommend for testing!

##### suppressOutDated

If set will suppress warnings for TYPO3 instances that are not updateable with a latest version.
    

How to contribute
-----------------
The TYPO3 Community lives from your contribution!

You wrote a feature? - Start a pull request!

You found a bug? - Write an issue report!

You are in the need of a feature? - Write a feature request!

You have a problem with the usage? - Ask!
