## Installation  
Copy all files to the root of your WHMCS directory.  
Remove any previously used module's cronfiles.


## Simple automatic usage
Add the following commands to your cronjobs:  

	30 * * * *    /usr/bin/php {WHMCS}/modules/servers/onappusers/cronjobs/onapp.stat.php
	30 0 1 * *    /usr/bin/php {WHMCS}/modules/servers/onappusers/cronjobs/onapp.invoices.php

*{WHMCS} is the full path to your WHMCS directory.  
First command is usage statistics collector.  
Second command is invoice generator.*  
**_DO NOT SETUP INVOICE GENERATOR BEFORE TESTING (SEE BELOW)!_**


## Advanced usage
**_Statistics collector_**  
By default it starts grab data since last usage date (or the begining of the month if it run for the first time) till the current time.

You can force collector to grab more data by passing desired starting date in format 'YYYY-MM-DD HH:MM:SS' as a parameter:

	/usr/bin/php {WHMCS}/modules/servers/onappusers/cronjobs/onapp.stat.php '2012-01-01 00:00:00'


**_Invoice generator_**  
By default it generates customers invoices relying on collected statistics for the previous month.
As usual it should be run once a month when you want generate invoices.

Invoice generator can be run any time you want generate invoices. In such case it generates invoices from last usage date till now.

You can force generator to generate invoices for the certain period by passing  
desired starting and ending dates in format 'YYYY-MM-DD HH:MM:SS' as a parameter:

	/usr/bin/php {WHMCS}/modules/servers/onappusers/cronjobs/onapp.invoices.php '2012-03-14 00:00:00' '2012-03-28 00:00:00'

**_for the proper calculation all dates should be entered in the server's timezone_**


## Testing invoice generator 
We strongly recommend to test invoice generator before using in production to be sure that it works properly.  
For testing you should setup statistics collector (or run it from console) and run tester:

	/usr/bin/php	{WHMCS}/modules/servers/onappusers/cronjobs/onapp.invoices.test.php
Tester functionally is the same as generator itself, but it writes processed data to the file for review instead of generating real invoices.