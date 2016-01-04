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

class AddressController extends AddressControllerCore
{
	public function preProcess()
	{
		if (version_compare(_PS_VERSION_, '1.5', '<') && (Tools::isSubmit('submitAddress') || Tools::isSubmit('submitAccount')))
		{
			include_once(dirname(__FILE__).'/../../modules/avalaratax/avalaratax.php');
			$avalaraModule = new AvalaraTax();
			$result = $avalaraModule->fixPOST();
			if (isset($result['ResultCode']) && $result['ResultCode'] == 'Error')
			{
				if (isset($result['Messages']['Summary']))
					foreach ($result['Messages']['Summary'] as $error)
						$this->errors[] = Tools::safeOutput($error);
				else
					$this->errors[] = Tools::displayError('This address cannot be submitted');
				return false;
			}
		}
		parent::preProcess();
	}

	public function processSubmitAddress()
	{
		include_once(_PS_MODULE_DIR_.'avalaratax/avalaratax.php');
		$avalara_module = new AvalaraTax();
		if ($avalara_module->active)
		{
			$result = $avalara_module->fixPOST();
			if (isset($result['ResultCode']) && $result['ResultCode'] == 'Error')
			{
				if (isset($result['Messages']['Summary']))
					foreach ($result['Messages']['Summary'] as $error)
						$this->errors[] = Tools::safeOutput($error);
				else
					$this->errors[] = Tools::displayError('This address cannot be submitted');
				return false;
			}
		}

		parent::processSubmitAddress();
	}
}
