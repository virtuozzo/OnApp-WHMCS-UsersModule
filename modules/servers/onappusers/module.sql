-- module tables
-- Create syntax for table 'tblonappusers'
CREATE TABLE IF NOT EXISTS `tblonappusers` (
		`server_id`     INT(11) NOT NULL,
		`client_id`     INT(11) NOT NULL,
		`service_id`    INT(11) NOT NULL,
		`onapp_user_id` INT(11) NOT NULL,
		`password`      TEXT    NOT NULL,
		`email`         TEXT    NOT NULL,
		PRIMARY KEY (`server_id`, `client_id`, `service_id`),
		KEY `client_id` (`client_id`)
)
		ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- cronjob tables
-- Create syntax for table 'onapp_itemized_disks'
CREATE TABLE IF NOT EXISTS `onapp_itemized_disks` (
		`stat_id`               INT(11)      NOT NULL,
		`id`                    INT(11)      NOT NULL,
		`disk_size`             FLOAT        NOT NULL,
		`disk_size_cost`        FLOAT(10, 2) NOT NULL,
		`data_read`             FLOAT        NOT NULL,
		`data_read_cost`        FLOAT(10, 2) NOT NULL,
		`data_written`          FLOAT        NOT NULL,
		`data_written_cost`     FLOAT(10, 2) NOT NULL,
		`reads_completed`       FLOAT        NOT NULL,
		`reads_completed_cost`  FLOAT(10, 2) NOT NULL,
		`writes_completed`      FLOAT        NOT NULL,
		`writes_completed_cost` FLOAT(10, 2) NOT NULL,
		`label`                 VARCHAR(255) NOT NULL,
		UNIQUE KEY `stat_id` (`stat_id`, `id`)
)
		ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for table 'onapp_itemized_network_interfaces'
CREATE TABLE IF NOT EXISTS `onapp_itemized_network_interfaces` (
		`stat_id`            INT(11)      NOT NULL,
		`id`                 INT(11)      NOT NULL,
		`ip_addresses`       FLOAT        NOT NULL,
		`ip_addresses_cost`  FLOAT(10, 2) NOT NULL,
		`rate`               FLOAT        NOT NULL,
		`rate_cost`          FLOAT(10, 2) NOT NULL,
		`data_received`      FLOAT        NOT NULL,
		`data_received_cost` FLOAT(10, 2) NOT NULL,
		`data_sent`          FLOAT        NOT NULL,
		`data_sent_cost`     FLOAT(10, 2) NOT NULL,
		`label`              VARCHAR(255) NOT NULL,
		UNIQUE KEY `stat_id` (`stat_id`, `id`)
)
		ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for table 'onapp_itemized_stat'
CREATE TABLE IF NOT EXISTS `onapp_itemized_stat` (
		`id`                INT(11) UNSIGNED NOT NULL,
		`whmcs_user_id`     INT(11) UNSIGNED NOT NULL,
		`onapp_user_id`     INT(11) UNSIGNED NOT NULL,
		`server_id`         INT(11) UNSIGNED NOT NULL,
		`vm_id`             INT(11) UNSIGNED NOT NULL,
		`date`              DATETIME         NOT NULL,
		`currency`          CHAR(3)          NOT NULL DEFAULT '',
		`usage_cost`        FLOAT(10, 2)     NOT NULL,
		`total_cost`        FLOAT(10, 2)     NOT NULL,
		`vm_resources_cost` FLOAT(10, 2)     NOT NULL,
		PRIMARY KEY (`id`)
)
		ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for table 'onapp_itemized_virtual_machines'
CREATE TABLE IF NOT EXISTS `onapp_itemized_virtual_machines` (
		`stat_id`         INT(11)      NOT NULL,
		`id`              INT(11)      NOT NULL,
		`cpu_shares`      FLOAT        NOT NULL,
		`cpu_shares_cost` FLOAT(10, 2) NOT NULL,
		`cpus`            FLOAT        NOT NULL,
		`cpus_cost`       FLOAT(10, 2) NOT NULL,
		`memory`          FLOAT        NOT NULL,
		`memory_cost`     FLOAT(10, 2) NOT NULL,
		`template`        FLOAT        NOT NULL,
		`template_cost`   FLOAT(10, 2) NOT NULL,
		`cpu_usage`       FLOAT        NOT NULL,
		`cpu_usage_cost`  FLOAT(10, 2) NOT NULL,
		`label`           VARCHAR(255) NOT NULL,
		UNIQUE KEY `stat_id` (`stat_id`, `id`)
)
		ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for table 'onapp_itemized_resources'
