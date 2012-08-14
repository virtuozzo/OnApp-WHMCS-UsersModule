## Prerequirements  
WHMCS 5+  
PHP 5.1+ built with IPv6 support enabled.

PHP extensions:  
JSON (is builtin since PHP 5.2 or could be installed from [PECL](http://php.net/manual/en/json.installation.php))  
[SimpleXML](http://php.net/manual/en/simplexml.installation.php)  
[Mcrypt](http://php.net/manual/en/mcrypt.installation.php)


## Installation  
* execute any of the following commands in your terminal (without asterisk):  
\* `curl https://raw.github.com/OnApp/OnApp-WHMCS-UsersModule/master/install.sh --O install.sh && sh ./install.sh`  
\* `wget https://raw.github.com/OnApp/OnApp-WHMCS-UsersModule/master/install.sh && sh ./install.sh`
* setup cronjobs according to [instruction](https://github.com/OnApp/OnApp-WHMCS-UsersModule/blob/master/modules/servers/onappusers/cronjobs/README.md)