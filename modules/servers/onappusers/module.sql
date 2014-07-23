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