CREATE TABLE IF NOT EXISTS `onapp_itemized_resources` (
		`id`                      INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`whmcs_user_id`           INT(11) UNSIGNED NOT NULL,
		`onapp_user_id`           INT(11) UNSIGNED NOT NULL,
		`server_id`               INT(11) UNSIGNED NOT NULL,
		`date`                    DATETIME         NOT NULL,
		`backup_cost`             FLOAT DEFAULT NULL,
		`edge_group_cost`         FLOAT DEFAULT NULL,
		`monit_cost`              FLOAT DEFAULT NULL,
		`storage_disk_size_cost`  FLOAT DEFAULT NULL,
		`template_cost`           FLOAT DEFAULT NULL,
		`vm_cost`                 FLOAT DEFAULT NULL,
		`user_resources_cost`     FLOAT DEFAULT NULL,
		`total_cost`              FLOAT DEFAULT NULL,
		`currency`                CHAR(3)          NOT NULL DEFAULT '',
		`backup_count_cost`       FLOAT DEFAULT NULL,
		`backup_disk_size_cost`   FLOAT DEFAULT NULL,
		`template_count_cost`     FLOAT DEFAULT NULL,
		`template_disk_size_cost` FLOAT DEFAULT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `date_users_server_integrity` (`whmcs_user_id`, `onapp_user_id`, `server_id`, `date`)
)
		ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for table 'onapp_itemized_last_check'
CREATE TABLE IF NOT EXISTS `onapp_itemized_last_check` (
		`serverID`    INT(10) UNSIGNED NOT NULL,
		`WHMCSUserID` INT(10) UNSIGNED NOT NULL,
		`OnAppUserID` INT(10) UNSIGNED NOT NULL,
		`Date`        DATETIME         NOT NULL,
		UNIQUE KEY `integrety` (`serverID`, `WHMCSUserID`, `OnAppUserID`)
)
		ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table `onapp_itemized_disks`
ALTER TABLE `onapp_itemized_disks` ADD COLUMN `disk_min_iops` FLOAT(10, 2) NOT NULL;

ALTER TABLE `onapp_itemized_disks` ADD COLUMN `disk_min_iops_cost` FLOAT(10, 2) NOT NULL;

ALTER TABLE `onapp_itemized_disks` MODIFY COLUMN `disk_min_iops_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_disks` MODIFY COLUMN `disk_size_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_disks` MODIFY COLUMN `data_read_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_disks` MODIFY COLUMN `data_written_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_disks` MODIFY COLUMN `reads_completed_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_disks` MODIFY COLUMN `writes_completed_cost` FLOAT(20, 12) NOT NULL;

-- Table `onapp_itemized_network_interfaces`
ALTER TABLE `onapp_itemized_network_interfaces` MODIFY COLUMN `ip_addresses_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_network_interfaces` MODIFY COLUMN `rate_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_network_interfaces` MODIFY COLUMN `data_received_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_network_interfaces` MODIFY COLUMN `data_sent_cost` FLOAT(20, 12) NOT NULL;

-- Table `onapp_itemized_stat`
ALTER TABLE `onapp_itemized_stat` MODIFY COLUMN `usage_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_stat` MODIFY COLUMN `total_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_stat` MODIFY COLUMN `vm_resources_cost` FLOAT(20, 12) NOT NULL;

-- Table `onapp_itemized_virtual_machines`
ALTER TABLE `onapp_itemized_virtual_machines` MODIFY COLUMN `cpu_shares_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_virtual_machines` MODIFY COLUMN `cpus_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_virtual_machines` MODIFY COLUMN `memory_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_virtual_machines` MODIFY COLUMN `template_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_virtual_machines` MODIFY COLUMN `cpu_usage_cost` FLOAT(20, 12) NOT NULL;

-- Table `onapp_itemized_resources`
ALTER TABLE `onapp_itemized_resources` ADD COLUMN `customer_network_cost` FLOAT(10, 2) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `backup_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `edge_group_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `monit_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `storage_disk_size_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `template_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `vm_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `user_resources_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `total_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `backup_count_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `backup_disk_size_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `template_count_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `template_disk_size_cost` FLOAT(20, 12) NOT NULL;

ALTER TABLE `onapp_itemized_resources` MODIFY COLUMN `customer_network_cost` FLOAT(20, 12) NOT NULL;

-- Table `onapp_itemized_invoices`
DROP DATABASE IF EXISTS `onapp_itemized_invoices`;

DROP DATABASE IF EXISTS `onapp_itemized_last_check`;