## Prerequirements  
PHP 5.1.2+ built with IPv6 support enabled

PHP extensions:
JSON (is builtin since PHP 5.2+ or could be installed from PECL http://php.net/manual/en/json.installation.php )
SimpleXML (http://php.net/manual/en/simplexml.installation.php)
Mcrypt (http://php.net/manual/en/mcrypt.installation.php)







Make sure that you use the latest [OnApp PHP Wrapper](https://github.com/OnApp/OnApp-PHP-Wrapper/tree/master/wrapper) version.  
Timezone should be defined in PHP settings. If the timezone is not already set add the following line to your php.ini:

	date.timezone = {desired timezone}
Line should looks like **_date.timezone = Europe/Stockholm_**  
All available timezones are listed on http://php.net/manual/en/timezones.php


## Installation  
Copy all files to the root of your WHMCS directory.  
Remove any previously used module's cronfiles.


## Simple automatic usage
Add the following commands to your cronjobs:  

	15 * * * *    /usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/onapp.stat.php
	30 0 1 * *    /usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/onapp.invoices.php

*{WHMCS} is the full path to your WHMCS directory.  
First command is usage statistics collector.  
Second command is invoice generator.*  
**_DO NOT SETUP INVOICE GENERATOR BEFORE TESTING (SEE BELOW)!_**


## Advanced usage
**_Statistics collector_**  
By default it starts grab data since last usage date (or the begining of the month if it run for the first time) till the current time.

You can force collector to grab more data by passing desired starting date in format 'YYYY-MM-DD HH:MM:SS' as a parameter:

	/usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/onapp.stat.php '2012-01-01 00:00:00'


**_Invoice generator_**  
By default it generates customers invoices relying on collected statistics for the previous month.
As usual it should be run once a month when you want generate invoices.

Invoice generator can be run any time you want generate invoices. In such case it generates invoices from last usage date till now.

You can force generator to generate invoices for the certain period by passing  
desired starting and ending dates in format 'YYYY-MM-DD HH:MM:SS' as a parameter:

	/usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/onapp.invoices.php '2012-03-14 00:00:00' '2012-03-28 00:00:00'

**_for the proper calculation all dates should be entered in the server's timezone_**


## Testing invoice generator 
We strongly recommend to test invoice generator before using in production to be sure that it works properly.  
For testing you should setup statistics collector (or run it from console) and run tester:

	/usr/bin/php -q	{WHMCS}/modules/servers/onappusers/cronjobs/onapp.invoices.test.php
Tester functionally is the same as generator itself, but it writes processed data to the file for review instead of generating real invoices.