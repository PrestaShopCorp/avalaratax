<?php
/*
* 2007-2016 PrestaShop
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
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class Cart extends CartCore
{
    public function getOrderTotal($with_taxes = true, $type = Cart::BOTH, $products = null, $id_carrier = null, $use_cache = true)
    {
        if (!Module::isEnabled('avalaratax') || version_compare(_PS_VERSION_, '1.5.4', '<')) {
            return parent::getOrderTotal($with_taxes, $type, $products, $id_carrier, $use_cache);
        }

        if (version_compare(_PS_VERSION_, '1.6.1', '<')) {
            // Store the original, as this value is mutated before executing the hook that relies on it
            $original_with_taxes = $with_taxes;

            if (!$this->id) {
                return 0;
            }

            $type = (int)$type;
            $array_type = array(
                Cart::ONLY_PRODUCTS,
                Cart::ONLY_DISCOUNTS,
                Cart::BOTH,
                Cart::BOTH_WITHOUT_SHIPPING,
                Cart::ONLY_SHIPPING,
                Cart::ONLY_WRAPPING,
                Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING,
                Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING,
            );

            // Define virtual context to prevent case where the cart is not the in the global context
            $virtual_context = Context::getContext()->cloneContext();
            $virtual_context->cart = $this;

            if (!in_array($type, $array_type)) {
                die(Tools::displayError());
            }

            $with_shipping = in_array($type, array(Cart::BOTH, Cart::ONLY_SHIPPING));

            // if cart rules are not used
            if ($type == Cart::ONLY_DISCOUNTS && !CartRule::isFeatureActive()) {
                return 0;
            }

            // no shipping cost if is a cart with only virtuals products
            $virtual = $this->isVirtualCart();
            if ($virtual && $type == Cart::ONLY_SHIPPING) {
                return 0;
            }

            if ($virtual && $type == Cart::BOTH) {
                $type = Cart::BOTH_WITHOUT_SHIPPING;
            }

            if ($with_shipping) {
                if (is_null($products) && is_null($id_carrier)) {
                    $shipping_fees = $this->getTotalShippingCost(null, (boolean)$with_taxes);
                } else {
                    $shipping_fees = $this->getPackageShippingCost($id_carrier, (int)$with_taxes, null, $products);
                }
            } else {
                $shipping_fees = 0;
            }

            if ($type == Cart::ONLY_SHIPPING) {
                return $shipping_fees;
            }

            if ($type == Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING) {
                $type = Cart::ONLY_PRODUCTS;
            }

            $param_product = true;
            if (is_null($products)) {
                $param_product = false;
                $products = $this->getProducts();
            }

            if ($type == Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING) {
                foreach ($products as $key => $product) {
                    if ($product['is_virtual']) {
                        unset($products[$key]);
                    }
                }

                $type = Cart::ONLY_PRODUCTS;
            }

            $order_total = 0;
            if (Tax::excludeTaxeOption()) {
                $with_taxes = false;
            }

            // products refer to the cart details
            foreach ($products as $product) {
                if ($virtual_context->shop->id != $product['id_shop']) {
                    $virtual_context->shop = new Shop((int)$product['id_shop']);
                }

                if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
                    $address_id = (int)$this->id_address_invoice;
                } else {
                    $address_id = (int)$product['id_address_delivery']; // Get delivery address of the product from the cart
                }

                if (!Address::addressExists($address_id)) {
                    $address_id = null;
                }

                if ($this->_taxCalculationMethod == PS_TAX_EXC) {
                    // Here taxes are computed only once the quantity has been applied to the product price
                    $null = null; // The $null variable reference hack for getProductPrice()
                    $price = Product::getPriceStatic(
                        (int)$product['id_product'],
                        false,
                        (int)$product['id_product_attribute'],
                        2,
                        null,
                        false,
                        true,
                        $product['cart_quantity'],
                        false,
                        (int)$this->id_customer ? (int)$this->id_customer : null,
                        (int)$this->id,
                        $address_id,
                        $null,
                        true,
                        true,
                        $virtual_context
                    );

                    $total_ecotax = $product['ecotax'] * (int)$product['cart_quantity'];
                    $total_price = $price * (int)$product['cart_quantity'];

                    if ($with_taxes) {
                        $product_tax_rate = (float)Tax::getProductTaxRate((int)$product['id_product'], (int)$address_id, $virtual_context);
                        $product_eco_tax_rate = Tax::getProductEcotaxRate((int)$address_id);

                        $total_price = ($total_price - $total_ecotax) * (1 + $product_tax_rate / 100);
                        $total_ecotax = $total_ecotax * (1 + $product_eco_tax_rate / 100);
                        $total_price = Tools::ps_round($total_price + $total_ecotax, 2);
                    }
                } else {
                    if ($with_taxes) {
                        $null = null; // The $null variable reference hack for getProductPrice()
                        $price = Product::getPriceStatic(
                            (int)$product['id_product'],
                            true,
                            (int)$product['id_product_attribute'],
                            2,
                            null,
                            false,
                            true,
                            $product['cart_quantity'],
                            false,
                            ((int)$this->id_customer ? (int)$this->id_customer : null),
                            (int)$this->id,
                            ((int)$address_id ? (int)$address_id : null),
                            $null,
                            true,
                            true,
                            $virtual_context
                        );
                    } else {
                        $null = null; // The $null variable reference hack for getProductPrice()
                        $price = Product::getPriceStatic(
                            (int)$product['id_product'],
                            false,
                            (int)$product['id_product_attribute'],
                            2,
                            null,
                            false,
                            true,
                            $product['cart_quantity'],
                            false,
                            ((int)$this->id_customer ? (int)$this->id_customer : null),
                            (int)$this->id,
                            ((int)$address_id ? (int)$address_id : null),
                            $null,
                            true,
                            true,
                            $virtual_context
                        );
                    }

                    $total_price = Tools::ps_round($price * (int)$product['cart_quantity'], 2);
                }

                $order_total += $total_price;
            }

            $order_total_products = $order_total;

            if ($type == Cart::ONLY_DISCOUNTS) {
                $order_total = 0;
            }

            // Wrapping Fees
            $wrapping_fees = 0;
            if ($this->gift) {
                $wrapping_fees = Tools::convertPrice(Tools::ps_round($this->getGiftWrappingPrice($with_taxes), 2), Currency::getCurrencyInstance((int)$this->id_currency));
            }

            if ($type == Cart::ONLY_WRAPPING) {
                return $wrapping_fees;
            }

            $order_total_discount = 0;

            if (!in_array($type, array(Cart::ONLY_SHIPPING, Cart::ONLY_PRODUCTS)) && CartRule::isFeatureActive()) {
                // First, retrieve the cart rules associated to this "getOrderTotal"
                if ($with_shipping || $type == Cart::ONLY_DISCOUNTS) {
                    $cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_ALL);
                } else {
                    $cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_REDUCTION);
                    // Cart Rules array are merged manually in order to avoid doubles
                    foreach ($this->getCartRules(CartRule::FILTER_ACTION_GIFT) as $tmp_cart_rule) {
                        $flag = false;

                        foreach ($cart_rules as $cart_rule) {
                            if ($tmp_cart_rule['id_cart_rule'] == $cart_rule['id_cart_rule']) {
                                $flag = true;
                            }
                        }

                        if (!$flag) {
                            $cart_rules[] = $tmp_cart_rule;
                        }
                    }
                }

                $id_address_delivery = 0;

                if (isset($products[0])) {
                    $id_address_delivery = (is_null($products) ? $this->id_address_delivery : $products[0]['id_address_delivery']);
                }

                $package = array('id_carrier' => $id_carrier, 'id_address' => $id_address_delivery, 'products' => $products);

                // Then, calculate the contextual value for each one
                foreach ($cart_rules as $cart_rule) {
                    // If the cart rule offers free shipping, add the shipping cost
                    if (($with_shipping || $type == Cart::ONLY_DISCOUNTS) && $cart_rule['obj']->free_shipping) {
                        $order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_SHIPPING, ($param_product ? $package : null), $use_cache), 2);
                    }

                    // If the cart rule is a free gift, then add the free gift value only if the gift is in this package
                    if ((int)$cart_rule['obj']->gift_product) {
                        $in_order = false;

                        if (is_null($products)) {
                            $in_order = true;
                        } else {
                            foreach ($products as $product) {
                                if ($cart_rule['obj']->gift_product == $product['id_product'] && $cart_rule['obj']->gift_product_attribute == $product['id_product_attribute']) {
                                    $in_order = true;
                                }
                            }
                        }

                        if ($in_order) {
                            $order_total_discount += $cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_GIFT, $package, $use_cache);
                        }
                    }

                    // If the cart rule offers a reduction, the amount is prorated (with the products in the package)
                    if ($cart_rule['obj']->reduction_percent > 0 || $cart_rule['obj']->reduction_amount > 0) {
                        $order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_REDUCTION, $package, $use_cache), 2);
                    }
                }

                $order_total_discount = min(Tools::ps_round($order_total_discount, 2), $wrapping_fees + $order_total_products + $shipping_fees);
                $order_total -= $order_total_discount;
            }

            if ($type == Cart::BOTH) {
                $order_total += $shipping_fees + $wrapping_fees;
            }

            if ($order_total < 0 && $type != Cart::ONLY_DISCOUNTS) {
                return 0;
            }

            if ($type == Cart::ONLY_DISCOUNTS) {
                return $order_total_discount;
            }

            // Our new hook
            $hook_array = Hook::exec(
                'actionCartGetOrderTotal',
                array(
                    'with_taxes' => $original_with_taxes,
                    'type' => $type,
                    'cart' => $this,
                    'order_total' => $order_total
                ),
                null,
                true
            );

            // Replace order_total with the value returned from the hook
            if (is_array($hook_array)) {
                $hook_array = array_shift($hook_array);
                $order_total = $hook_array['new_order_total'];
            }

            return Tools::ps_round((float)$order_total, 2);
        } else {
            // Dependencies
            $address_factory = Adapter_ServiceLocator::get('Adapter_AddressFactory');
            $price_calculator = Adapter_ServiceLocator::get('Adapter_ProductPriceCalculator');
            $configuration = Adapter_ServiceLocator::get('Core_Business_ConfigurationInterface');

            $ps_tax_address_type = $configuration->get('PS_TAX_ADDRESS_TYPE');
            $ps_use_ecotax = $configuration->get('PS_USE_ECOTAX');
            $ps_round_type = $configuration->get('PS_ROUND_TYPE');
            $ps_ecotax_tax_rules_group_id = $configuration->get('PS_ECOTAX_TAX_RULES_GROUP_ID');
            $compute_precision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

            // Store the original, as this value is mutated before executing the hook that relies on it
            $original_with_taxes = $with_taxes;

            if (!$this->id) {
                return 0;
            }

            $type = (int) $type;
            $array_type = array(
                Cart::ONLY_PRODUCTS,
                Cart::ONLY_DISCOUNTS,
                Cart::BOTH,
                Cart::BOTH_WITHOUT_SHIPPING,
                Cart::ONLY_SHIPPING,
                Cart::ONLY_WRAPPING,
                Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING,
                Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING,
            );

            // Define virtual context to prevent case where the cart is not the in the global context
            $virtual_context = Context::getContext()->cloneContext();
            $virtual_context->cart = $this;

            if (!in_array($type, $array_type)) {
                die(Tools::displayError());
            }

            $with_shipping = in_array($type, array(Cart::BOTH, Cart::ONLY_SHIPPING));

            // if cart rules are not used
            if ($type == Cart::ONLY_DISCOUNTS && !CartRule::isFeatureActive()) {
                return 0;
            }

            // no shipping cost if is a cart with only virtuals products
            $virtual = $this->isVirtualCart();
            if ($virtual && $type == Cart::ONLY_SHIPPING) {
                return 0;
            }

            if ($virtual && $type == Cart::BOTH) {
                $type = Cart::BOTH_WITHOUT_SHIPPING;
            }

            if ($with_shipping || $type == Cart::ONLY_DISCOUNTS) {
                if (is_null($products) && is_null($id_carrier)) {
                    $shipping_fees = $this->getTotalShippingCost(null, (bool) $with_taxes);
                } else {
                    $shipping_fees = $this->getPackageShippingCost((int) $id_carrier, (bool) $with_taxes, null, $products);
                }
            } else {
                $shipping_fees = 0;
            }

            if ($type == Cart::ONLY_SHIPPING) {
                return $shipping_fees;
            }

            if ($type == Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING) {
                $type = Cart::ONLY_PRODUCTS;
            }

            $param_product = true;
            if (is_null($products)) {
                $param_product = false;
                $products = $this->getProducts();
            }

            if ($type == Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING) {
                foreach ($products as $key => $product) {
                    if ($product['is_virtual']) {
                        unset($products[$key]);
                    }
                }
                $type = Cart::ONLY_PRODUCTS;
            }

            $order_total = 0;
            if (Tax::excludeTaxeOption()) {
                $with_taxes = false;
            }

            $products_total = array();
            $ecotax_total = 0;

            foreach ($products as $product) {
                // products refer to the cart details

                if ($virtual_context->shop->id != $product['id_shop']) {
                    $virtual_context->shop = new Shop((int) $product['id_shop']);
                }

                if ($ps_tax_address_type == 'id_address_invoice') {
                    $id_address = (int) $this->id_address_invoice;
                } else {
                    $id_address = (int) $product['id_address_delivery'];
                } // Get delivery address of the product from the cart

                if (!$address_factory->addressExists($id_address)) {
                    $id_address = null;
                }

                // The $null variable below is not used,
                // but it is necessary to pass it to getProductPrice because
                // it expects a reference.
                $null = null;
                $price = $price_calculator->getProductPrice(
                    (int) $product['id_product'],
                    $with_taxes,
                    (int) $product['id_product_attribute'],
                    6,
                    null,
                    false,
                    true,
                    $product['cart_quantity'],
                    false,
                    (int) $this->id_customer ? (int) $this->id_customer : null,
                    (int) $this->id,
                    $id_address,
                    $null,
                    $ps_use_ecotax,
                    true,
                    $virtual_context
                );

                $address = $address_factory->findOrCreate($id_address, true);

                if ($with_taxes) {
                    $id_tax_rules_group = Product::getIdTaxRulesGroupByIdProduct((int) $product['id_product'], $virtual_context);
                    $tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();
                } else {
                    $id_tax_rules_group = 0;
                }

                if (in_array($ps_round_type, array(Order::ROUND_ITEM, Order::ROUND_LINE))) {
                    if (!isset($products_total[$id_tax_rules_group])) {
                        $products_total[$id_tax_rules_group] = 0;
                    }
                } elseif (!isset($products_total[$id_tax_rules_group.'_'.$id_address])) {
                    $products_total[$id_tax_rules_group.'_'.$id_address] = 0;
                }

                switch ($ps_round_type) {
                    case Order::ROUND_TOTAL:
                        $products_total[$id_tax_rules_group.'_'.$id_address] += $price * (int) $product['cart_quantity'];
                        break;

                    case Order::ROUND_LINE:
                        $product_price = $price * $product['cart_quantity'];
                        $products_total[$id_tax_rules_group] += Tools::ps_round($product_price, $compute_precision);
                        break;

                    case Order::ROUND_ITEM:
                    default:
                        $product_price = /*$with_taxes ? $tax_calculator->addTaxes($price) : */
                            $price;
                        $products_total[$id_tax_rules_group] += Tools::ps_round($product_price, $compute_precision) * (int) $product['cart_quantity'];
                        break;
                }
            }

            foreach ($products_total as $key => $price) {
                $order_total += $price;
            }

            $order_total_products = $order_total;

            if ($type == Cart::ONLY_DISCOUNTS) {
                $order_total = 0;
            }

            // Wrapping Fees
            $wrapping_fees = 0;

            // With PS_ATCP_SHIPWRAP on the gift wrapping cost computation calls getOrderTotal with $type === Cart::ONLY_PRODUCTS, so the flag below prevents an infinite recursion.
            $include_gift_wrapping = (!$configuration->get('PS_ATCP_SHIPWRAP') || $type !== Cart::ONLY_PRODUCTS);

            if ($this->gift && $include_gift_wrapping) {
                $wrapping_fees = Tools::convertPrice(Tools::ps_round($this->getGiftWrappingPrice($with_taxes), $compute_precision), Currency::getCurrencyInstance((int) $this->id_currency));
            }
            if ($type == Cart::ONLY_WRAPPING) {
                return $wrapping_fees;
            }

            $order_total_discount = 0;
            $order_shipping_discount = 0;
            if (!in_array($type, array(Cart::ONLY_SHIPPING, Cart::ONLY_PRODUCTS)) && CartRule::isFeatureActive()) {
                // First, retrieve the cart rules associated to this "getOrderTotal"
                if ($with_shipping || $type == Cart::ONLY_DISCOUNTS) {
                    $cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_ALL);
                } else {
                    $cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_REDUCTION);
                    // Cart Rules array are merged manually in order to avoid doubles
                    foreach ($this->getCartRules(CartRule::FILTER_ACTION_GIFT) as $tmp_cart_rule) {
                        $flag = false;
                        foreach ($cart_rules as $cart_rule) {
                            if ($tmp_cart_rule['id_cart_rule'] == $cart_rule['id_cart_rule']) {
                                $flag = true;
                            }
                        }
                        if (!$flag) {
                            $cart_rules[] = $tmp_cart_rule;
                        }
                    }
                }

                $id_address_delivery = 0;
                if (isset($products[0])) {
                    $id_address_delivery = (is_null($products) ? $this->id_address_delivery : $products[0]['id_address_delivery']);
                }
                $package = array('id_carrier' => $id_carrier, 'id_address' => $id_address_delivery, 'products' => $products);

                // Then, calculate the contextual value for each one
                $flag = false;
                foreach ($cart_rules as $cart_rule) {
                    // If the cart rule offers free shipping, add the shipping cost
                    if (($with_shipping || $type == Cart::ONLY_DISCOUNTS) && $cart_rule['obj']->free_shipping && !$flag) {
                        $order_shipping_discount = (float) Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_SHIPPING, ($param_product ? $package : null), $use_cache), $compute_precision);
                        $flag = true;
                    }

                    // If the cart rule is a free gift, then add the free gift value only if the gift is in this package
                    if ((int) $cart_rule['obj']->gift_product) {
                        $in_order = false;
                        if (is_null($products)) {
                            $in_order = true;
                        } else {
                            foreach ($products as $product) {
                                if ($cart_rule['obj']->gift_product == $product['id_product'] && $cart_rule['obj']->gift_product_attribute == $product['id_product_attribute']) {
                                    $in_order = true;
                                }
                            }
                        }

                        if ($in_order) {
                            $order_total_discount += $cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_GIFT, $package, $use_cache);
                        }
                    }

                    // If the cart rule offers a reduction, the amount is prorated (with the products in the package)
                    if ($cart_rule['obj']->reduction_percent > 0 || $cart_rule['obj']->reduction_amount > 0) {
                        $order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_REDUCTION, $package, $use_cache), $compute_precision);
                    }
                }
                $order_total_discount = min(Tools::ps_round($order_total_discount, 2), (float) $order_total_products) + (float) $order_shipping_discount;
                $order_total -= $order_total_discount;
            }

            if ($type == Cart::BOTH) {
                $order_total += $shipping_fees + $wrapping_fees;
            }

            if ($order_total < 0 && $type != Cart::ONLY_DISCOUNTS) {
                return 0;
            }

            if ($type == Cart::ONLY_DISCOUNTS) {
                return $order_total_discount;
            }

            // Our new hook
            $hook_array = Hook::exec(
                'actionCartGetOrderTotal',
                array(
                    'with_taxes' => $original_with_taxes,
                    'type' => $type,
                    'cart' => $this,
                    'order_total' => $order_total
                ),
                null,
                true
            );

            // Replace order_total with the value returned from the hook
            if (is_array($hook_array)) {
                $hook_array = array_shift($hook_array);
                $order_total = $hook_array['new_order_total'];
            }

            return Tools::ps_round((float) $order_total, $compute_precision);
        }
    }

    public function getPackageShippingCost($id_carrier = null, $use_tax = true, Country $default_country = null, $product_list = null, $id_zone = null)
    {
        if (!Module::isEnabled('avalaratax') || version_compare(_PS_VERSION_, '1.5.4', '<')) {
            return parent::getPackageShippingCost($id_carrier, $use_tax, $default_country, $product_list, $id_zone);
        }

        if (version_compare(_PS_VERSION_, '1.6.1', '<')) {
            if ($this->isVirtualCart()) {
                return 0;
            }

            if (!$default_country) {
                $default_country = Context::getContext()->country;
            }

            $complete_product_list = $this->getProducts();

            if (is_null($product_list)) {
                $products = $complete_product_list;
            } else {
                $products = $product_list;
            }

            if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
                $address_id = (int)$this->id_address_invoice;
            } elseif (count($product_list)) {
                $prod = current($product_list);
                $address_id = (int)$prod['id_address_delivery'];
            } else {
                $address_id = null;
            }

            if (!Address::addressExists($address_id)) {
                $address_id = null;
            }

            $cache_id = 'getPackageShippingCost_'.(int)$this->id.'_'.(int)$address_id.'_'.(int)$id_carrier.'_'.(int)$use_tax.'_'.(int)$default_country->id;

            if ($products) {
                foreach ($products as $product) {
                    $cache_id .= '_'.(int)$product['id_product'].'_'.(int)$product['id_product_attribute'];
                }
            }

            if (Cache::isStored($cache_id)) {
                return Cache::retrieve($cache_id);
            }

            // Order total in default currency without fees
            $order_total = $this->getOrderTotal(true, Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING, $product_list);

            // Start with shipping cost at 0
            $shipping_cost = 0;
            // If no product added, return 0
            if (!count($products)) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            if (!isset($id_zone)) {
                // Get id zone
                if (!$this->isMultiAddressDelivery()
                    && isset($this->id_address_delivery) // Be carefull, id_address_delivery is not usefull one 1.5
                    && $this->id_address_delivery
                    && Customer::customerHasAddress($this->id_customer, $this->id_address_delivery)) {
                    $id_zone = Address::getZoneById((int)$this->id_address_delivery);
                } else {
                    if (!Validate::isLoadedObject($default_country)) {
                        $default_country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), Configuration::get('PS_LANG_DEFAULT'));
                    }

                    $id_zone = (int)$default_country->id_zone;
                }
            }

            if ($id_carrier && !$this->isCarrierInRange((int)$id_carrier, (int)$id_zone)) {
                $id_carrier = '';
            }

            if (empty($id_carrier) && $this->isCarrierInRange((int)Configuration::get('PS_CARRIER_DEFAULT'), (int)$id_zone)) {
                $id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
            }

            $total_package_without_shipping_tax_inc = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, $product_list);

            if (empty($id_carrier)) {
                if ((int)$this->id_customer) {
                    $customer = new Customer((int)$this->id_customer);
                    $result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone, $customer->getGroups());
                    unset($customer);
                } else {
                    $result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone);
                }

                foreach ($result as $k => $row) {
                    if ($row['id_carrier'] == Configuration::get('PS_CARRIER_DEFAULT')) {
                        continue;
                    }

                    if (!isset(self::$_carriers[$row['id_carrier']])) {
                        self::$_carriers[$row['id_carrier']] = new Carrier((int)$row['id_carrier']);
                    }

                    $carrier = self::$_carriers[$row['id_carrier']];

                    // Get only carriers that are compliant with shipping method
                    if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && $carrier->getMaxDeliveryPriceByWeight((int)$id_zone) === false)
                        || ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && $carrier->getMaxDeliveryPriceByPrice((int)$id_zone) === false)) {
                        unset($result[$k]);
                        continue;
                    }

                    // If out-of-range behavior carrier is set on "Desactivate carrier"
                    if ($row['range_behavior']) {
                        $check_delivery_price_by_weight = Carrier::checkDeliveryPriceByWeight($row['id_carrier'], $this->getTotalWeight(), (int)$id_zone);

                        $total_order = $total_package_without_shipping_tax_inc;
                        $check_delivery_price_by_price = Carrier::checkDeliveryPriceByPrice($row['id_carrier'], $total_order, (int)$id_zone, (int)$this->id_currency);

                        // Get only carriers that have a range compatible with cart
                        if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && !$check_delivery_price_by_weight)
                            || ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && !$check_delivery_price_by_price)) {
                            unset($result[$k]);
                            continue;
                        }
                    }

                    if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT) {
                        $shipping = $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), (int)$id_zone);
                    } else {
                        $shipping = $carrier->getDeliveryPriceByPrice($order_total, (int)$id_zone, (int)$this->id_currency);
                    }

                    if (!isset($min_shipping_price)) {
                        $min_shipping_price = $shipping;
                    }

                    if ($shipping <= $min_shipping_price) {
                        $id_carrier = (int)$row['id_carrier'];
                        $min_shipping_price = $shipping;
                    }
                }
            }

            if (empty($id_carrier)) {
                $id_carrier = Configuration::get('PS_CARRIER_DEFAULT');
            }

            if (!isset(self::$_carriers[$id_carrier])) {
                self::$_carriers[$id_carrier] = new Carrier((int)$id_carrier, Configuration::get('PS_LANG_DEFAULT'));
            }

            $carrier = self::$_carriers[$id_carrier];

            // No valid Carrier or $id_carrier <= 0 ?
            if (!Validate::isLoadedObject($carrier)) {
                Cache::store($cache_id, 0);
                return 0;
            }

            if (!$carrier->active) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            // Free fees if free carrier
            if ($carrier->is_free == 1) {
                Cache::store($cache_id, 0);
                return 0;
            }

            // Select carrier tax
            if ($use_tax && !Tax::excludeTaxeOption()) {
                $address = Address::initialize((int)$address_id);
                $carrier_tax = $carrier->getTaxesRate($address);
            }

            $configuration = Configuration::getMultiple(
                array(
                    'PS_SHIPPING_FREE_PRICE',
                    'PS_SHIPPING_HANDLING',
                    'PS_SHIPPING_METHOD',
                    'PS_SHIPPING_FREE_WEIGHT'
                )
            );

            // Free fees
            $free_fees_price = 0;
            if (isset($configuration['PS_SHIPPING_FREE_PRICE'])) {
                $free_fees_price = Tools::convertPrice((float)$configuration['PS_SHIPPING_FREE_PRICE'], Currency::getCurrencyInstance((int)$this->id_currency));
            }

            $orderTotalwithDiscounts = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, null, null, false);

            if ($orderTotalwithDiscounts >= (float)($free_fees_price) && (float)($free_fees_price) > 0) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            if (isset($configuration['PS_SHIPPING_FREE_WEIGHT'])
                && $this->getTotalWeight() >= (float)$configuration['PS_SHIPPING_FREE_WEIGHT']
                && (float)$configuration['PS_SHIPPING_FREE_WEIGHT'] > 0) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            // Get shipping cost using correct method
            if ($carrier->range_behavior) {
                if (!isset($id_zone)) {
                    // Get id zone
                    if (isset($this->id_address_delivery)
                        && $this->id_address_delivery
                        && Customer::customerHasAddress($this->id_customer, $this->id_address_delivery)) {
                        $id_zone = Address::getZoneById((int)$this->id_address_delivery);
                    } else {
                        $id_zone = (int)$default_country->id_zone;
                    }
                }

                if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && !Carrier::checkDeliveryPriceByWeight($carrier->id, $this->getTotalWeight(), (int)$id_zone))
                    || ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && !Carrier::checkDeliveryPriceByPrice($carrier->id, $total_package_without_shipping_tax_inc, $id_zone, (int)$this->id_currency)
                    )) {
                    $shipping_cost += 0;
                } else {
                    if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT) {
                        $shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
                    } else {
                        // by price
                        $shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);
                    }
                }
            } else {
                if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT) {
                    $shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
                } else {
                    $shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);
                }

            }
            // Adding handling charges
            if (isset($configuration['PS_SHIPPING_HANDLING']) && $carrier->shipping_handling) {
                $shipping_cost += (float)$configuration['PS_SHIPPING_HANDLING'];
            }

            // Additional Shipping Cost per product
            foreach ($products as $product) {
                if (!$product['is_virtual']) {
                    $shipping_cost += $product['additional_shipping_cost'] * $product['cart_quantity'];
                }
            }

            $shipping_cost = Tools::convertPrice($shipping_cost, Currency::getCurrencyInstance((int)$this->id_currency));

            //get external shipping cost from module
            if ($carrier->shipping_external) {
                $module_name = $carrier->external_module_name;
                $module = Module::getInstanceByName($module_name);

                if (Validate::isLoadedObject($module)) {
                    if (array_key_exists('id_carrier', $module)) {
                        $module->id_carrier = $carrier->id;
                    }
                    if ($carrier->need_range) {
                        if (method_exists($module, 'getPackageShippingCost')) {
                            $shipping_cost = $module->getPackageShippingCost($this, $shipping_cost, $products);
                        } else {
                            $shipping_cost = $module->getOrderShippingCost($this, $shipping_cost);
                        }
                    } else {
                        $shipping_cost = $module->getOrderShippingCostExternal($this);
                    }

                    // Check if carrier is available
                    if ($shipping_cost === false) {
                        Cache::store($cache_id, false);
                        return false;
                    }
                } else {
                    Cache::store($cache_id, false);
                    return false;
                }
            }

            // Apply tax
            if ($use_tax && isset($carrier_tax)) {
                $shipping_cost *= 1 + ($carrier_tax / 100);
            }

            // New hook for allowing AvaTax to add tax to shipping costs
            $hook_array = Hook::exec(
                'actionCartGetPackageShippingCost',
                array(
                    'with_taxes' => $use_tax,
                    'id_carrier' => $id_carrier,
                    'cart' => $this,
                    'shipping_cost' => $shipping_cost
                ),
                null,
                true
            );

            // Replace shipping_cost with value returned from the hook
            if (is_array($hook_array)) {
                $hook_array = array_shift($hook_array);
                $shipping_cost = $hook_array['new_shipping_cost'];
            }

            $shipping_cost = (float)Tools::ps_round((float)$shipping_cost, 2);
            Cache::store($cache_id, $shipping_cost);

            return $shipping_cost;
        } else {
            if ($this->isVirtualCart()) {
                return 0;
            }

            if (!$default_country) {
                $default_country = Context::getContext()->country;
            }

            if (!is_null($product_list)) {
                foreach ($product_list as $key => $value) {
                    if ($value['is_virtual'] == 1) {
                        unset($product_list[$key]);
                    }
                }
            }

            if (is_null($product_list)) {
                $products = $this->getProducts();
            } else {
                $products = $product_list;
            }

            if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
                $address_id = (int)$this->id_address_invoice;
            } elseif (count($product_list)) {
                $prod = current($product_list);
                $address_id = (int)$prod['id_address_delivery'];
            } else {
                $address_id = null;
            }
            if (!Address::addressExists($address_id)) {
                $address_id = null;
            }

            if (is_null($id_carrier) && !empty($this->id_carrier)) {
                $id_carrier = (int)$this->id_carrier;
            }

            $cache_id = 'getPackageShippingCost_'.(int)$this->id.'_'.(int)$address_id.'_'.(int)$id_carrier.'_'.(int)$use_tax.'_'.(int)$default_country->id;
            if ($products) {
                foreach ($products as $product) {
                    $cache_id .= '_'.(int)$product['id_product'].'_'.(int)$product['id_product_attribute'];
                }
            }

            if (Cache::isStored($cache_id)) {
                return Cache::retrieve($cache_id);
            }

            // Order total in default currency without fees
            $order_total = $this->getOrderTotal(true, Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING, $product_list);

            // Start with shipping cost at 0
            $shipping_cost = 0;
            // If no product added, return 0
            if (!count($products)) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            if (!isset($id_zone)) {
                // Get id zone
                if (!$this->isMultiAddressDelivery()
                    && isset($this->id_address_delivery) // Be carefull, id_address_delivery is not usefull one 1.5
                    && $this->id_address_delivery
                    && Customer::customerHasAddress($this->id_customer, $this->id_address_delivery)) {
                    $id_zone = Address::getZoneById((int)$this->id_address_delivery);
                } else {
                    if (!Validate::isLoadedObject($default_country)) {
                        $default_country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), Configuration::get('PS_LANG_DEFAULT'));
                    }

                    $id_zone = (int)$default_country->id_zone;
                }
            }

            if ($id_carrier && !$this->isCarrierInRange((int)$id_carrier, (int)$id_zone)) {
                $id_carrier = '';
            }

            if (empty($id_carrier) && $this->isCarrierInRange((int)Configuration::get('PS_CARRIER_DEFAULT'), (int)$id_zone)) {
                $id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
            }

            $total_package_without_shipping_tax_inc = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, $product_list);
            if (empty($id_carrier)) {
                if ((int)$this->id_customer) {
                    $customer = new Customer((int)$this->id_customer);
                    $result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone, $customer->getGroups());
                    unset($customer);
                } else {
                    $result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone);
                }

                foreach ($result as $k => $row) {
                    if ($row['id_carrier'] == Configuration::get('PS_CARRIER_DEFAULT')) {
                        continue;
                    }

                    if (!isset(self::$_carriers[$row['id_carrier']])) {
                        self::$_carriers[$row['id_carrier']] = new Carrier((int)$row['id_carrier']);
                    }

                    /** @var Carrier $carrier */
                    $carrier = self::$_carriers[$row['id_carrier']];

                    $shipping_method = $carrier->getShippingMethod();
                    // Get only carriers that are compliant with shipping method
                    if (($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT && $carrier->getMaxDeliveryPriceByWeight((int)$id_zone) === false)
                        || ($shipping_method == Carrier::SHIPPING_METHOD_PRICE && $carrier->getMaxDeliveryPriceByPrice((int)$id_zone) === false)) {
                        unset($result[$k]);
                        continue;
                    }

                    // If out-of-range behavior carrier is set on "Desactivate carrier"
                    if ($row['range_behavior']) {
                        $check_delivery_price_by_weight = Carrier::checkDeliveryPriceByWeight($row['id_carrier'], $this->getTotalWeight(), (int)$id_zone);

                        $total_order = $total_package_without_shipping_tax_inc;
                        $check_delivery_price_by_price = Carrier::checkDeliveryPriceByPrice($row['id_carrier'], $total_order, (int)$id_zone, (int)$this->id_currency);

                        // Get only carriers that have a range compatible with cart
                        if (($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT && !$check_delivery_price_by_weight)
                            || ($shipping_method == Carrier::SHIPPING_METHOD_PRICE && !$check_delivery_price_by_price)) {
                            unset($result[$k]);
                            continue;
                        }
                    }

                    if ($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT) {
                        $shipping = $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), (int)$id_zone);
                    } else {
                        $shipping = $carrier->getDeliveryPriceByPrice($order_total, (int)$id_zone, (int)$this->id_currency);
                    }

                    if (!isset($min_shipping_price)) {
                        $min_shipping_price = $shipping;
                    }

                    if ($shipping <= $min_shipping_price) {
                        $id_carrier = (int)$row['id_carrier'];
                        $min_shipping_price = $shipping;
                    }
                }
            }

            if (empty($id_carrier)) {
                $id_carrier = Configuration::get('PS_CARRIER_DEFAULT');
            }

            if (!isset(self::$_carriers[$id_carrier])) {
                self::$_carriers[$id_carrier] = new Carrier((int)$id_carrier, Configuration::get('PS_LANG_DEFAULT'));
            }

            $carrier = self::$_carriers[$id_carrier];

            // No valid Carrier or $id_carrier <= 0 ?
            if (!Validate::isLoadedObject($carrier)) {
                Cache::store($cache_id, 0);
                return 0;
            }
            $shipping_method = $carrier->getShippingMethod();

            if (!$carrier->active) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            // Free fees if free carrier
            if ($carrier->is_free == 1) {
                Cache::store($cache_id, 0);
                return 0;
            }

            // Select carrier tax
            if ($use_tax && !Tax::excludeTaxeOption()) {
                $address = Address::initialize((int)$address_id);

                if (Configuration::get('PS_ATCP_SHIPWRAP')) {
                    // With PS_ATCP_SHIPWRAP, pre-tax price is deduced
                    // from post tax price, so no $carrier_tax here
                    // even though it sounds weird.
                    $carrier_tax = 0;
                } else {
                    $carrier_tax = $carrier->getTaxesRate($address);
                }
            }

            $configuration = Configuration::getMultiple(array(
                'PS_SHIPPING_FREE_PRICE',
                'PS_SHIPPING_HANDLING',
                'PS_SHIPPING_METHOD',
                'PS_SHIPPING_FREE_WEIGHT'
            ));

            // Free fees
            $free_fees_price = 0;
            if (isset($configuration['PS_SHIPPING_FREE_PRICE'])) {
                $free_fees_price = Tools::convertPrice((float)$configuration['PS_SHIPPING_FREE_PRICE'], Currency::getCurrencyInstance((int)$this->id_currency));
            }
            $orderTotalwithDiscounts = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, null, null, false);
            if ($orderTotalwithDiscounts >= (float)($free_fees_price) && (float)($free_fees_price) > 0) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            if (isset($configuration['PS_SHIPPING_FREE_WEIGHT'])
                && $this->getTotalWeight() >= (float)$configuration['PS_SHIPPING_FREE_WEIGHT']
                && (float)$configuration['PS_SHIPPING_FREE_WEIGHT'] > 0) {
                Cache::store($cache_id, $shipping_cost);
                return $shipping_cost;
            }

            // Get shipping cost using correct method
            if ($carrier->range_behavior) {
                if (!isset($id_zone)) {
                    // Get id zone
                    if (isset($this->id_address_delivery)
                        && $this->id_address_delivery
                        && Customer::customerHasAddress($this->id_customer, $this->id_address_delivery)) {
                        $id_zone = Address::getZoneById((int)$this->id_address_delivery);
                    } else {
                        $id_zone = (int)$default_country->id_zone;
                    }
                }

                if (($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT && !Carrier::checkDeliveryPriceByWeight($carrier->id, $this->getTotalWeight(), (int)$id_zone))
                    || ($shipping_method == Carrier::SHIPPING_METHOD_PRICE && !Carrier::checkDeliveryPriceByPrice($carrier->id, $total_package_without_shipping_tax_inc, $id_zone, (int)$this->id_currency)
                    )) {
                    $shipping_cost += 0;
                } else {
                    if ($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT) {
                        $shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
                    } else { // by price
                        $shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);
                    }
                }
            } else {
                if ($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT) {
                    $shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
                } else {
                    $shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);
                }
            }
            // Adding handling charges
            if (isset($configuration['PS_SHIPPING_HANDLING']) && $carrier->shipping_handling) {
                $shipping_cost += (float)$configuration['PS_SHIPPING_HANDLING'];
            }

            // Additional Shipping Cost per product
            foreach ($products as $product) {
                if (!$product['is_virtual']) {
                    $shipping_cost += $product['additional_shipping_cost'] * $product['cart_quantity'];
                }
            }

            $shipping_cost = Tools::convertPrice($shipping_cost, Currency::getCurrencyInstance((int)$this->id_currency));

            //get external shipping cost from module
            if ($carrier->shipping_external) {
                $module_name = $carrier->external_module_name;

                /** @var CarrierModule $module */
                $module = Module::getInstanceByName($module_name);

                if (Validate::isLoadedObject($module)) {
                    if (array_key_exists('id_carrier', $module)) {
                        $module->id_carrier = $carrier->id;
                    }
                    if ($carrier->need_range) {
                        if (method_exists($module, 'getPackageShippingCost')) {
                            $shipping_cost = $module->getPackageShippingCost($this, $shipping_cost, $products);
                        } else {
                            $shipping_cost = $module->getOrderShippingCost($this, $shipping_cost);
                        }
                    } else {
                        $shipping_cost = $module->getOrderShippingCostExternal($this);
                    }

                    // Check if carrier is available
                    if ($shipping_cost === false) {
                        Cache::store($cache_id, false);
                        return false;
                    }
                } else {
                    Cache::store($cache_id, false);
                    return false;
                }
            }

            if (Configuration::get('PS_ATCP_SHIPWRAP')) {
                if (!$use_tax) {
                    // With PS_ATCP_SHIPWRAP, we deduce the pre-tax price from the post-tax
                    // price. This is on purpose and required in Germany.
                    $shipping_cost /= (1 + $this->getAverageProductsTaxRate());
                }
            } else {
                // Apply tax
                if ($use_tax && isset($carrier_tax)) {
                    $shipping_cost *= 1 + ($carrier_tax / 100);
                }
            }

            // New hook for allowing AvaTax to add tax to shipping costs
            $hook_array = Hook::exec(
                'actionCartGetPackageShippingCost',
                array(
                    'with_taxes' => $use_tax,
                    'id_carrier' => $id_carrier,
                    'cart' => $this,
                    'shipping_cost' => $shipping_cost
                ),
                null,
                true
            );

            // Replace shipping_cost with value returned by hook
            if (is_array($hook_array)) {
                $hook_array = array_shift($hook_array);
                $shipping_cost = $hook_array['new_shipping_cost'];
            }

            $shipping_cost = (float)Tools::ps_round((float)$shipping_cost, 2);
            Cache::store($cache_id, $shipping_cost);

            return $shipping_cost;
        }
    }
}
