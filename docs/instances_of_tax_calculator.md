Trying to keep track of all the various places TaxCalculator appears

prestashop/classes/Carrier.php

1128    getTaxtesRate(Address $address)
1141    getTaxCalculator(...)              Ugh...


prestashop/classes/Cart.php

642   $tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();
1555  $tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();
1723  $tax_calculator = $tax_manager->getTaxCalculator();


prestashop/classes/Order.php

573   $tax_calculator = OrderDetail::getTaxCalculatorStatic((int)$row['id_order_detail']);
1320  $tax_calculator = $carrier->getTaxCalculator($address);
1338  $wrapping_tax_calculator = $wrapping_tax_manager->getTaxCalculator();

prestashop/classes/OrderDetail.php

321   public static function getTaxCalculatorStatic($id_order_detail)
        also contains several other getTaxCalculator calls as part of this method

prestashop/classes/order/OrderSlip.php

293   $tax_calculator = $carrier->getTaxCalculator($address);
332   $tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();
375   $tax_calculator = TaxManagerFactory::getManager($address, $tmp[0])->getTaxCalculator();



prestashop/classes/Product.php

3010  $product_tax_calculator = $tax_manager->getTaxCalculator();
3033  $ecotax_tax_calculator = $tax_manager->getTaxCalculator();
4375  $row['rate'] = $tax_manager->getTaxCalculator()->getTotalRate();
4376  $row['tax_name'] = $tax_manager->getTaxCalculator()->getTaxesName();
5016  $tax_calculator = $tax_manager->getTaxCalculator();


prestashop/classes/Tax.php

200   $tax_calculator = $tax_manager->getTaxCalculator();
217   $tax_calculator = $tax_manager->getTaxCalculator();
260   $tax_calculator = $tax_manager->getTaxCalculator();

prestashop/controllers/admin/AdminImportController.php

1418  $product_tax_calculator = $tax_manager->getTaxCalculator();

prestashop/controllers/admin/AdminOrdersController.php

2044  $tax_calculator = $carrier->getTaxCalculator($invoice_address);

prestashop/controllers/admin/AdminProductsController.php

3319  $tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();






