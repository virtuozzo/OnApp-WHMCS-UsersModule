## Overview
The OnApp Users module for WHMCS allows you to operate (creating/suspending/deleting etc) with OnApp users directly from WHMCS. Clients then log directly into OnApp to build and manage their VMs with WHMCS handling billing.

## Instructions
Please visit our [wiki](https://github.com/OnApp/OnApp-WHMCS-UsersModule/wiki) for detailed instructions _(updated on **23 of July 2014**)_.

## Changelog:
####v3.6.0
 - WHMCS 7 compatibility
 - fix support issues
 
####v3.4.0
 - fix URL handling in payment hook

####v3.3.5
 - improve server IP/hostname and schema handling

####v3.3.0
 - add 'login to OnApp CP' functionality on servers page
 - add the possibility to reset password from client area [read more](https://github.com/OnApp/OnApp-WHMCS-UsersModule/wiki/3.-Setting-up-WHMCS-product#other)
 - improve client area layout

####v3.2.6
 - fix bootstrap and js issues

####v3.2.5
 - drop DB support
 - rewrite client area layout
 - add localization into invoices

####v3.2.0.5
 - handle empty date

####v3.2.0.4
 - add the possibility to upgrade/downgrade product
 - add email notifications for module actions (create, suspend, terminate etc)
 - improve client area layout

####v3.2.0.3
 - fix file inclusion
 - move hooks into module

####v3.2.0.2
 - enable currency conversion

####v3.2.0.1
 - improve logic
 - change invoice generator behavior
 - add commandline options (run any cron file with -h flag to see available options)
