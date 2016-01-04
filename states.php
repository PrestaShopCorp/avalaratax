<?php
/*
* 2007-2011 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once(dirname(__FILE__).'/../../config/config.inc.php');

$iso_code = Tools::getValue('country_iso_code');

$states = array();

$states[$iso_code] = Db::getInstance()->executeS(
    'SELECT *, s.iso_code AS state_iso_code, c.iso_code AS country_iso_code
    FROM `'._DB_PREFIX_.'state` s, `'._DB_PREFIX_.'country` c
    WHERE s.`id_country` = c.`id_country` AND c.`iso_code` = "'.pSQL($iso_code).'"'
);

if (sizeof($states[$iso_code])) {
    die(Tools::jsonEncode($states));
}

die('0');
