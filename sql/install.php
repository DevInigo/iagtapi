<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'order_payment_links` (
    `id_payment_link` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order` INT(11) NOT NULL,
    `reference` VARCHAR(255) NOT NULL,
    `payment_link` VARCHAR(255) NOT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_payment_link`),
    INDEX `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

if (!Db::getInstance()->execute($sql)) {
    return false;
}

return true;