## Prerequirements
**PHP 5.3+  
WHMCS 5+**

#####_PHP extensions:_
[Mcrypt](http://php.net/manual/en/mcrypt.installation.php)
[Multibyte String](http://www.php.net/manual/en/mbstring.installation.php)
[SimpleXML](http://php.net/manual/en/simplexml.installation.php) (if you wish to operate with XML-data)

#####_Additional libraries:_
[OnApp PHP Wrapper](https://github.com/OnApp/OnApp-PHP-Wrapper-External)

#####_PHP settings:_
Timezone should be defined in PHP settings. If the timezone is not already set add the following line to your *php.ini*:

```
date.timezone = {desired timezone}
```

Line should looks like **_date.timezone = Europe/Stockholm_**
All available timezones are listed on http://php.net/manual/en/timezones.php


## Installation
- remove/rename any previously used module's files
- copy all files to the root of your WHMCS directory.


## Setting up cronjobs
Add the following commands to your cronjobs:

```bash
15 * * * *    /usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/stat.php
30 0 1 * *    /usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/generate-invoices.php
```

*{WHMCS} is the full path to your WHMCS directory.
First command is usage statistics collector.
Second one is invoice generator.*

**_DO NOT SETUP INVOICE GENERATOR BEFORE TESTING (SEE BELOW)!_**


## Advanced usage
#####_Statistics collector_
By default it starts grab data since last collection date (or the beginning of the month if it run for the first time) till the current time.
You can force collector to grab more data by passing desired  dates in format 'YYYY-MM-DD HH:MM' as a parameter:

```bash
/usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/stat.php -s'2014-01-01 00:00'
/usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/stat.php --since='2014-01-01 00:00' --till='2014-03-15 23:00'
```


#####_Invoice generator_
By default it generates customers invoices relying on collected statistics for the previous month.
As usual it should be run once a month when you want generate invoices.

You can force generator to generate invoices for the certain period by passing desired starting and ending dates in format 'YYYY-MM-DD HH:MM' as a parameters:

```bash
/usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/generate-invoices.php --since='2014-03-15 00:00' --till='2014-04-15 23:00'
```

**_for the proper calculation all dates should be entered in the server's timezone_**


## Testing invoice generator
We strongly recommend to test invoice generator before using in production to be sure that it works properly.
For testing you should setup statistics collector (or run it from console) and run tester:

```bash
/usr/bin/php -q	{WHMCS}/modules/servers/onappusers/cronjobs/test.php -l
```

_Tester functionally is the same as generator itself, but it writes processed data to the file for review instead of generating real invoices._
