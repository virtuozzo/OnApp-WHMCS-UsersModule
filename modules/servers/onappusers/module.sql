-- module tables
-- Create syntax for table 'tblonappusers'
CREATE TABLE IF NOT EXISTS `tblonappusers` (
    `server_id`     INT(11) NOT NULL,
    `client_id`     INT(11) NOT NULL,
    `service_id`    INT(11) NOT NULL,
    `onapp_user_id` INT(11) NOT NULL,
    PRIMARY KEY (`server_id`, `client_id`, `service_id`),
    KEY `client_id` (`client_id`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for table 'tblonappusers_invoices'
CREATE TABLE IF NOT EXISTS `tblonappusers_invoices` (
    `id`     INT(11)         NOT NULL,
    `amount` DECIMAL(20, 12) NOT NULL,
    PRIMARY KEY (`id`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Update syntax for table 'tblonappusers'
ALTER TABLE `tblonappusers` ADD `billing_type` ENUM('postpaid','prepaid') NOT NULL DEFAULT 'postpaid' AFTER `onapp_user_id`;

-- Create syntax for table 'tblonappusers_Hourly_Stat'
CREATE TABLE IF NOT EXISTS `tblonappusers_Hourly_Stat` (
    `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `server_id`     INT(11)          DEFAULT NULL,
    `client_id`     INT(11)          DEFAULT NULL,
    `onapp_user_id` INT(11)          DEFAULT NULL,
    `cost`          DOUBLE(20, 12)   DEFAULT NULL,
    `start_date`    DATETIME         DEFAULT NULL,
    `end_date`      DATETIME         DEFAULT NULL,
    PRIMARY KEY (`id`)
)
ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- Create syntax for table 'tblonappusers_Hourly_LastCheck'
CREATE TABLE IF NOT EXISTS `tblonappusers_Hourly_LastCheck` (
    `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `server_id`     INT(10) UNSIGNED NOT NULL,
    `client_id`     INT(11)          DEFAULT NULL,
    `onapp_user_id` INT(11)          DEFAULT NULL,
    `date`          DATETIME         NOT NULL,
    PRIMARY KEY (`id`)
)
ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- Create syntax for table 'tblonappusers_Hourly_Buffer'
CREATE TABLE IF NOT EXISTS `tblonappusers_Hourly_Buffer` (
    `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `server_id`     INT(11)          DEFAULT NULL,
    `client_id`     INT(11)          DEFAULT NULL,
    `onapp_user_id` INT(11)          DEFAULT NULL,
    `buffer`       DOUBLE(20, 12)   DEFAULT NULL,
    PRIMARY KEY (`id`)
)
ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- Update syntax for table 'tblonappusers'
ALTER TABLE `tblonappusers` ADD `custom_billing_plan_id` INT(11) DEFAULT 0 AFTER `billing_type`;

-- Update syntax for table 'tblonappusers'
ALTER TABLE `tblonappusers` ADD `custom_info` TEXT DEFAULT '' AFTER `custom_billing_plan_id`;

