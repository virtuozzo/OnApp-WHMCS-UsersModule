## Prerequirements  
WHMCS 5+  
PHP 5.1+ built with IPv6 support enabled.

PHP extensions:  
JSON (is builtin since PHP 5.2 or could be installed from [PECL](http://php.net/manual/en/json.installation.php))  
[SimpleXML](http://php.net/manual/en/simplexml.installation.php)  
[Mcrypt](http://php.net/manual/en/mcrypt.installation.php)


## Installation  
* make any directory
* go to that directory
* execute `git clone --recursive git://github.com/OnApp/OnApp-WHMCS-UsersModule.git .`
* move `includes` and `modules` directories inside your WHMCS root directory
* setup cronjobs according to [instruction](https://github.com/OnApp/OnApp-WHMCS-UsersModule/blob/master/modules/servers/onappusers/cronjobs/README.md)