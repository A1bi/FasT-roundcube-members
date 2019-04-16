ALTER TABLE `contactgroupmembers` DROP FOREIGN KEY `contact_id_fk_contacts`;

ALTER TABLE `contactgroupmembers` CHANGE `contact_id` `contact_id` VARCHAR(255) NOT NULL COMMENT '';
