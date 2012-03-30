-- Create syntax for TABLE 'onapp_itemized_disks'
CREATE TABLE IF NOT EXISTS `onapp_itemized_disks` (
	`stat_id` int(11) NOT NULL,
	`id` int(11) NOT NULL,
	`disk_size` float NOT NULL,
	`disk_size_cost` float(10,2) NOT NULL,
	`data_read` float NOT NULL,
	`data_read_cost` float(10,2) NOT NULL,
	`data_written` float NOT NULL,
	`data_written_cost` float(10,2) NOT NULL,
	`reads_completed` float NOT NULL,
	`reads_completed_cost` float(10,2) NOT NULL,
	`writes_completed` float NOT NULL,
	`writes_completed_cost` float(10,2) NOT NULL,
	`label` varchar(255) NOT NULL,
	UNIQUE KEY `stat_id` (`stat_id`,`id`)
) DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'onapp_itemized_network_interfaces'
CREATE TABLE IF NOT EXISTS `onapp_itemized_network_interfaces` (
	`stat_id` int(11) NOT NULL,
	`id` int(11) NOT NULL,
	`ip_addresses` float NOT NULL,
	`ip_addresses_cost` float(10,2) NOT NULL,
	`rate` float NOT NULL,
	`rate_cost` float(10,2) NOT NULL,
	`data_received` float NOT NULL,
	`data_received_cost` float(10,2) NOT NULL,
	`data_sent` float NOT NULL,
	`data_sent_cost` float(10,2) NOT NULL,
	`label` varchar(255) NOT NULL,
	UNIQUE KEY `stat_id` (`stat_id`,`id`)
) DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'onapp_itemized_stat'
CREATE TABLE IF NOT EXISTS `onapp_itemized_stat` (
	`id` int(11) unsigned NOT NULL,
	`whmcs_user_id` int(11) unsigned NOT NULL,
	`onapp_user_id` int(11) unsigned NOT NULL,
	`server_id` int(11) unsigned NOT NULL,
	`vm_id` int(11) unsigned NOT NULL,
	`date` datetime NOT NULL,
	`currency` char(3) NOT NULL DEFAULT '',
	`usage_cost` float(10,2) NOT NULL,
	`total_cost` float(10,2) NOT NULL,
	`vm_resources_cost` float(10,2) NOT NULL,
	PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'onapp_itemized_virtual_machines'
CREATE TABLE IF NOT EXISTS `onapp_itemized_virtual_machines` (
	`stat_id` int(11) NOT NULL,
	`id` int(11) NOT NULL,
	`cpu_shares` float NOT NULL,
	`cpu_shares_cost` float(10,2) NOT NULL,
	`cpus` float NOT NULL,
	`cpus_cost` float(10,2) NOT NULL,
	`memory` float NOT NULL,
	`memory_cost` float(10,2) NOT NULL,
	`template` float NOT NULL,
	`template_cost` float(10,2) NOT NULL,
	`cpu_usage` float NOT NULL,
	`cpu_usage_cost` float(10,2) NOT NULL,
	`label` varchar(255) NOT NULL,
	UNIQUE KEY `stat_id` (`stat_id`,`id`)
) DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'onapp_itemized_resources'
CREATE TABLE IF NOT EXISTS `onapp_itemized_resources` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`whmcs_user_id` int(11) unsigned NOT NULL,
	`onapp_user_id` int(11) unsigned NOT NULL,
	`server_id` int(11) unsigned NOT NULL,
	`date` datetime NOT NULL,
	`backup_cost` float DEFAULT NULL,
	`edge_group_cost` float DEFAULT NULL,
	`monit_cost` float DEFAULT NULL,
	`storage_disk_size_cost` float DEFAULT NULL,
	`template_cost` float DEFAULT NULL,
	`vm_cost` float DEFAULT NULL,
	`user_resources_cost` float DEFAULT NULL,
	`total_cost` float DEFAULT NULL,
	`currency` char(3) NOT NULL DEFAULT '',
	`backup_count_cost` float DEFAULT NULL,
	`backup_disk_size_cost` float DEFAULT NULL,
	`template_count_cost` float DEFAULT NULL,
	`template_disk_size_cost` float DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `date_users_server_integrity` (`whmcs_user_id`,`onapp_user_id`,`server_id`,`date`)
) DEFAULT CHARSET=utf8;


-- Create syntax for TABLE 'onapp_itemized_invoices'
CREATE TABLE IF NOT EXISTS `onapp_itemized_invoices` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`whmcs_user_id` int(11) unsigned NOT NULL,
	`onapp_user_id` int(11) unsigned NOT NULL,
	`server_id` int(11) unsigned NOT NULL,
	`invoice_id` int(11) unsigned NOT NULL,
	`date` datetime NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `invoive_check_integrity` (`whmcs_user_id`,`onapp_user_id`,`server_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;