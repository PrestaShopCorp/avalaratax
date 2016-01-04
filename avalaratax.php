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

// Security
if (!defined('_PS_VERSION_'))
  exit;

spl_autoload_register('avalaraAutoload');

class AvalaraTax extends Module
{
  /**
   * @brief Constructor
   */
  public function __construct()
  {
    $this->name = 'avalaratax';
    $this->tab = 'billing_invoicing';
    $this->version = '3.5.6';
    $this->author = 'PrestaShop';
    parent::__construct();

    $this->tax_manager_class = "AvalaraTaxManager";

    $this->displayName = $this->l('Avalara - AvaTax');
    $this->description = $this->l('Sales Tax is complicated. AvaTax makes it easy.');

    /** Backward compatibility */
    require(_PS_MODULE_DIR_.$this->name.'/backward_compatibility/backward.php');

    if (!extension_loaded('soap') || !class_exists('SoapClient'))
      $this->warning = $this->l('SOAP extension should be enabled on your server to use this module.');
  }

  /**
   * @brief Installation method
   */
  public function install()
  {
    Configuration::updateValue('AVALARATAX_URL', 'https://avatax.avalara.net');
    Configuration::updateValue('AVALARATAX_ADDRESS_VALIDATION', 1);
    Configuration::updateValue('AVALARATAX_TAX_CALCULATION', 1);
    Configuration::updateValue('AVALARATAX_TIMEOUT', 300);

    // Value possible : Development / Production
    Configuration::updateValue('AVALARATAX_MODE', 'Production');
    Configuration::updateValue('AVALARATAX_ADDRESS_NORMALIZATION', 1);
    Configuration::updateValue('AVALARATAX_COMMIT_ID', (int)Configuration::get('PS_OS_DELIVERED'));
    Configuration::updateValue('AVALARATAX_CANCEL_ID', (int)Configuration::get('PS_OS_CANCELED'));
    Configuration::updateValue('AVALARATAX_REFUND_ID', (int)Configuration::get('PS_OS_REFUND'));
    Configuration::updateValue('AVALARATAX_POST_ID', (int)Configuration::get('PS_OS_PAYMENT'));
    Configuration::updateValue('AVALARATAX_STATE', 1);
    Configuration::updateValue('PS_TAX_DISPLAY', 1);
    Configuration::updateValue('AVALARATAX_COUNTRY', 0);
    Configuration::updateValue('AVALARA_CACHE_MAX_LIMIT', 3600); /* The values in cache will be refreshed every 1 minute by default */

    // Make sure Avalara Tables don't exist before installation
    Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'avalara_product_cache`');
    Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'avalara_carrier_cache`');
    Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'avalara_address_validation_cache`');
    Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'avalara_returned_products`');
    Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'avalara_temp`');
    Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'avalara_cart_cache`');

    if (!Db::getInstance()->Execute('
    CREATE TABLE `'._DB_PREFIX_.'avalara_product_cache` (
    `id_cache` int(10) unsigned NOT NULL auto_increment,
    `id_product` int(10) unsigned NOT NULL,
    `tax_rate` float(8, 2) unsigned NOT NULL,
    `region` varchar(2) NOT NULL,
    `id_address` int(10) unsigned NOT NULL,
    `update_date` datetime,
    PRIMARY KEY (`id_cache`),
    UNIQUE (`id_product`, `region`),
    KEY `id_product2` (`id_product`,`region`,`id_address`))
    ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8') ||
      !Db::getInstance()->Execute('
    CREATE TABLE `'._DB_PREFIX_.'avalara_carrier_cache` (
    `id_cache` int(10) unsigned NOT NULL auto_increment,
    `id_carrier` int(10) unsigned NOT NULL,
    `tax_rate` float(8, 2) unsigned NOT NULL,
    `region` varchar(2) NOT NULL,
    `amount` float(8, 2) unsigned NOT NULL,
    `update_date` datetime,
    `id_cart` int(10) unsigned NOT NULL,
    `cart_hash` varchar(32) DEFAULT NULL,
    PRIMARY KEY (`id_cache`),
    KEY `cart_hash` (`cart_hash`),
    KEY `cart_idx` (`id_cart`, `id_carrier`, `region`))
    ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8') ||
      !Db::getInstance()->Execute('
    CREATE TABLE `'._DB_PREFIX_.'avalara_address_validation_cache` (
    `id_avalara_address_validation_cache` int(10) unsigned NOT NULL auto_increment,
    `id_address` int(10) unsigned NOT NULL,
    `date_add` datetime,
    PRIMARY KEY (`id_avalara_address_validation_cache`),
    UNIQUE (`id_address`))
    ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8') ||
      !Db::getInstance()->Execute('
    CREATE TABLE `'._DB_PREFIX_.'avalara_returned_products` (
    `id_returned_product` int(10) unsigned NOT NULL auto_increment,
    `id_order` int(10) unsigned NOT NULL,
    `id_product` int(10) unsigned NOT NULL,
    `total` float(8, 2) unsigned NOT NULL,
    `quantity` int(10) unsigned NOT NULL,
    `name` varchar(255) NOT NULL,
    `description_short` varchar(255) NULL,
    `tax_code` varchar(255) NULL,
    PRIMARY KEY (`id_returned_product`))
    ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8') ||
      !Db::getInstance()->Execute('
    CREATE TABLE `'._DB_PREFIX_.'avalara_temp` (
    `id_order` int(10) unsigned NOT NULL,
    `id_order_detail` int(10) unsigned NOT NULL)
    ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8') ||
      !Db::getInstance()->Execute('
    CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'avalara_taxcodes` (
    `id_taxcode` int(10) unsigned NOT NULL auto_increment,
    `id_product` int(10) unsigned NOT NULL,
    `tax_code`  varchar(30) NOT NULL,
    `taxable` int(2) unsigned  NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_taxcode`),
    UNIQUE (`id_product`))
    ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8') ||
      !Db::getInstance()->Execute('
    CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'avalara_cart_cache` (
    `cart_id` int(10) unsigned NOT NULL,
    `cart_hash` varchar(32) NOT NULL,
    `total_tax` float(8,2) unsigned NOT NULL,
    `total_products_tax` float(8,2) unsigned NOT NULL,
    `total_shipping_tax` float(8,2) unsigned NOT NULL,
    PRIMARY KEY (`cart_hash`),
    KEY `cart_hash` (`cart_hash`),
    KEY `cart_id` (`cart_id`))'))
      return false;

    if (!parent::install() ||
      !$this->registerHook('updateOrderStatus') ||
      !$this->registerHook('cancelProduct') ||                        // Old PS 1.4 hook
      !$this->registerHook('actionProductCancel') ||                  // New PS 1.5 > hook
      !$this->registerHook('adminOrder') ||
      !$this->registerHook('backOfficeTop') ||
      !$this->registerHook('header') ||
      !$this->registerHook('actionCartSave') ||
      !$this->registerHook('actionOrderDetail') ||
      !$this->registerHook('actionCartGetOrderTotal') ||
      !$this->registerHook('actionCartGetPackageShippingCost') ||
      !$this->overrideFiles())
      return false;
    return true;
  }

  public function uninstall()
  {
    if (!$this->removeOverrideFiles() || !parent::uninstall() ||
      !Configuration::deleteByName('AVALARATAX_URL') ||
      !Configuration::deleteByName('AVALARATAX_ADDRESS_VALIDATION') ||
      !Configuration::deleteByName('AVALARATAX_TAX_CALCULATION') ||
      !Configuration::deleteByName('AVALARATAX_TIMEOUT') ||
      !Configuration::deleteByName('AVALARATAX_MODE') ||
      !Configuration::deleteByName('AVALARATAX_ACCOUNT_NUMBER') ||
      !Configuration::deleteByName('AVALARATAX_COMPANY_CODE') ||
      !Configuration::deleteByName('AVALARATAX_LICENSE_KEY') ||
      !Configuration::deleteByName('AVALARATAX_ADDRESS_NORMALIZATION') ||
      !Configuration::deleteByName('AVALARATAX_ADDRESS_LINE1') ||
      !Configuration::deleteByName('AVALARATAX_ADDRESS_LINE2') ||
      !Configuration::deleteByName('AVALARATAX_CITY') ||
      !Configuration::deleteByName('AVALARATAX_STATE') ||
      !Configuration::deleteByName('AVALARATAX_ZIP_CODE') ||
      !Configuration::deleteByName('AVALARATAX_COUNTRY') ||
      !Configuration::deleteByName('AVALARATAX_COMMIT_ID') ||
      !Configuration::deleteByName('AVALARATAX_CANCEL_ID') ||
      !Configuration::deleteByName('AVALARATAX_REFUND_ID') ||
      !Configuration::deleteByName('AVALARA_CACHE_MAX_LIMIT') ||
      !Configuration::deleteByName('AVALARATAX_POST_ID') ||
      !Configuration::deleteByName('AVALARATAX_CONFIGURATION_OK') ||
      !Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'avalara_product_cache`') ||
      !Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'avalara_carrier_cache`') ||
      !Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'avalara_address_validation_cache`') ||
      !Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'avalara_returned_products`') ||
      !Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'avalara_temp`') ||
      !Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'avalara_cart_cache`'))
      return false;
    // Do not remove taxcode table
    return true;
  }

  /**
   * @brief Describe the override schema
   */
  protected static function getOverrideInfo()
  {
    return array(
      'Cart.php' => array(
        'source' => 'override/classes/Cart.php',
        'dest' => 'override/classes/Cart.php',
        'md5' => array(
          '3.0.2' => 'e4b05425b6dc61f75aad434265f3cac8',
          '3.0.3' => 'f7388cb50fbfd300c9f81cc407b7be83',
        )
      ),
      'AddressController.php' => array(
        'source' => 'override/controllers/front/AddressController.php',
        'dest' => 'override/controllers/AddressController.php',
        'md5' => array(
          '1.1' => 'ebc4f31298395c4b113c7e2d7cc41b4a',
          '3.0.2' => 'ff3d9cb2956c35f4229d5277cb2e92e6',
          '3.2.1' => 'bc34c1150f7170d3ec7912eb383cd04b',
        )
      ),
      'AuthController.php' => array(
        'source' => 'override/controllers/front/AuthController.php',
        'dest' => 'override/controllers/AuthController.php',
        'md5' => array(
          '1.1' => '7304d7af971b30f2dcd401b80bbdf805',
          '3.0.2' => '3eb86260a7c8d6cfa1d209fb3e8f8bd6',
        )
      ),
    );
  }

  protected function removeOverrideFiles()
  {
    /** In v1.5, we do not remove override files */
    if (version_compare(_PS_VERSION_, '1.5', '<'))
      foreach (self::getOverrideInfo() as $key => $params)
      {
        if (!file_exists(_PS_ROOT_DIR_.'/'.$params['dest']))
          continue;

        $md5 = md5_file(_PS_ROOT_DIR_.'/'.$params['dest']);
        $removed = false;
        foreach ($params['md5'] as $hash)
          if ($md5 == $hash)
          {
            if (unlink(_PS_ROOT_DIR_.'/'.$params['dest']))
              $removed = true;
            break;
          }
        if (!$removed)
          $this->_errors[] = $this->l('Error while removing override: ').$key;
      }

    return !Tools::getIsset($this->_errors) || !$this->_errors || !count($this->_errors);
  }

  protected function overrideFiles()
  {
    /** In v1.5, we do not copy the override files */
    if (version_compare(_PS_VERSION_, '1.5', '<') && $this->removeOverrideFiles())
    {
      /** Check if the override directories exists */
      if (!is_dir(_PS_ROOT_DIR_.'/override/classes/'))
        mkdir(_PS_ROOT_DIR_.'/override/classes/', 0777, true);
      if (!is_dir(_PS_ROOT_DIR_.'/override/controllers/'))
        mkdir(_PS_ROOT_DIR_.'/override/controllers/', 0777, true);

      foreach (self::getOverrideInfo() as $key => $params)
        if (file_exists(_PS_ROOT_DIR_.'/'.$params['dest']))
          $this->_errors[] = $this->l('This override file already exists, please merge it manually: ').$key;
        elseif (!copy(_PS_MODULE_DIR_.'avalaratax/'.$params['source'], _PS_ROOT_DIR_.'/'.$params['dest']))
            $this->_erroors[] = $this->l('Error while copying the override file: ').$key;
    }
    return !Tools::getIsset($this->_errors) || !$this->_errors || !count($this->_errors);
  }


  //*** OrderDetailController.php ************************************

  // Hook called after generating OrderDetail
  // $params['carrier'] => $carrier
  // $params['order'] => $order
  public function hookActionOrderDetail($params)
  {
    // We need to override smarty here to force displaying tax information
    // Other pages rely on value of 'use_taxes' but this one expects 'use_tax'
    // 'show_taxes' is required on other pages but not this one
    // the value of 'group_use_tax' should be left alone
    $this->context->smarty->assign(array('use_tax' => 1));
  }

  //*** End OrderDetailController.php ********************************


  //*** AdminOrderController.php *************************************

  // Hook is called when a product is cancelled from an Order
  // This hook gets called once for each product that has been cancelled
  // $params['order'] => Order object
  // $params['id_order_detail']
  public function hookActionProductCancel($params)
  {
    if (Tools::getIsset($_POST['cancelProduct']))
    {
      $order = new Order((int)$_POST['id_order']);
      if (!Validate::isLoadedObject($order))
        return false;
      if ($order->invoice_number)
      {
        // Get all the cancel product's IDs
        $cancelledIdsOrderDetail = array();
        foreach ($_POST['cancelQuantity'] as $idOrderDetail => $qty)
          if ($qty > 0)
            $cancelledIdsOrderDetail[] = (int)$idOrderDetail;
        $cancelledIdsOrderDetail = implode(', ', $cancelledIdsOrderDetail);

        // Fill temp table
        Db::getInstance()->Execute('INSERT INTO '._DB_PREFIX_.'avalara_temp (`id_order`, `id_order_detail`)
                    VALUES ('.(int)$_POST['id_order'].', '.(int)$params['id_order_detail'].')');
        // Check if we are at the end of the loop
        $totalLoop = Db::getInstance()->ExecuteS('SELECT COUNT(`id_order`) as totalLines
                            FROM `'._DB_PREFIX_.'avalara_temp`
                            WHERE `id_order_detail` IN ('.pSQL($cancelledIdsOrderDetail).')');

        // We haven't reached the end of the returned products loop, exit this call of the hook early
        if ($totalLoop[0]['totalLines'] != count(array_filter($_POST['cancelQuantity'])))
          return false;

        // We should have reached the last call of this hook (all returned products are in our temp table)
        // Clean the temp table because we are at the end of the loop
        $this->purgeTempTable();

        // Get details for cancelledIdsOrderDetail (Grab the info to post to Avalara in English.)
        $cancelledProdIdsDetails = Db::getInstance()->ExecuteS('SELECT od.`product_id` as id_product, od.`id_order_detail`, pl.`name`,
                                    pl.`description_short`, od.`product_price` as price, od.`reduction_percent`,
                                    od.`reduction_amount`, od.`product_quantity` as quantity, atc.`tax_code`
                                    FROM '._DB_PREFIX_.'order_detail od
                                    LEFT JOIN '._DB_PREFIX_.'product p ON (p.id_product = od.product_id)
                                    LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (pl.id_product = p.id_product)
                                    LEFT JOIN '._DB_PREFIX_.'avalara_taxcodes atc ON (atc.id_product = p.id_product)
                                    WHERE pl.`id_lang` = '.(int)Configuration::get('PS_LANG_DEFAULT').' AND od.`id_order` = '.(int)$_POST['id_order'].'
                                    AND od.`id_order_detail` IN ('.pSQL($cancelledIdsOrderDetail).')');
        // Build the product list
        $products = array();
        foreach ($cancelledProdIdsDetails as $cancelProd)
          $products[] = array('id_product' => (int)$cancelProd['id_product'],
                        'quantity' => (int)$_POST['cancelQuantity'][$cancelProd['id_order_detail']],
                        'total' => pSQL($_POST['cancelQuantity'][$cancelProd['id_order_detail']] * ($cancelProd['price'] - ($cancelProd['price'] * ($cancelProd['reduction_percent'] / 100)) - $cancelProd['reduction_amount'])), // Including those product with discounts
                        'name' => pSQL(Tools::safeOutput($cancelProd['name'])),
                        'description_short' => pSQL(Tools::safeOutput($cancelProd['description_short']), true),
                        'tax_code' => pSQL(Tools::safeOutput($cancelProd['tax_code'])));
        // Send to Avalara
        $commitResult = $this->getTax($products, array('type' => 'ReturnInvoice', 'DocCode' => (int)$_POST['id_order']));

        if ($commitResult['ResultCode'] == 'Warning' || $commitResult['ResultCode'] == 'Error' || $commitResult['ResultCode'] == 'Exception')
          echo $this->_displayConfirmation($this->l('The following error was generated while cancelling the orders you selected. <br /> - '.
              Tools::safeOutput($commitResult['Messages']['Summary'])), 'error');
        else
        {
          // This seems to be causing returns to improperly adjust the commit date of the orignal transaction
          // $this->commitToAvalara(array('id_order' => (int)$_POST['id_order']));

          echo $this->_displayConfirmation($this->l('The products you selected were cancelled.'));
        }
      }
    }
  }

  //*** End AdminOrderController.php *********************************

  // Cart tax methods
  public function getSimplifiedCart($cart)
  {
    $products = $cart->getProducts();

    $s_cart = array(); // Create a simplified cart array with only the information we need
    $s_products = array(); // Create a simplified prodcuts array with only the information we need

    // In each row of products array we are looking for product id, quantity and
    // a pretax total that includes discounts
    foreach($products as $prod){
      $s_products[] = array('id_product' => (int)$prod['id_product'],
        'name' => $prod['name'],
        'description_short' => $prod['description_short'],
        'quantity' => (int)$prod['quantity'],
        'pretax_total' => (float)$prod['total'],
        'tax_code' => $this->getProductTaxCode((int)$prod['id_product'])
      );
    }

    $s_cart = array('cart_id' => $cart->id,
      'customer_id' => (int)$cart->id_customer,
      'customer_tax_address_id' => (int)$cart->id_address_delivery,
      'discounts' => $this->getOrderedCartRulesIds($cart),
      'carrier' => (int)$cart->id_carrier,
      'products' => $s_products);

    return $s_cart;
  }

  public function getTaxesForCartHash($cart_hash)
  {
    $result = Db::getInstance()->ExecuteS('
        SELECT acc.`total_tax`, acc.`total_products_tax`, acc.`total_shipping_tax`
        FROM `'._DB_PREFIX_.'avalara_cart_cache` acc
        WHERE acc.`cart_hash` = \''.$cart_hash.'\'');

    // If we have exactly 1 result from the datbase as we expected, assign tax values that we retrieved
    if (count($result) == 1 && !empty($result)) {
      $row = current($result);
      return array('total_tax' => $row['total_tax'],
        'total_products_tax' => $row['total_products_tax'],
        'total_shipping_tax' => $row['total_shipping_tax']);
    } else {
      return false;
    }
  }


  //*** Cart Hooks ***************************************************

  // Hook is called when attemtping to calculate an order total for a cart
  public function hookActionCartGetOrderTotal($params)
  {
    $new_order_total = 0.0;
    $with_taxes = $params['with_taxes'];
    $type = $params['type'];
    $cart = $params['cart'];
    $order_total = $params['order_total'];

    $total_tax = 0.00;
    $total_products_tax = 0.00;
    $total_shipping_tax = 0.00;

    // We only need to perform this section for OrderTotal's that ask for tax information
    if ($with_taxes == true && ($type==Cart::BOTH || Cart::ONLY_PRODUCTS)) {
      $s_cart = $this->getSimplifiedCart($cart);
      $cart_hash = md5(serialize($s_cart)); // Construct hash to act as a cache id

      // Search avalara_cart_cache table for tax values by cart hash primary key
      $tax_array = $this->getTaxesForCartHash($cart_hash);

      if (is_array($tax_array)) {
        $total_tax = $tax_array['total_tax'];
        $total_products_tax = $tax_array['total_products_tax'];
        $total_shipping_tax = $tax_array['total_shipping_tax'];
      }
    }

    if ($with_taxes==false) {
      $new_order_total = $order_total;
    } else {
      switch ($type) {
        case Cart::BOTH:
          // Shipping and shipping tax is already included in this number by hookActionCartGetPackageShippingCost
          $new_order_total = $order_total + $total_products_tax;
          break;
        case Cart::ONLY_PRODUCTS:
        case Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING:
          $new_order_total = $order_total + $total_products_tax;
          break;
        case Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING:
          $new_order_total = $order_total + $total_products_tax;
          break;
        case Cart::BOTH_WITHOUT_SHIPPING: // This type is automatically set if asking for Cart::BOTH and entire cart is virtual
          $new_order_total = $order_total + $total_products_tax;
          break;
        case Cart::ONLY_SHIPPING:
          // The getOrderTotal method should never call this hook when using this type instead
          // Cart::getPackageShippingCost will be returned instead before this hook is ever called
          $new_order_total = $order_total + $total_shipping_tax;
          break;
        case Cart::ONLY_DISCOUNTS:
          $new_order_total = $order_total;
          break;
        case Cart::ONLY_WRAPPING:
          $new_order_total = $order_total;
          break;
        default:
          $new_order_total = $order_total; // We could return, or throw an error here
      }
    }

    return array('new_order_total' => $new_order_total);
  }

  // Hook is called when calculating the total shipping cost for a package
  public function hookActionCartGetPackageShippingCost($params)
  {
    $new_shipping_cost = 0.0;
    $with_taxes = $params['with_taxes'];
    $cart = $params['cart'];
    $shipping_cost = $params['shipping_cost'];

    $total_shipping_tax = 0.00;

    // We only need to perform this section for OrderTotal's that ask for tax information
    if ($with_taxes == true) {
      $s_cart = $this->getSimplifiedCart($cart);
      $cart_hash = md5(serialize($s_cart)); // Construct hash to act as a cache id

      // Search avalara_cart_cache table for tax values by cart hash primary key
      $tax_array = $this->getTaxesForCartHash($cart_hash);

      if (is_array($tax_array))
        $total_shipping_tax = $tax_array['total_shipping_tax'];
    }

    if ($with_taxes==false) {
      $new_shipping_cost = $shipping_cost;
    } else {
      $new_shipping_cost = $shipping_cost + $total_shipping_tax;
    }

    return array('new_shipping_cost' => $new_shipping_cost);
  }

  // Occurs immediately after a cart is saved
  public function hookActionCartSave($args)
  {
    $cart = $args['cart'];
    if(!is_object($cart))
      return false;

    $products = $cart->getProducts();

    // No point in continuing if we do not have products in the cart
    if (empty($products))
      return false;

    // Obtain a simplified cart array and construct a hash to act as the primary key for the avalara_cart_cache table
    $s_cart = $this->getSimplifiedCart($cart);
    $cart_hash = md5(serialize($s_cart));

    // Verify that we have all the required information to uniquely identify a cart
    if (empty($s_cart['cart_id']) || empty($s_cart['customer_id']) || empty('customer_tax_address_id') || empty('carrier'))
      return false; // Skip everything below because we are missing a cart id, a customer id or a tax_address_id

    $s_products = $s_cart['products'];

    // Obtain pre-tax total discounts and total shipping values
    $total_discounts = $cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS);
    $total_shipping = $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);

    // Attempt to update our cache of taxes for this cart
    $this->updateTotalCartTaxes($cart, $s_cart, $cart_hash, $total_discounts, $total_shipping);
  }

  //
  public function updateTotalCartTaxes($cart, $s_cart, $cart_hash, $total_discounts, $total_shipping)
  {
    // Make sure we don't replace our cart taxes cache if we already have valid values for this cart
    if ($this->checkForValidCartCache($cart_hash) == true) {
      if(version_compare(_PS_VERSION_, '1.6.1', '>=')) {
        return true; // This caching optimization only works for Prestashop 1.6.1 and greater
      }
    }

    $total_products_tax = 0.00;
    $total_shipping_tax = 0.00;
    $total_tax = 0.00;

    // Rename keys of products array for compatibility with old getTax method
    $s_products = $s_cart['products'];

    foreach ($s_products as $k=>$v){
      $s_products[$k]['total'] = $s_products[$k]['pretax_total'];
      unset($s_products[$k]['pretax_total']);
    }

    // Obtain tax information from Avalara for this cart
    // Consider swtich on 'taxable' value based on config
    $tax_result = $this->getTax($s_products, array('type' => 'SalesOrder', 'DocCode' => 1, 'cart' => $cart, 'taxable' => true));

    $total_tax = (float)$tax_result['TotalTax'];

    $tax_lines = $tax_result['TaxLines'];

    $shipping_line = $tax_lines['Shipping'];
    unset($tax_lines['Shipping']);

    $total_shipping_tax = (float)$shipping_line['GetTax'];

    foreach ($tax_lines as $tax_line){
      $total_products_tax += (float)$tax_line['GetTax'];
    }

    Db::getInstance()->Execute('
      REPLACE INTO `'._DB_PREFIX_.'avalara_cart_cache` (`cart_id`, `cart_hash`, `total_tax`, `total_products_tax`, `total_shipping_tax`)
      VALUES ('.$s_cart['cart_id'].', \''.$cart_hash.'\', '.$total_tax.', '.$total_products_tax.', '.$total_shipping_tax.')'
        );

    return true;
  }

  // Return true if we have an unexpired cart cache for the given cart hash, false otherwise
  public function checkForValidCartCache($cart_hash)
  {
    $result = Db::getInstance()->getRow('
      SELECT `cart_hash`
      FROM `'._DB_PREFIX_.'avalara_cart_cache`
      WHERE `cart_hash` = \''.$cart_hash.'\'
    ');

    return !$result ? false : true;
  }

  // Method originally from Cart.php (1.6.x and greater), extracted out here for backwards compatibility with 1.5.x
  public function getOrderedCartRulesIds($cart, $filter = CartRule::FILTER_ACTION_ALL)
  {
    $cache_key = 'Cart::getCartRules_'.$cart->id.'-'.$filter.'-ids';
    if (!Cache::isStored($cache_key)) {
      $result = Db::getInstance()->executeS('
        SELECT cr.`id_cart_rule`
        FROM `'._DB_PREFIX_.'cart_cart_rule` cd
        LEFT JOIN `'._DB_PREFIX_.'cart_rule` cr ON cd.`id_cart_rule` = cr.`id_cart_rule`
        LEFT JOIN `'._DB_PREFIX_.'cart_rule_lang` crl ON (
          cd.`id_cart_rule` = crl.`id_cart_rule`
          AND crl.id_lang = '.(int)$cart->id_lang.'
        )
        WHERE `id_cart` = '.(int)$cart->id.'
        '.($filter == CartRule::FILTER_ACTION_SHIPPING ? 'AND free_shipping = 1' : '').'
        '.($filter == CartRule::FILTER_ACTION_GIFT ? 'AND gift_product != 0' : '').'
        '.($filter == CartRule::FILTER_ACTION_REDUCTION ? 'AND (reduction_percent != 0 OR reduction_amount != 0)' : '')
        .' ORDER BY cr.priority ASC'
        );

      Cache::store($cache_key, $result);
    } else {
      $result = Cache::retrieve($cache_key);
    }
  }

  //*** End Cart Hooks ***********************************************

  //******************************************************************
  //*** Hook Methods *************************************************
  //******************************************************************

  public function hookAdminOrder($params)
  {
    $this->purgeTempTable();
  }

  // This is the old 1.4 version, we should consider deprecating this in our next release
  public function hookCancelProduct($params)
  {
    // Make sure we don't execute this hook in versions greater than 1.4
    if (version_compare(_PS_VERSION_, '1.5', '>')) {
      return false;
    }

    if (Tools::getIsset($_POST['cancelProduct']))
    {
      $order = new Order((int)$_POST['id_order']);
      if (!Validate::isLoadedObject($order))
        return false;
      if ($order->invoice_number)
      {
        // Get all the cancel product's IDs
        $cancelledIdsOrderDetail = array();
        foreach ($_POST['cancelQuantity'] as $idOrderDetail => $qty)
          if ($qty > 0)
            $cancelledIdsOrderDetail[] = (int)$idOrderDetail;
        $cancelledIdsOrderDetail = implode(', ', $cancelledIdsOrderDetail);

        // Fill temp table
        Db::getInstance()->Execute('INSERT INTO '._DB_PREFIX_.'avalara_temp (`id_order`, `id_order_detail`)
                    VALUES ('.(int)$_POST['id_order'].', '.(int)$params['id_order_detail'].')');
        // Check if we are at the end of the loop
        $totalLoop = Db::getInstance()->ExecuteS('SELECT COUNT(`id_order`) as totalLines
                            FROM `'._DB_PREFIX_.'avalara_temp`
                            WHERE `id_order_detail` IN ('.pSQL($cancelledIdsOrderDetail).')');

        if ($totalLoop[0]['totalLines'] != count(array_filter($_POST['cancelQuantity'])))
          return false;

        // Clean the temp table because we are at the end of the loop
        $this->purgeTempTable();

        // Get details for cancelledIdsOrderDetail (Grab the info to post to Avalara in English.)
        $cancelledProdIdsDetails = Db::getInstance()->ExecuteS('SELECT od.`product_id` as id_product, od.`id_order_detail`, pl.`name`,
                                    pl.`description_short`, od.`product_price` as price, od.`reduction_percent`,
                                    od.`reduction_amount`, od.`product_quantity` as quantity, atc.`tax_code`
                                    FROM '._DB_PREFIX_.'order_detail od
                                    LEFT JOIN '._DB_PREFIX_.'product p ON (p.id_product = od.product_id)
                                    LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (pl.id_product = p.id_product)
                                    LEFT JOIN '._DB_PREFIX_.'avalara_taxcodes atc ON (atc.id_product = p.id_product)
                                    WHERE pl.`id_lang` = '.(int)Configuration::get('PS_LANG_DEFAULT').' AND od.`id_order` = '.(int)$_POST['id_order'].'
                                    AND od.`id_order_detail` IN ('.pSQL($cancelledIdsOrderDetail).')');
        // Build the product list
        $products = array();
        foreach ($cancelledProdIdsDetails as $cancelProd)
          $products[] = array('id_product' => (int)$cancelProd['id_product'],
                        'quantity' => (int)$_POST['cancelQuantity'][$cancelProd['id_order_detail']],
                        'total' => pSQL($_POST['cancelQuantity'][$cancelProd['id_order_detail']] * ($cancelProd['price'] - ($cancelProd['price'] * ($cancelProd['reduction_percent'] / 100)) - $cancelProd['reduction_amount'])), // Including those product with discounts
                        'name' => pSQL(Tools::safeOutput($cancelProd['name'])),
                        'description_short' => pSQL(Tools::safeOutput($cancelProd['description_short']), true),
                        'tax_code' => pSQL(Tools::safeOutput($cancelProd['tax_code'])));
        // Send to Avalara
        $commitResult = $this->getTax($products, array('type' => 'ReturnInvoice', 'DocCode' => (int)$_POST['id_order']));
        if ($commitResult['ResultCode'] == 'Warning' || $commitResult['ResultCode'] == 'Error' || $commitResult['ResultCode'] == 'Exception')
          echo $this->_displayConfirmation($this->l('The following error was generated while cancelling the orders you selected. <br /> - '.
              Tools::safeOutput($commitResult['Messages']['Summary'])), 'error');
        else
        {
          $this->commitToAvalara(array('id_order' => (int)$_POST['id_order']));
          echo $this->_displayConfirmation($this->l('The products you selected were cancelled.'));
        }
      }
    }
  }

  protected function getDestinationAddress($id_order)
  {
    $order = new Order((int)$id_order);
    if (!Validate::isLoadedObject($order))
      return false;

    $address = new Address((int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
    if (!Validate::isLoadedObject($address))
      return false;

    $state = null;
    if (!empty($address->id_state))
    {
      $state = new State((int)$address->id_state);
      if (!Validate::isLoadedObject($state))
        return false;
    }

    return array($address, $state, $order);
  }

  public function hookUpdateOrderStatus($params)
  {
    list($params['address'], $params['state'], $params['order']) = self::getDestinationAddress((int)$params['id_order']);

    if ($params['newOrderStatus']->id == (int)Configuration::get('AVALARATAX_COMMIT_ID'))
      return $this->commitToAvalara($params);
    elseif ($params['newOrderStatus']->id == (int)Configuration::get('AVALARATAX_CANCEL_ID'))
    {
      $params['CancelCode'] = 'V';
      $this->cancelFromAvalara($params);
      return $this->cancelFromAvalara($params);
    }
    elseif ($params['newOrderStatus']->id == (int)Configuration::get('AVALARATAX_POST_ID'))
      return $this->postToAvalara($params);
    elseif ($params['newOrderStatus']->id == (int)Configuration::get('AVALARATAX_REFUND_ID'))
      return $this->commitToAvalara($params);

    return false;
  }

  public function hookBackOfficeTop()
  {
    if (Tools::isSubmit('submitAddproduct') || Tools::isSubmit('submitAddproductAndStay'))
      Db::getInstance()->Execute('REPLACE INTO `'._DB_PREFIX_.'avalara_taxcodes` (`id_product`, `tax_code`)
        VALUES ('.(Tools::getIsset($_GET['id_product']) ? (int)$_GET['id_product'] : 0).', \''.pSQL(Tools::safeOutput($_POST['tax_code'])).'\')');


    if ((Tools::getIsset($_GET['updateproduct']) || Tools::getIsset($_GET['addproduct'])) && Tools::getIsset($_GET['id_product']) && (int)$_GET['id_product'])
    {
      $r = Db::getInstance()->getRow('
      SELECT `tax_code`
      FROM `'._DB_PREFIX_.'avalara_taxcodes` atc
      WHERE atc.`id_product` = '.(int)Tools::getValue('id_product'));

      if (version_compare(_PS_VERSION_, '1.5', '<')) /* v1.4.x an older */
      {
        return '
        <script type="text/javascript">
          $(function() {
            // Add the Tax Code field
            $(\'<tr><td class="col-left">'.$this->l('Tax Code (Avalara)').':</td><td style="padding-bottom:5px;"><input type="text" style="width: 130px; margin-right: 5px;" value="'.
              ($r ? Tools::safeOutput($r['tax_code']) : '').'" name="tax_code" maxlength="13" size="55"></td></tr>\').appendTo(\'#product #step1 table:eq(0) tbody\');

            // override original tax rules
            $(\'span #id_tax_rules_group\').parent().html(\'Avalara\');
          });
        </script>';
      }
      elseif (version_compare(_PS_VERSION_, '1.6', '<')) /* v1.5.x */
      {
        return '
        <script type="text/javascript">
          $(function() {
            var done = false;
            // Add the Tax Code field
            $(\'#link-Informations\').click(function() {
                    if (done == false) {
                            done = true;
                            $(\'<tr><td class="col-left"><label for="tax_code">'.$this->l('Tax Code:').'</label></td><td style="padding-bottom:5px;"><input type="text" style="width: 130px; margin-right: 5px;" value="'.
                            ($r ? Tools::safeOutput($r['tax_code']) : '').'" name="tax_code" maxlength="13" size="55"> <span class="small">(Avalara)</span></td></tr>\').appendTo(\'#step1 table:first tbody\');
                    }
            });

            // override original tax rules
            $(\'#link-Prices\').click(function() {
                    $(\'span #id_tax_rules_group\').parent().html(\'Avalara\');
            });
          });
        </script>';
      }
      else /* v1.6.x and newer */
      {
        return '
        <script type="text/javascript">
          $(function() {
            var done = false;
            // Add the Tax Code field
            //$(\'#link-Prices\').click(function() {
            //  $(\'#id_tax_rules_group\').parent().parent().parent().parent().html(\'<div class="form-group"><label class="control-label col-lg-3" for="tax_code"><span class="label-tooltip" data-toggle="tooltip" title="" data-original-title="'.$this->l('Tax rules will be handled by Avalara').'">'.$this->l('Tax Code (Avalara):').'</span></label><div class="input-group col-lg-4"><input type="text" value="'.($r ? Tools::safeOutput($r['tax_code']) : '').'" name="tax_code" maxlength="13" /><div class="alert alert-info" style="margin-top: 40px;">'.$this->l('Tax rules will be handled by Avalara').'</div></div>\');
            //});

            // Attempt # 2
            $(\'#product-tab-content-Prices\').on("displayed", function(){
              var $our_node = $(\'#id_tax_rules_group\').parent().parent().parent().parent();
              $our_node.hide();

              if(!$("#tax-code-input").length){
                $our_node.after(\'<div class="form-group"><label class="control-label col-lg-3" for="tax_code"><span class="label-tooltip" data-toggle="tooltip" title="" data-original-title="'.$this->l('Tax rules will be handled by Avalara').'">'.$this->l('Tax Code (Avalara):').'</span></label><div class="input-group col-lg-4"><input type="text" value="'.($r ? Tools::safeOutput($r['tax_code']) : '').'" name="tax_code" id="tax-code-input" maxlength="13" /><div class="alert alert-info" style="margin-top: 40px;">'.$this->l('Tax rules will be handled by Avalara').'</div></div>\');
              }
            //  console.log("test");
            //  $(\'#id_tax_rules_group\').parent().parent().parent().parent().html(\'<div class="form-group"><label class="control-label col-lg-3" for="tax_code"><span class="label-tooltip" data-toggle="tooltip" title="" data-original-title="'.$this->l('Tax rules will be handled by Avalara').'">'.$this->l('Tax Code (Avalara):').'</span></label><div class="input-group col-lg-4"><input type="text" value="'.($r ? Tools::safeOutput($r['tax_code']) : '').'" name="tax_code" maxlength="13" /><div class="alert alert-info" style="margin-top: 40px;">'.$this->l('Tax rules will be handled by Avalara').'</div></div>\');
            });
          });
        </script>';
      }
    }
    elseif ((Tools::isSubmit('updatecarrier') || Tools::isSubmit('addcarrier')) && Tools::getValue('id_carrier'))
      return '<script type="text/javascript">
      $(function() {
        // override original tax rules
        $(\'div #id_tax_rules_group\').parent().html(\'<label class="t">Avalara</label>\');
      });
    </script>';

    if (Tools::getValue('tab') == 'AdminTaxes' || Tools::getValue('tab') == 'AdminTaxRulesGroup' || Tools::strtolower(Tools::getValue('controller'))== 'admintaxes' || Tools::strtolower(Tools::getValue('controller')) == 'admintaxrulesgroup')
    {
      // JS for 1.6
      if (version_compare(_PS_VERSION_, '1.6', '>')){
        return '<script type="text/javascript">
          $(function() {
            $("#content #form-tax").hide();
            $("#content #form-tax_rules_group").hide();
            $("#desc-tax-new").hide();
            $(\'#content div:first\').append(\'<div class="warn alert alert-danger">'.$this->l('Tax rules are overwritten by Avalara Tax Module. Please make sure "Enable Tax" and "Use Eco Tax" are set to "No"').'</div>\');
          });
          </script>';
      }
      // JS for 1.5
      elseif (version_compare(_PS_VERSION_, '1.5', '>')){
        return '<script type="text/javascript">
        $(function() {
          $(\'#desc-tax-new\').hide();
          $("#content form").not("#tax_form").hide();
          $("#content #form-tax_rules_group").hide();
          $(\'#content div:first\').append(\'<div class="warn alert alert-danger">'.$this->l('Tax rules are overwritten by Avalara Tax Module. Please make sure "Enable Tax" and "Use Eco Tax" are set to "No"').'</div>\');
        });
        </script>';
      }else{
      // JS for 1.4
      return '<script type="text/javascript">
        $(function() {
        if ($(\'#Taxes\').size() || $(\'#submitFiltertax_rules_group\').size())
          $(\'#content\').prepend(\'<div class="warn"><img src="../img/admin/warn2.png">'.
      $this->l('Tax rules are overwritten by Avalara Tax Module.').'</div>\');
        });
        </script>';
      }
    }
    return '';
  }

  public function hookHeader()
  {
    // This module requires that PS tax calculation is turned off (in order to avoid conflicts)
    // Because of this, pages won't display tax values. Injecting these smarty values forces
    // all pages to display tax information
    $this->context->smarty->assign(array('use_taxes' => 1, 'show_taxes' => true));
  }

  /******************************************************************/
  /** Main Form Methods *********************************************/
  /******************************************************************/
  public function getContent()
  {
    $buffer = '';

    if (version_compare(_PS_VERSION_,'1.5','>'))
      $this->context->controller->addJQueryPlugin('fancybox');
    else
      $buffer .= '<script type="text/javascript" src="'.__PS_BASE_URI__.'js/jquery/jquery.fancybox-1.3.4.js"></script>
        <link type="text/css" rel="stylesheet" href="'.__PS_BASE_URI__.'css/jquery.fancybox-1.3.4.css" />';

    if (Tools::isSubmit('SubmitAvalaraTaxSettings'))
    {
      Configuration::updateValue('AVALARATAX_ACCOUNT_NUMBER', Tools::getValue('avalaratax_account_number'));
      Configuration::updateValue('AVALARATAX_LICENSE_KEY', Tools::getValue('avalaratax_license_key'));
      Configuration::updateValue('AVALARATAX_URL', Tools::getValue('avalaratax_url'));
      Configuration::updateValue('AVALARATAX_COMPANY_CODE', Tools::getValue('avalaratax_company_code'));

      $connectionTestResult = $this->_testConnection();
      if (strpos($connectionTestResult[0], 'Error') === false)
      {
        Configuration::updateValue('AVALARATAX_CONFIGURATION_OK', true);
        $buffer .= $this->_displayConfirmation();
      }
    }
    elseif (Tools::isSubmit('SubmitAvalaraTaxOptions'))
    {
      Configuration::updateValue('AVALARATAX_ADDRESS_VALIDATION', Tools::getValue('avalaratax_address_validation'));
      Configuration::updateValue('AVALARATAX_TAX_CALCULATION', Tools::getValue('avalaratax_tax_calculation'));
      Configuration::updateValue('AVALARATAX_TIMEOUT', (int)Tools::getValue('avalaratax_timeout'));
      Configuration::updateValue('AVALARATAX_ADDRESS_NORMALIZATION', Tools::getValue('avalaratax_address_normalization'));
      Configuration::updateValue('AVALARATAX_TAX_OUTSIDE', Tools::getValue('avalaratax_tax_outside'));
      Configuration::updateValue('AVALARA_CACHE_MAX_LIMIT', (int)Tools::getValue('avalara_cache_max_limit'));

      $buffer .= $this->_displayConfirmation();
    }
    elseif (Tools::isSubmit('SubmitAvalaraTestConnection'))
      $connectionTestResult = $this->_testConnection();
    elseif (Tools::isSubmit('SubmitAvalaraAddressOptions'))
    {
      /* Validate address*/
      $address = new Address();
      $address->address1 = Tools::getValue('avalaratax_address_line1');
      $address->address2 = Tools::getValue('avalaratax_address_line2');
      $address->city = Tools::getValue('avalaratax_city');
      $address->id_state = State::getIdByIso(Tools::getValue('avalaratax_state'));
      $address->id_country = Tools::getValue('avalaratax_country');
      $address->postcode = Tools::getValue('avalaratax_zip_code');

      $normalizedAddress = $this->validateAddress($address);
      if (Tools::getIsset($normalizedAddress['ResultCode']) && $normalizedAddress['ResultCode'] == 'Success')
      {
        $buffer .= $this->_displayConfirmation($this->l('The address you submitted has been validated.'));
        Configuration::updateValue('AVALARATAX_ADDRESS_LINE1', $normalizedAddress['Normalized']['Line1']);
        Configuration::updateValue('AVALARATAX_ADDRESS_LINE2', $normalizedAddress['Normalized']['Line2']);
        Configuration::updateValue('AVALARATAX_CITY', $normalizedAddress['Normalized']['City']);
        Configuration::updateValue('AVALARATAX_STATE', $normalizedAddress['Normalized']['Region']);
        Configuration::updateValue('AVALARATAX_COUNTRY', $normalizedAddress['Normalized']['Country']);
        Configuration::updateValue('AVALARATAX_ZIP_CODE', $normalizedAddress['Normalized']['PostalCode']);
      }
      else
      {
        $message = $this->l('The following error was generated while validating your address:');
        if (Tools::getIsset($normalizedAddress['Exception']['FaultString']))
          $message .= '<br /> - '.Tools::safeOutput($normalizedAddress['Exception']['FaultString']);
        if (Tools::getIsset($normalizedAddress['Messages']['Summary']))
          foreach ($normalizedAddress['Messages']['Summary'] as $summary)
            $message .= '<br /> - '.Tools::safeOutput($summary);
        $buffer .= $this->_displayConfirmation($message, 'error');

        Configuration::updateValue('AVALARATAX_ADDRESS_LINE1', Tools::getValue('avalaratax_address_line1'));
        Configuration::updateValue('AVALARATAX_ADDRESS_LINE2', Tools::getValue('avalaratax_address_line2'));
        Configuration::updateValue('AVALARATAX_CITY', Tools::getValue('avalaratax_city'));
        Configuration::updateValue('AVALARATAX_STATE', Tools::getValue('avalaratax_state'));
        Configuration::updateValue('AVALARATAX_ZIP_CODE', Tools::getValue('avalaratax_zip_code'));
      }
    }
    elseif (Tools::isSubmit('SubmitAvalaraTaxClearCache'))
    {
      Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'avalara_product_cache`');
      Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'avalara_carrier_cache`');

      $buffer .= $this->_displayConfirmation('Cache cleared!');
    }

    $confValues = Configuration::getMultiple(array(
                    // Configuration
                    'AVALARATAX_ACCOUNT_NUMBER', 'AVALARATAX_LICENSE_KEY', 'AVALARATAX_URL', 'AVALARATAX_COMPANY_CODE',
                    // Options
                    'AVALARATAX_ADDRESS_VALIDATION', 'AVALARATAX_TAX_CALCULATION', 'AVALARATAX_TIMEOUT',
                    'AVALARATAX_ADDRESS_NORMALIZATION', 'AVALARATAX_TAX_OUTSIDE', 'AVALARATAX_COMMIT_ID', 'AVALARATAX_CANCEL_ID',
                    'AVALARATAX_REFUND_ID', 'AVALARATAX_POST_ID', 'AVALARA_CACHE_MAX_LIMIT',
                    // Default Address
                    'AVALARATAX_ADDRESS_LINE1', 'AVALARATAX_ADDRESS_LINE2', 'AVALARATAX_CITY', 'AVALARATAX_STATE',
                    'AVALARATAX_ZIP_CODE', 'AVALARATAX_COUNTRY'));

    $stateList = array();
    $stateList[] = array('id' => '0', 'name' => $this->l('Choose your state (if applicable)'), 'iso_code' => '--');
    foreach (State::getStates((int)$this->context->cookie->id_lang) as $state)
      $stateList[] = array('id' => $state['id_state'], 'name' => $state['name'], 'iso_code' => $state['iso_code']);

    $countryList = array();
    $countryList[] = array('id' => '0', 'name' => $this->l('Choose your country'), 'iso_code' => '--');
    foreach (Country::getCountries((int)$this->context->cookie->id_lang, false, null, false) as $country)
      $countryList[] = array('id' => $country['id_country'], 'name' => $country['name'], 'iso_code' => $country['iso_code']);

    $buffer .= '<link href="'.$this->_path.'views/css/avalara.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">
      /* Fancybox */
      $(\'a.avalara-video-btn\').live(\'click\', function(){
          $.fancybox({
              \'type\' : \'iframe\',
              \'href\' : this.href.replace(new RegExp("watch\\?v=", "i"), \'embed\') + \'?rel=0&autoplay=1\',
              \'swf\': {\'allowfullscreen\':\'true\', \'wmode\':\'transparent\'},
              \'overlayShow\' : true,
              \'centerOnScroll\' : true,
              \'speedIn\' : 100,
              \'speedOut\' : 50,
              \'width\' : 853,
              \'height\' : 480
          });
          return false;
      });
    </script>
    <script type="text/javascript">
      $(document).ready(function(){
          var height1 = 0;
          var height = 0;
           $(\'.field-height1\').each(function(){
        if (height1 < $(this).height())
            height1 = $(this).height();
          });

          $(\'.field-height\').each(function(){
        if (height < $(this).height())
            height = $(this).height();
          });

        $(\'.field-height1\').css({\'height\' : $(\'.field-height1\').css(\'height\', height1+\'px\')});
          $(\'.field-height\').css({\'height\' : $(\'.field-height\').css(\'height\', height+\'px\')});

updateAvalaraTaxState($(\'#avalaratax_country\').val());

    $(\'#avalaratax_country\').change(function(){
updateAvalaraTaxState($(this).val());
    });


      });
function updateAvalaraTaxState(iso_code)
{

var default_state = "'.$confValues['AVALARATAX_STATE'].'";

$(\'#avalaratax_state\').html(\'\');
  $.ajax({
      type : \'GET\',
      url : \'../modules/avalaratax/states.php?country_iso_code=\'+iso_code,
      dataType: \'JSON\',
      success: function(data)
      {
    if (data != 0)
    {
        $.each(data[iso_code], function(i, item){
if (default_state == item.state_iso_code)
      $(\'#avalaratax_state\').append(\'<option  selected="selected" value="\'+item.state_iso_code+\'">\'+item.name+\'</option>\');
else
      $(\'#avalaratax_state\').append(\'<option  value="\'+item.state_iso_code+\'">\'+item.name+\'</option>\');
      $(\'#avalaratax_state\').show();
      $(\'#avalaratax_label_state\').show();
        });
    }
    else
    {
        $(\'#avalaratax_state\').hide();
        $(\'#avalaratax_label_state\').hide();
    }
      }
  });

}

    </script>
    <div class="avalara-wrap">
      <p class="avalara-intro"><a href="http://www.info.avalara.com/prestashop" class="avalara-logo" target="_blank"><img src="'.$this->_path.'views/img/avalara_logo.png" alt="Avalara" border="0" /></a><a href="http://www.info.avalara.com/prestashop" class="avalara-link" target="_blank">'.$this->l('Create an account').'</a>'.$this->l('Avalara and PrestaShop have partnered to provide the easiest way for you to accurately calculate and file sales tax.').'</p>
      <div class="clear"></div>
      <div class="avalara-content">
        <div class="avalara-video">
          <a href="http://www.youtube.com/embed/tm1tENVdcQ8" class="avalara-video-btn"><img src="'.$this->_path.'views/img/avalara-video-screen.jpg" alt="Avalara Video" /><img src="'.$this->_path.'views/img/btn-video.png" alt="" class="video-icon" /></a>
        </div>
        <h3>'.$this->l('Doing sales tax right is simple with Avalara.').'</h3>
        <p>'.$this->l('We do all of the research and automate the process for you, ensuring that the system is up-to-date with the most recent sales tax and VAT rates and rules in every state and country, so you don’t have to.  As a cloud-based service, AvaTax eliminates ongoing maintenance and support.  It provides you with a complete solution to manage your sales tax needs.').'</p>
        <img src="'.$this->_path.'views/img/avatax_badge.png" alt="AvaTax Certified" class="avatax-badge" />
        <ul>
          <li>'.$this->l('Address Validation included').'</li>
          <li>'.$this->l('Rooftop Accurate Calculations').'</li>
          <li>'.$this->l('Product and Service Taxability Rules').'</li>
          <li>'.$this->l('Exemption Certificate Management').'</li>
          <li>'.$this->l('Out-of-the-Box Sales Tax Reporting').'</li>
        </ul>
        <a href="http://www.info.avalara.com/prestashop" class="avalara-link" target="_blank">'.$this->l('Create an account').'</a>
        <p class="contact-avalara"><a href="http://www.info.avalara.com/prestashop" target="_blank">'.$this->l('Contact Avalara Today to Start Your Service').'</a></p>
      </div>
      <fieldset class="field-height1 right-fieldset">
      <legend><img src="'.$this->_path.'views/img/icon-console.gif" alt="" />'.$this->l('AvaTax Admin Console').'</legend>
        <p><a href="https://admin-avatax.avalara.net/" target="_blank">'.$this->l('Log-in to AvaTax Admin Console').'</a></p>
        <a href="https://admin-avatax.avalara.net/" target="_blank"><img src="'.$this->_path.'views/img/avatax-logo.png" alt="AvaTax" class="avatax-logo" /></a>
      </fieldset>
      <form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post" class="left-form">
        <fieldset class="field-height1">
          <legend><img src="'.$this->_path.'views/img/icon-config.gif" alt="" />'.$this->l('Configuration').'</legend>
          <h4>'.$this->l('AvaTax Credentials').'</h4>';
          if (Tools::getIsset($connectionTestResult))
            $buffer .= '<div id="test_connection" style="background: '.Tools::safeOutput($connectionTestResult[1]).';">'.$connectionTestResult[0].'</div>';

          $buffer .= '<label>'.$this->l('Account Number').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_account_number" value="'.(Tools::getIsset($confValues['AVALARATAX_ACCOUNT_NUMBER']) ? Tools::safeOutput($confValues['AVALARATAX_ACCOUNT_NUMBER']) : '').'" /> '.$this->l('Located in the top-right corner of your AvaTax Admin Console').'
          </div>
          <label>'.$this->l('License Key').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_license_key" value="'.(Tools::getIsset($confValues['AVALARATAX_LICENSE_KEY']) ? Tools::safeOutput($confValues['AVALARATAX_LICENSE_KEY']) : '').'" />
          </div>
          <label>'.$this->l('URL').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_url" value="'.(Tools::getIsset($confValues['AVALARATAX_URL']) ? Tools::safeOutput($confValues['AVALARATAX_URL']) : '').'" />
          </div>
          <label>'.$this->l('Company Code').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_company_code" value="'.(Tools::getIsset($confValues['AVALARATAX_COMPANY_CODE']) ? Tools::safeOutput($confValues['AVALARATAX_COMPANY_CODE']) : '').'" />
          </div>
          <div class="margin-form">
            <input type="submit" class="button" name="SubmitAvalaraTaxSettings" value="'.$this->l('Save Settings').'" /><img src="'.$this->_path.'views/img/icon-connection.gif" alt="" class="icon-connection" /><input type="submit" id="avalaratax_test_connection" class="button" name="SubmitAvalaraTestConnection" value="'.$this->l('Click here to Test Connection').'" />
          </div>
        </fieldset>
      </form>
      <form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post" class="form-half reset-label">
        <fieldset class="field-height MR7">
          <legend><img src="'.$this->_path.'views/img/icon-options.gif" alt="" />'.$this->l('Options').'</legend>
          <label>'.$this->l('Enable address validation').'</label>
          <div class="margin-form">
            <input type="checkbox" name="avalaratax_address_validation" value="1"'.(Tools::getIsset($confValues['AVALARATAX_ADDRESS_VALIDATION']) && $confValues['AVALARATAX_ADDRESS_VALIDATION'] ? ' checked="checked"' : '').' />
            ('.$this->l('Not compatible with One Page Checkout').')
          </div>
          <label>'.$this->l('Enable tax calculation').'</label>
          <div class="margin-form">
            <input type="checkbox" name="avalaratax_tax_calculation" value="1" '.(Tools::getIsset($confValues['AVALARATAX_TAX_CALCULATION']) && $confValues['AVALARATAX_TAX_CALCULATION'] ? ' checked="checked"' : '').' />
          </div>
          <label>'.$this->l('Enable address normalization in uppercase').'</label>
          <div class="margin-form">
            <input type="checkbox" name="avalaratax_address_normalization" value="1" '.(Tools::getIsset($confValues['AVALARATAX_ADDRESS_NORMALIZATION']) && $confValues['AVALARATAX_ADDRESS_NORMALIZATION'] ? ' checked="checked"' : '').' />
          </div>
          <label>'.$this->l('Enable tax calculation outside of your state').'</label>
          <div class="margin-form">
            <input type="checkbox" name="avalaratax_tax_outside" value="1" '.(Tools::getIsset($confValues['AVALARATAX_TAX_OUTSIDE']) && $confValues['AVALARATAX_TAX_OUTSIDE'] ? ' checked="checked"' : '').' />
          </div>
          <label>'.$this->l('Request timeout').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_timeout" value="'.(Tools::getIsset($confValues['AVALARATAX_TIMEOUT']) ? Tools::safeOutput($confValues['AVALARATAX_TIMEOUT']) : '').'" style="width: 40px;" /> '.$this->l('seconds').'
          </div>
          <label>'.$this->l('Refresh tax rate cache every x seconds (default 3600):').'</label>
          <div class="margin-form">
            <input type="text" name="avalara_cache_max_limit" value="'.(Tools::getIsset($confValues['AVALARA_CACHE_MAX_LIMIT']) ? (int)Tools::safeOutput($confValues['AVALARA_CACHE_MAX_LIMIT']) : '').'" style="width: 40px;" /> '.$this->l('seconds').'
          </div>
          <div class="margin-form">
            <input type="submit" class="button avalaratax_button" name="SubmitAvalaraTaxOptions" value="'.$this->l('Save Settings').'" />
            <input type="submit" class="button avalaratax_button" name="SubmitAvalaraTaxClearCache" value="'.$this->l('Clear Cache').'" style="display: none;"/>
          </div>
          <div class="sep"></div>
          <h4>'.$this->l('Default Post/Commit/Cancel/Refund Options').'</h4>
          <span class="avalara-info">'.$this->l('When an order\'s status is updated, the following options will be used to update Avalara\'s records.').'</span>';

      // Check if the order status exists
      $orderStatusList = array();
      foreach (Db::getInstance()->ExecuteS('SELECT `id_order_state`, `name` FROM `'._DB_PREFIX_.'order_state_lang` WHERE `id_lang` = '.(int)$this->context->cookie->id_lang) as $v)
        $orderStatusList[$v['id_order_state']] = Tools::safeOutput($v['name']);
      $buffer .= '<table class="avalara-table" cellspacing="0" cellpadding="0" width="100%">
            <th>'.$this->l('Action').'</th>
            <th>'.$this->l('Order status in your store').'</th>
            <tr>
              <td class="avalaratax_column">'.$this->l('Post order to Avalara').':</td>
              <td>'.(Tools::getIsset($orderStatusList[Configuration::get('AVALARATAX_POST_ID')]) ? html_entity_decode(Tools::safeOutput($orderStatusList[Configuration::get('AVALARATAX_POST_ID')])) :
                '<div style="color: red">'.$this->l('[ERROR] A default value was not found. Please, restore PrestaShop\'s default statuses.').'</div>').'
              </td>
            </tr>
            <tr>
              <td class="avalaratax_column">'.$this->l('Commit order to Avalara').':</td>
              <td>'.(Tools::getIsset($orderStatusList[Configuration::get('AVALARATAX_COMMIT_ID')]) ? html_entity_decode(Tools::safeOutput($orderStatusList[Configuration::get('AVALARATAX_COMMIT_ID')])) :
                '<div style="color: red">'.$this->l('[ERROR] A default value was not found. Please, restore PrestaShop\'s default statuses.').'</div>').'
              </td>
            </tr>
            <tr>
              <td class="avalaratax_column">'.$this->l('Delete order from Avalara').':</td>
              <td>'.(Tools::getIsset($orderStatusList[Configuration::get('AVALARATAX_CANCEL_ID')]) ? html_entity_decode(Tools::safeOutput($orderStatusList[Configuration::get('AVALARATAX_CANCEL_ID')])) :
                '<div style="color: red">'.$this->l('[ERROR] A default value was not found. Please, restore PrestaShop\'s default statuses.').'</div>').'
              </td>
            </tr>
            <tr>
              <td class="avalaratax_column last">'.$this->l('Void order in Avalara').':</td>
              <td class="last">'.(Tools::getIsset($orderStatusList[Configuration::get('AVALARATAX_REFUND_ID')]) ? html_entity_decode(Tools::safeOutput($orderStatusList[Configuration::get('AVALARATAX_REFUND_ID')])) :
                '<div style="color: red">'.$this->l('[ERROR] A default value was not found. Please, restore PrestaShop\'s default statuses.').'</div>').'
              </td>
            </tr>
          </table>
        </fieldset>
      </form>
      <form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post" class="form-half">
        <fieldset class="field-height ML7">
          <legend><img src="'.$this->_path.'views/img/icon-address.gif" alt="" />'.$this->l('Default Origin Address and Tax Information').'</legend>
          <label>'.$this->l('Address Line 1').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_address_line1" value="'.(Tools::getIsset($confValues['AVALARATAX_ADDRESS_LINE1']) ? Tools::safeOutput($confValues['AVALARATAX_ADDRESS_LINE1']) : '').'" />
          </div>
          <label>'.$this->l('Address Line 2').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_address_line2" value="'.(Tools::getIsset($confValues['AVALARATAX_ADDRESS_LINE2']) ? Tools::safeOutput($confValues['AVALARATAX_ADDRESS_LINE2']) : '').'" />
          </div>
          <label>'.$this->l('City').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_city" value="'.(Tools::getIsset($confValues['AVALARATAX_CITY']) ? Tools::safeOutput($confValues['AVALARATAX_CITY']) : '').'" />
          </div>
          <label>'.$this->l('Zip Code').'</label>
          <div class="margin-form">
            <input type="text" name="avalaratax_zip_code" value="'.(Tools::getIsset($confValues['AVALARATAX_ZIP_CODE']) ? Tools::safeOutput($confValues['AVALARATAX_ZIP_CODE']) : '').'" />
          </div>
          <label>'.$this->l('Country').'</label>
          <div class="margin-form">
            <select name="avalaratax_country" id="avalaratax_country">';
      foreach ($countryList as $country)
        $buffer .= '<option value="'.Tools::substr(Tools::strtoupper($country['iso_code']), 0, 2).'" '.($country['iso_code'] == $confValues['AVALARATAX_COUNTRY'] ? ' selected="selected"' : '').'>'.Tools::safeOutput($country['name']).'</option>';
      $buffer .= '</select>
          </div>
          <label id="avalaratax_label_state" >'.$this->l('State').'</label>
          <div class="margin-form">
            <select name="avalaratax_state" id="avalaratax_state">';
      foreach ($stateList as $state)
        $buffer .= '<option value="'.Tools::substr(Tools::strtoupper($state['iso_code']), 0, 2).'" '.($state['iso_code'] == $confValues['AVALARATAX_STATE'] ? ' selected="selected"' : '').'>'.Tools::safeOutput($state['name']).'</option>';

      $buffer .= '</select>
          </div>
          <div class="margin-form">
            <input type="submit" class="button" name="SubmitAvalaraAddressOptions" value="'.$this->l('Save Settings').'" />
          </div>
        </fieldset>
      </form>
      <div class="clear"></div>
    </div>';

    return $buffer;
  }

  /*
  ** Display a custom message for settings update
  ** $text string Text to be displayed in the message
  ** $type string (confirm|warn|error) Decides what color will the message have (green|yellow)
  */
  private function _displayConfirmation($text = '', $type = 'confirm')
  {
    if ($type == 'confirm')
      $img = 'ok.gif';
    elseif ($type == 'warn')
      $img = 'warn2.png';
    elseif ($type == 'error')
      $img = 'disabled.gif';
    else
      die('Invalid type.');

    return '<div class="conf '.Tools::safeOutput($type).'">
      <img src="../img/admin/'.$img.'" alt="" title="" />
      '.(empty($text) ? $this->l('Settings updated') : $text).
      '<img src="http://www.prestashop.com/modules/avalaratax.png?sid='.urlencode(Configuration::get('AVALARATAX_ACCOUNT_NUMBER')).'" style="float: right;" />
    </div>';
  }

  /**
   * @brief init the Avatax SDK
   */
  private function _connectToAvalara()
  {
    $timeout = Configuration::get('AVALARATAX_TIMEOUT');
    if ((int)$timeout > 0)
      ini_set('max_execution_time', (int)$timeout);

    include_once(dirname(__FILE__).'/sdk/AvaTax.php');

    /* Just instantiate the ATConfig class to init the settings (mandatory...) */
    new ATConfig(Configuration::get('AVALARATAX_MODE'),
      array('url' => Configuration::get('AVALARATAX_URL'),
            'account' => Configuration::get('AVALARATAX_ACCOUNT_NUMBER'),
            'license' => Configuration::get('AVALARATAX_LICENSE_KEY'),
            'client' => "Prestashop (" . _PS_VERSION_ . ") AvaTax Module (" . $this->version . ")",
            'trace' => false));
  }

  /**
   * @brief Connect to Avalara to make sure everything is OK
   */
  private function _testConnection()
  {
    $this->_connectToAvalara();
    try
    {
      $client = new TaxServiceSoap(Configuration::get('AVALARATAX_MODE'));
      $connectionTest = $client->ping();
      if ($connectionTest->getResultCode() == SeverityLevel::$Success)
      {
        try
        {
          $authorizedTest = $client->isAuthorized('GetTax');
          if ($authorizedTest->getResultCode() == SeverityLevel::$Success)
            $expirationDate = $authorizedTest->getexpires();
        }
        catch (SoapFault $exception)
        {
        }

        return array('<img src="../img/admin/ok.gif" alt="" /><strong style="color: green;">'.$this->l('Connection Test performed successfully.').'</strong><br /><br />'.$this->l('Ping version is:').' '.Tools::safeOutput($connectionTest->getVersion()).(Tools::getIsset($expirationDate) ? '<br /><br />'.$this->l('License Expiration Date:').' '.Tools::safeOutput($expirationDate) : ''), '#D6F5D6');
      }
    }
    catch (SoapFault $exception)
    {
      return array('<img src="../img/admin/forbbiden.gif" alt="" /><b style="color: #CC0000;">'.$this->l('Connection Test Failed.').'</b><br /><br />'.$this->l('Either the Account or License Key is incorrect. Please confirm the Account and License Key before testing the connection again.').'<br /><br /><strong style="color: #CC0000;">'.$this->l('Error(s):').' '.Tools::safeOutput($exception->faultstring).'</strong>', '#FFD8D8');
    }
  }

  /**
   * @brief Validates a given address
   */
  public function validateAddress(Address $address)
  {
    $this->_connectToAvalara();
    $client = new AddressServiceSoap(Configuration::get('AVALARATAX_MODE'));

    if (!empty($address->id_state))
      $state = new State((int)$address->id_state);
    if (!empty($address->id_country))
      $country = new Country((int)$address->id_country);

    $avalaraAddress = new AvalaraAddress($address->address1, $address->address2, null, $address->city,
                      (Tools::getIsset($state) ? $state->iso_code : null), $address->postcode, (Tools::getIsset($country) ? $country->iso_code : null), 0);

    $buffer = array();
    try
    {
      $request = new ValidateRequest($avalaraAddress, TextCase::$Upper, false);
      $result = $client->Validate($request);
      $addresses = $result->ValidAddresses;

      $buffer['ResultCode'] = Tools::safeOutput($result->getResultCode());
      if ($result->getResultCode() != SeverityLevel::$Success)
        foreach ($result->getMessages() as $msg)
        {
          $buffer['Messages']['Name'][] = Tools::safeOutput($msg->getName());
          $buffer['Messages']['Summary'][] = Tools::safeOutput($msg->getSummary());
        }
      else
        foreach ($result->getvalidAddresses() as $valid)
        {
          $buffer['Normalized']['Line1'] = Tools::safeOutput($valid->getline1());
          $buffer['Normalized']['Line2'] = Tools::safeOutput($valid->getline2());
          $buffer['Normalized']['City']= Tools::safeOutput($valid->getcity());
          $buffer['Normalized']['Region'] = Tools::safeOutput($valid->getregion());
          $buffer['Normalized']['PostalCode'] = Tools::safeOutput($valid->getpostalCode());
          $buffer['Normalized']['Country'] = Tools::safeOutput($valid->getcountry());
          $buffer['Normalized']['County'] = Tools::safeOutput($valid->getcounty());
          $buffer['Normalized']['FIPS'] = Tools::safeOutput($valid->getfipsCode());
          $buffer['Normalized']['PostNet'] = Tools::safeOutput($valid->getpostNet());
          $buffer['Normalized']['CarrierRoute'] = Tools::safeOutput($valid->getcarrierRoute());
          $buffer['Normalized']['AddressType'] = Tools::safeOutput($valid->getaddressType());
        }
    }
    catch (SoapFault $exception)
    {
      $buffer['Exception']['FaultString'] = Tools::safeOutput($exception->faultstring);
      $buffer['Exception']['LastRequest'] = Tools::safeOutput($client->__getLastRequest());
      $buffer['Exception']['LastResponse'] = Tools::safeOutput($client->__getLastResponse());
    }
    return $buffer;
  }

  /**
   * @brief Executes tax actions on documents
   *
   * @param Array $products Array of Product for which taxes need to be calculated
   * @param Array $params
   *    type : (default SalesOrder) SalesOrder|SalesInvoice|ReturnInvoice
   *    cart : (required for SalesOrder and SalesInvoice) Cart object
   *    DocCode : (required in ReturnInvoice, and when 'cart' is not set) Specify the Document Code
   *
   */
  public function getTax($products = array(), $params = array())
  {
    $confValues = Configuration::getMultiple(array('AVALARATAX_COMPANY_CODE', 'AVALARATAX_ADDRESS_LINE1',
                    'AVALARATAX_ADDRESS_LINE2', 'AVALARATAX_CITY', 'AVALARATAX_STATE', 'AVALARATAX_ZIP_CODE'));
    if (!Tools::getIsset($params['type']))
      $params['type'] = 'SalesOrder';
    $this->_connectToAvalara();

    $client = new TaxServiceSoap(Configuration::get('AVALARATAX_MODE'));
    $request = new GetTaxRequest();

    if (Tools::getIsset($this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}))
      $address = new Address((int)$this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
    elseif (Tools::getIsset($this->context->cookie) && Tools::getIsset($this->context->cookie->id_customer) && $this->context->cookie->id_customer)
      $address = new Address((int)Db::getInstance()->getValue('SELECT `id_address` FROM `'._DB_PREFIX_.'address`  WHERE `id_customer` = '.(int)$this->context->cookie->id_customer.' AND active = 1 AND deleted = 0'));

    if (Tools::getIsset($address))
    {
      if (!empty($address->id_state))
        $state = new State((int)$address->id_state);
      $addressDest = array();
      $addressDest['Line1'] = $address->address1;
      $addressDest['Line2'] = $address->address2;
      $addressDest['City'] = $address->city;
      $addressDest['Region'] = Tools::getIsset($state) ? $state->iso_code : '';
      $addressDest['PostalCode'] = $address->postcode;
      $addressDest['Country'] = Country::getIsoById($address->id_country);

      // Try to normalize the address depending on option in the BO
      if (Configuration::get('AVALARATAX_ADDRESS_NORMALIZATION'))
      {
        $last_update = Db::getInstance()->getValue('SELECT date_add FROM '._DB_PREFIX_.'avalara_address_validation_cache WHERE id_address = '.(int)$address->id);
        if (empty($last_update) || (strtotime($address->date_upd) > strtotime($last_update)))
        {
          $normalizedAddress = $this->validateAddress($address);
          Db::getInstance()->Execute('INSERT INTO '._DB_PREFIX_.'avalara_address_validation_cache (id_address, date_add) VALUES ('.(int)$address->id.', \''.pSQL(date('Y-m-d H:i:s')).'\') ON DUPLICATE KEY UPDATE date_add = \''.pSQL(date('Y-m-d H:i:s')).'\'');
        }
      }

      if (Tools::getIsset($normalizedAddress['Normalized']))
        $addressDest = $normalizedAddress['Normalized'];

      // Add Destination address (Customer address)
      $destination = new AvalaraAddress();
      $destination->setLine1($addressDest['Line1']);
      $destination->setLine2($addressDest['Line2']);
      $destination->setCity($addressDest['City']);
      $destination->setRegion($addressDest['Region']);
      $destination->setPostalCode($addressDest['PostalCode']);
      $destination->setCountry($addressDest['Country']);

      $request->setDestinationAddress($destination);
    }

    // Origin Address (Store Address or address setup in BO)
    $origin = new AvalaraAddress();
    $origin->setLine1(Tools::getIsset($confValues['AVALARATAX_ADDRESS_LINE1']) ? $confValues['AVALARATAX_ADDRESS_LINE1'] : '');
    $origin->setLine2(Tools::getIsset($confValues['AVALARATAX_ADDRESS_LINE2']) ? $confValues['AVALARATAX_ADDRESS_LINE2'] : '');
    $origin->setCity(Tools::getIsset($confValues['AVALARATAX_CITY']) ? $confValues['AVALARATAX_CITY'] : '');
    $origin->setRegion(Tools::getIsset($confValues['AVALARATAX_STATE']) ? $confValues['AVALARATAX_STATE'] : '');
    $origin->setPostalCode(Tools::getIsset($confValues['AVALARATAX_ZIP_CODE']) ? $confValues['AVALARATAX_ZIP_CODE'] : '');
    $request->setOriginAddress($origin);

    $request->setCompanyCode(Tools::getIsset($confValues['AVALARATAX_COMPANY_CODE']) ? $confValues['AVALARATAX_COMPANY_CODE'] : '');

    if (Tools::getIsset($address->vat_number) && !empty($address->vat_number) && $address->vat_number != 'undefined')
      $request->setBusinessIdentificationNo($address->vat_number);

    $orderId = Tools::getIsset($params['cart']) ? (int)$params['cart']->id : (int)$params['DocCode'];
    $nowTime = date('mdHis');

    // Type: Only supported types are SalesInvoice or SalesOrder
    if ($params['type'] == 'SalesOrder')            // SalesOrder: Occurs when customer adds product to the cart (generally to check how much the tax will be)
      $request->setDocType(DocumentType::$SalesOrder);
    elseif ($params['type']  == 'SalesInvoice')         // SalesInvoice: Occurs when customer places an order (It works like commitToAvalara()).
    {
      $request->setDocType(DocumentType::$SalesInvoice);
      $orderId = Db::getInstance()->getValue('SELECT `id_order` FROM '._DB_PREFIX_.'orders WHERE `id_cart` = '.(int)$params['cart']->id); // Make sure we got the orderId, even if it was/wasn't passed in $params['DocCode']
    }
    elseif ($params['type']  == 'ReturnInvoice')
    {
      $orderId = Tools::getIsset($params['type']) && $params['type'] == 'ReturnInvoice' ? $orderId.'.'.$nowTime : $orderId;
      $orderDate = Db::getInstance()->ExecuteS('
      SELECT `id_order`, `date_add`
      FROM `'._DB_PREFIX_.'orders`
      WHERE '.(Tools::getIsset($params['cart']) ? '`id_cart` = '.(int)$params['cart']->id : '`id_order` = '.(int)$params['DocCode']));

      $request->setDocType(DocumentType::$ReturnInvoice);
      $request->setCommit(true);
      $taxOverride = new TaxOverride();
      $taxOverride->setTaxOverrideType(TaxOverrideType::$TaxDate);
      $taxOverride->setTaxDate(date('Y-m-d', strtotime($orderDate[0]['date_add'])));
      $taxOverride->setReason('Refund');
      $request->setTaxOverride($taxOverride);
    }

    if (Tools::getIsset($this->context->cookie->id_customer))
      $customerCode = $this->context->cookie->id_customer;
    else
    {
      if (Tools::getIsset($params['DocCode']))
        $id_order = (int)$params['DocCode'];
      elseif (Tools::getIsset($_POST['id_order']))
        $id_order = (int)$_POST['id_order'];
      elseif (Tools::getIsset($params['id_order']))
        $id_order = (int)$params['id_order'];
      else
        $id_order = 0;

      $customerCode = (int)Db::getInstance()->getValue('SELECT `id_customer` FROM `'._DB_PREFIX_.'orders` WHERE `id_order` = '.(int)$id_order);
    }

    $request->setDocCode('Order '.Tools::safeOutput($orderId)); // Order Id - has to be float due to the . and more numbers for returns
    $request->setDocDate(date('Y-m-d'));          // date
    $request->setCustomerCode('CustomerID: '.(int)$customerCode); // string Required

    $free_shipping = false; // Used below to determine values to send to Avalara for shipping
    $shipping_total = (float)0.00;

    if (Tools::getIsset($params['cart']))
    {
      $discount_total = $params['cart']->getOrderTotal(false, Cart::ONLY_DISCOUNTS); // decimal
      $shipping_total = (float)$params['cart']->getOrderTotal(false, Cart::ONLY_SHIPPING);

      $cart_rules = $params['cart']->getCartRules(); // Fetch discount rules for processing

      // Check for a "free shipping" discount rule
      foreach ($cart_rules as &$cart_rule) {
        if ($cart_rule['free_shipping']) {
          $free_shipping = true;
          break;
        }
      }

      // If free shipping provided, remove the free shipping amount from the discount total
      // and set $shipping_total variable to zero
      if ($free_shipping){
        $discount_total -= $shipping_total;
        $shipping_total = (float)0.00;
      }

      $request->setDiscount($discount_total);
    }
    else
    {
      $request->setDiscount(0.00); // decimal
    }

    $request->setDetailLevel(DetailLevel::$Tax);      // Summary or Document or Line or Tax or Diagnostic

    // Add line
    $lines = array();
    $i = 0;
    foreach ($products as $product)
    {
      // Retrieve the tax_code for the current product if not defined
      if (Tools::getIsset($params['taxable']) && !$params['taxable'])
        $taxCode = 'NT';
      else
        $taxCode = !Tools::getIsset($product['tax_code']) ? $this->getProductTaxCode((int)$product['id_product']) : $product['tax_code'];

      if (Tools::getIsset($product['id_product']))
      {
        $line = new Line();
        $line->setNo($i++);     // string line Number of invoice ($i)
        $line->setItemCode((int)$product['id_product'].' - '.Tools::substr($product['name'], 0, 20));
        $line->setDescription(Tools::substr(Tools::safeOutput($product['name'].' - '.$product['description_short']), 0, 250));
        $line->setTaxCode($taxCode);
        $line->setQty(Tools::getIsset($product['quantity']) ? (float)$product['quantity'] : 1);
        $line->setAmount($params['type'] == 'ReturnInvoice' && (float)$product['total'] > 0 ? (float)$product['total'] * -1 : (float)$product['total']);
        $line->setDiscounted(true);

        $lines[] = $line;
      }
    }

    // Send shipping as new line
    if (Tools::getIsset($params['cart']))
    {
      $line = new Line();
      $line->setNo('Shipping'); // Shipping information has it's own special line number in AvaTax
      $line->setItemCode('Shipping');
      $line->setDescription('Shipping costs');
      $line->setTaxCode('FR020100'); // Default TaxCode for Shipping. Avalara will decide depending on the State if taxes should be charged or not
      $line->setQty(1);
      $line->setAmount($shipping_total);
      $line->setDiscounted(false); // Free shipping discounts will always set line item amount to 0, so no document level discounts should ever apply
      $lines[] = $line;
    }

    $request->setLines($lines);
    $buffer = array();
    try
    {
      $result = $client->getTax($request);
      $buffer['ResultCode'] = Tools::safeOutput($result->getResultCode());
      if ($result->getResultCode() == SeverityLevel::$Success)
      {
        $buffer['DocCode'] = Tools::safeOutput($request->getDocCode());
        $buffer['TotalAmount'] = Tools::safeOutput($result->getTotalAmount());
        $buffer['TotalDiscount'] = Tools::safeOutput($result->getTotalDiscount());
        $buffer['TotalExemption'] = Tools::safeOutput($result->getTotalExemption());
        $buffer['TotalTaxable'] = Tools::safeOutput($result->getTotalTaxable());
        $buffer['TotalTax'] = Tools::safeOutput($result->getTotalTax());
        $buffer['TotalTaxCalculated'] = Tools::safeOutput($result->getTotalTaxCalculated());
        $buffer['NowTime'] = $nowTime;
        foreach ($result->getTaxLines() as $ctl)
        {
          $buffer['TaxLines'][$ctl->getNo()]['GetTax'] = Tools::safeOutput($ctl->getTax());
          $buffer['TaxLines'][$ctl->getNo()]['TaxCode'] = Tools::safeOutput($ctl->getTaxCode());

          foreach ($ctl->getTaxDetails() as $ctd)
          {
            $buffer['TaxLines'][$ctl->getNo()]['TaxDetails']['JurisType'] = Tools::safeOutput($ctd->getJurisType());
            $buffer['TaxLines'][$ctl->getNo()]['TaxDetails']['JurisName'] = Tools::safeOutput($ctd->getJurisName());
            $buffer['TaxLines'][$ctl->getNo()]['TaxDetails']['Region'] = Tools::safeOutput($ctd->getRegion());
            $buffer['TaxLines'][$ctl->getNo()]['TaxDetails']['Rate'] = Tools::safeOutput($ctd->getRate());
            $buffer['TaxLines'][$ctl->getNo()]['TaxDetails']['Tax'] = Tools::safeOutput($ctd->getTax());
          }
        }
      }
      else
        foreach ($result->getMessages() as $msg)
        {
          $buffer['Messages']['Name'] = Tools::safeOutput($msg->getName());
          $buffer['Messages']['Summary'] = Tools::safeOutput($msg->getSummary());
        }
    }
    catch (SoapFault $exception)
    {
      $buffer['Exception']['FaultString'] = Tools::safeOutput($exception->faultstring);
      $buffer['Exception']['LastRequest'] = Tools::safeOutput($client->__getLastRequest());
      $buffer['Exception']['LastResponse'] = Tools::safeOutput($client->__getLastResponse());
    }
    return $buffer;
  }

  /*
  ** Make changes to an order, get order history or checks if the module is authorized
  **
  ** $type string commit|post|cancel|history Transaction type
  ** $params array Key=>Values depending on the transaction type
  **    DocCode: (required for ALL except for isAuthorized) Document unique identifier
  **    DocDate: (required for post) Date in which the transaction was made (today's date if post)
  **    IdCustomer: (required for post) Customer ID
  **    TotalAmount: (required for post) Order total amount in case of Post type
  **    TotalTax: (required for post) Total tax amount for current order
  **    CancelCode: (required for cancel only) D|P Sets the cancel code (D: Document Deleted | P: Post Failed)
  */
  public function tax($type, $params = array())
  {
    $this->_connectToAvalara();
    $client = new TaxServiceSoap(Configuration::get('AVALARATAX_MODE'));
    if ($type == 'commit')
      $request= new CommitTaxRequest();
    elseif ($type == 'post')
    {
      $request= new PostTaxRequest();
      $request->setDocDate($params['DocDate']);
      $request->setTotalAmount($params['TotalAmount']);
      $request->setTotalTax($params['TotalTax']);
    }
    elseif ($type == 'cancel')
    {
      $request= new CancelTaxRequest();
      if ($params['CancelCode'] == 'D')
        $code = CancelCode::$DocDeleted;
      elseif ($params['CancelCode'] == 'P')
        $code = CancelCode::$PostFailed;
      elseif ($params['CancelCode'] == 'V')
        $code = CancelCode::$DocVoided;
      else
        die('Invalid cancel code.');

      $request->setCancelCode($code);
    }
    elseif ($type == 'history')
    {
      $request= new GetTaxHistoryRequest();
      $request->setDetailLevel(DetailLevel::$Document);
    }

    if ($type != 'isAuthorized')
    {
      $request->setDocCode('Order '.(int)$params['DocCode']);
      $request->setDocType(DocumentType::$SalesInvoice);
      $request->setCompanyCode(Configuration::get('AVALARATAX_COMPANY_CODE'));
    }

    $buffer = array();
    try
    {
      if ($type == 'commit')
        $result = $client->commitTax($request);
      elseif ($type == 'post')
        $result = $client->postTax($request);
      elseif ($type == 'cancel')
        $result = $client->cancelTax($request);
      elseif ($type == 'isAuthorized')
        $result = $client->isAuthorized('GetTax');
      elseif ($type == 'history')
      {
        $result = $client->getTaxHistory($request);
        $buffer['Invoice'] = $result->getGetTaxRequest()->getDocCode();
        $buffer['Status'] = $result->getGetTaxResult()->getDocStatus();
      }

      $buffer['ResultCode'] = $result->getResultCode();

      if ($result->getResultCode() != SeverityLevel::$Success)
        foreach ($result->getMessages() as $msg)
        {
          $buffer['Messages']['Name'] = Tools::safeOutput($msg->getName());
          $buffer['Messages']['Summary'] = Tools::safeOutput($msg->getSummary());
        }
    }
    catch (SoapFault $exception)
    {
      $buffer['Exception']['FaultString'] = Tools::safeOutput($exception->faultstring);
      $buffer['Exception']['LastRequest'] = Tools::safeOutput($client->__getLastRequest());
      $buffer['Exception']['LastResponse'] = Tools::safeOutput($client->__getLastResponse());
    }
    return $buffer;
  }

  public function postToAvalara($params)
  {
    if (!Tools::getIsset($params['address']))
      list($params['address'], $params['state'], $params['order']) = self::getDestinationAddress((int)$params['id_order']);

    $destination = new AvalaraAddress();
    $destination->setLine1($params['address']->address1);
    $destination->setLine2($params['address']->address2);
    $destination->setCity($params['address']->city);
    $destination->setRegion(Tools::getIsset($params['state']) ? $params['state']->iso_code : '');
    $destination->setPostalCode($params['address']->postcode);

    $commitResult = $this->tax('history', array('DocCode' => (int)$params['id_order'], 'Destination' => $destination));
    if (Tools::getIsset($commitResult['ResultCode']) && $commitResult['ResultCode'] == 'Success')
    {
      $params['CancelCode'] = 'D';
      $this->cancelFromAvalara($params);
      $this->cancelFromAvalara($params); // Twice because first call only voids the order, and 2nd call deletes it
    }

    // Grab the info to post to Avalara in English.
    $order = new Order((Tools::getIsset($_POST['id_order']) ? (int)$_POST['id_order'] : (int)$params['id_order']));
    $allProducts = Db::getInstance()->ExecuteS('SELECT p.`id_product`, pl.`name`, pl.`description_short`,
                          od.`product_price` as price, od.`reduction_percent`,
                          od.`reduction_amount`, od.`product_quantity` as quantity, atc.`tax_code`
                          FROM `'._DB_PREFIX_.'order_detail` od
                          LEFT JOIN `'._DB_PREFIX_.'product` p ON (p.id_product = od.product_id)
                          LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (pl.id_product = p.id_product)
                          LEFT JOIN `'._DB_PREFIX_.'avalara_taxcodes` atc ON (atc.id_product = p.id_product)
                          WHERE pl.`id_lang` = '.(int)Configuration::get('PS_LANG_DEFAULT').' AND od.`id_order` = '.(Tools::getIsset($_POST['id_order']) ? (int)$_POST['id_order'] : (int)$params['id_order']));

    $products = array();
    foreach ($allProducts as $v)
      $products[] = array('id_product' => $v['id_product'],
                    'name' => $v['name'],
                    'description_short' => $v['description_short'],
                    'quantity' => $v['quantity'],
                    'total' => $v['quantity'] * ($v['price'] - ($v['price'] * ($v['reduction_percent'] / 100)) - ($v['reduction_amount'])), // Including those products with discounts
                    'tax_code' => $v['tax_code'],
                    'taxable' => (bool)$this->getProductTaxable((int)$v['id_product']));

    $taxable = true;
    //check if it is outside the state and if we are in united state and if conf AVALARATAX_TAX_OUTSIDE IS ENABLE
    if (Tools::getIsset($params['state']) && !Configuration::get('AVALARATAX_TAX_OUTSIDE') && $params['state']->iso_code != Configuration::get('AVALARATAX_STATE'))
      $taxable = false;

    $cart = new Cart((int)$order->id_cart);
    $getTaxResult = $this->getTax($products, array('type' => 'SalesInvoice', 'cart' => $cart, 'id_order' => Tools::getIsset($_POST['id_order']) ? (int)$_POST['id_order'] : (int)$params['id_order'], 'taxable' => $taxable));

    $commitResult = $this->tax('post', array('DocCode' => (Tools::getIsset($_POST['id_order']) ? (int)$_POST['id_order'] : (int)$params['id_order']),
                      'DocDate' => date('Y-m-d'), 'IdCustomer' => (int)$cart->id_customer,  'TotalAmount' => (float)$getTaxResult['TotalAmount'],
                      'TotalTax' => (float)$getTaxResult['TotalTax']));


    if (Tools::getIsset($commitResult['ResultCode']) && ($commitResult['ResultCode'] == 'Warning' || $commitResult['ResultCode'] == 'Error' || $commitResult['ResultCode'] == 'Exception'))
      return $this->_displayConfirmation($this->l('The following error was generated while cancelling the orders you selected.'.
          '<br /> - '.Tools::safeOutput($commitResult['Messages']['Summary'])), 'error');

    return $this->_displayConfirmation($this->l('The orders you selected were posted.'));
  }

  public function commitToAvalara($params)
  {
    // Create the order before commiting to Avalara
    $this->postToAvalara($params);
    $commitResult = $this->tax('history', array('DocCode' => $params['id_order']));
    if (Tools::getIsset($commitResult['ResultCode']) && $commitResult['ResultCode'] == 'Success')
    {
      $commitResult = $this->tax('commit', array('DocCode' => (int)$params['id_order']));
      if (Tools::getIsset($commitResult['Exception']) || Tools::getIsset($commitResult['ResultCode'])   && ($commitResult['ResultCode'] == 'Warning' || $commitResult['ResultCode'] == 'Error' || $commitResult['ResultCode'] == 'Exception'))
        return ($this->_displayConfirmation($this->l('The following error was generated while committing the orders you selected to Avalara.').
            (Tools::getIsset($commitResult['Messages']) ? '<br /> - '.Tools::safeOutput($commitResult['Messages']['Summary']) : '').
            (Tools::getIsset($commitResult['Exception']) ? '<br /> - '.Tools::safeOutput($commitResult['Exception']['FaultString']) : ''), 'error'));
      else
        return $this->_displayConfirmation($this->l('The orders you selected were committed.'));
    }

    // Orders prior Avalara module installation will trigger an "Invalid Status" error. For this reason, the user won't be alerted here.
  }

  public function cancelFromAvalara($params)
  {
    $commitResult = $this->tax('history', array('DocCode' => $params['id_order']));
    $hasRefund = Db::getInstance()->ExecuteS('SELECT COUNT(`id_order`) as qtyProductRefunded
                        FROM `'._DB_PREFIX_.'ps_order_detail`
                        WHERE `id_order` = '.(int)$params['id_order'].'
                        AND (`product_quantity_refunded` IS NOT NULL AND `product_quantity_refunded` > 0)');

    if (!($commitResult['Status'] == 'Committed' && (int)$hasRefund[0]['qtyProductRefunded'] > 0))
    {
      if (Tools::getIsset($commitResult['Status']) && $commitResult['Status'] == 'Temporary')
        $this->postToAvalara($params);
      $commitResult = $this->tax('cancel', array('DocCode' => (int)$params['id_order'],
                        'CancelCode' => Tools::getIsset($params['CancelCode']) ? $params['CancelCode'] : 'V' ));
      if (Tools::getIsset($commitResult['ResultCode'])
        && ( $commitResult['ResultCode'] == 'Warning'
          || $commitResult['ResultCode'] == 'Error'
          || $commitResult['ResultCode'] == 'Exception'))
        return $this->_displayConfirmation($this->l('The following error was generated while cancelling the orders you selected.').
          ' <br /> - '.Tools::safeOutput($commitResult['Messages']['Summary']), 'error');
      else
        return $this->_displayConfirmation($this->l('The orders you selected were cancelled.'));
    }
  }

  /*
  ** Fix $_POST to validate/normalize the address on address creation/update
  */
  public function fixPOST()
  {
    $address = new Address(Tools::getIsset($_POST['id_address']) ? (int)$_POST['id_address'] : null);

    /* Validate address only in the U.S. and Canada - if the Address Validation feature has been turned on in the module's configuration */
    if (($address->id_country == Country::getByIso('US') || $address->id_country == Country::getByIso('CA')) && $this->tax('isAuthorized') && Configuration::get('AVALARATAX_ADDRESS_VALIDATION'))
    {
      $address->address1 = Tools::getIsset($_POST['address1']) ? $_POST['address1'] : null;
      $address->address2 = Tools::getIsset($_POST['address2']) ? $_POST['address2'] : null;
      $address->city = Tools::getIsset($_POST['city']) ? $_POST['city'] : null;
      $address->region = Tools::getIsset($_POST['region']) ? $_POST['region'] : null;
      $address->postcode = Tools::getIsset($_POST['postcode']) ? $_POST['postcode'] : null;
      $address->id_country = Tools::getIsset($_POST['id_country']) ? $_POST['id_country'] : null;
      $address->id_state = Tools::getIsset($_POST['id_state']) ? (int)$_POST['id_state'] : null;

      $normalizedAddress = $this->validateAddress($address);
      if (Tools::getIsset($normalizedAddress['ResultCode']) && $normalizedAddress['ResultCode'] == 'Success')
      {
        Db::getInstance()->Execute('INSERT INTO '._DB_PREFIX_.'avalara_address_validation_cache (id_address, date_add) VALUES ('.(int)$address->id.', \''.pSQL(date('Y-m-d H:i:s')).'\') ON DUPLICATE KEY UPDATE date_add = \''.pSQL(date('Y-m-d H:i:s')).'\'');

        $_POST['address1'] = Tools::safeOutput($normalizedAddress['Normalized']['Line1']);
        $_POST['address2'] = Tools::safeOutput($normalizedAddress['Normalized']['Line2']);
        $_POST['city'] = Tools::safeOutput($normalizedAddress['Normalized']['City']);
        $_POST['postcode'] =  Tools::safeOutput(Tools::substr($normalizedAddress['Normalized']['PostalCode'], 0, strpos($normalizedAddress['Normalized']['PostalCode'], '-')));
      }
      return $normalizedAddress;
    }
  }

  // Method obtains the Avalara TaxCode value set on the Product Prices page
  public function getProductTaxCode($id_product)
  {
    $result = Db::getInstance()->getValue('
    SELECT `tax_code`
    FROM `'._DB_PREFIX_.'avalara_taxcodes` atc
    WHERE atc.`id_product` = '.(int)$id_product);

    //return $result ? Tools::safeOutput($result) : '0';
    return empty($result) ? '0' : Tools::safeOutput($result);
  }

  public function getProductTaxable($idProduct)
  {
    // !== and not != because it can fail if getProductTaxCode return an int.
    return $this->getProductTaxCode($idProduct) !== 'NT';
  }

  private function purgeTempTable()
  {
    return Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'avalara_temp`');
  }

  private function getCurrentURL($htmlEntities = false)
  {
    $url = Tools::safeOutput($_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'], true);
    return (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.($htmlEntities ? preg_replace('/&/', '&amp;', $url): $url);
  }
}

function avalaraAutoload($className)
{
  $className = str_replace(chr(0), '', $className);
  if (!preg_match('/^\w+$/', $className))
    die('Invalid classname.');

  $moduleDir = dirname(__FILE__).'/';

  if (file_exists($moduleDir.$className.'.php'))
    require_once($moduleDir.$className.'.php');
  elseif (file_exists($moduleDir.'sdk/classes/'.$className.'.class.php'))
    require_once($moduleDir.'sdk/classes/'.$className.'.class.php');
  elseif (file_exists($moduleDir.'sdk/classes/BatchSvc/'.$className.'.class.php'))
    require_once($moduleDir.'sdk/classes/BatchSvc/'.$className.'.class.php');
  elseif (function_exists('__autoload'))
    __autoload($className);
}
