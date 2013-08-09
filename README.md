TYPO3Security
============

Checks if there are insecure, modified or outdated extensions in your local TYPO3 instances

What it does
------------

This small script is looking for TYPO3 instances under given `path` and `pathLevel` and checking if the extensions of the instances are a security risk.

* Will download TER
* Will compare all from TER as insecure marked extensions to local Extension versions, if requested
* Will compare all local Extensions to TER extensions to find outdated ones
* Will compare MD5 CheckSum to check wether there is a modified TYPO3 Extension.

### Why this is usefull

#### Security Team Release

If the security team finds once again security problems in several extensions, just run this script to check if one of your extensions is in the need of an upate!

#### Avoid Updating Modified Extensions without Backup

Will warn you about modified Extensions, so you are able to be safe and prepared before updating on security release.

#### No more need to enter static pages ones a while

Normally you have to enter all your installations within a specific time to check if all the TYPO3 extensions are up to date. With this little script you are able to runs a cron once a week to get informed if there are updates available for an extension.


How to use it
-------------

##### General Usage:

    $ ./check.php --path="..." --pathLevel="2" [--searchOutdated] [--searchInsecure] [--ignoreModified] [--warnModified] [--checkModificationOnlyFoundInTer] [--ignoreExtensions]

##### My CronJob:

    30 5 * * 1 /var/www/TYPO3Security/check.php --path="/var/www/" --pathLevel="2" --searchOutdated --searchInsecure --checkModificationOnlyFoundInTer --ignoreModified --ignoreExtensions="newloginbox,powermail=1.6.10"

### Params

##### Path

The path where the search for local instances should start. For example `/var/www/` or `/home/`

*Has to end with a slash!*

##### PathLevel

The depth of your architecture. For example:

`$path = '/var/www/'` and TYPO3 instances in `/var/www/$DOMAIN/htdocs/` then use `2`.

    /var/www/ => PATH
    $DOMAIN/  => Level 1
    htdocs/   => Level 2

##### searchOutdated

If option is set will compare each extension with the TERs highest version number and alert if a never version is available in TER.

##### searchInsecure

If option is set will compare each extension with the TERs insecure marked extensions and versions and warn if a comparison is found.

##### ignoreModified

If option is set will suppress warnings if extension is modified

##### warnModified

If option is set will inform about every modified extension

##### checkModificationOnlyFoundInTer

If option is set will check for modification only if extension is represented in TER.

##### ignoreExtensions

This option is able to get a comma separated list of extensions in two different formats:

* `extensionName` will ignore all Extensions with given extensionName
* `extensionName=version` will ignore all Extensions with given extensionName and setted version. Multible times the same extensionName is allowed.

How to contribute
-----------------
The TYPO3 Community lives from your contribution!

You wrote a feature? - Start a pull request!

You found a bug? - Write an issue report!

You are in the need of a feature? - Write a feature request!

You have a problem with the usage? - Ask!
