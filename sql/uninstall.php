<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'order_payment_links`;';

if (!Db::getInstance()->execute($sql)) {
    return false;
}

return true;