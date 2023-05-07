ALTER TABLE `profiles` 
    ADD `search_appear` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `client_name`; 