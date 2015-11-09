<?php

class Product extends ProductCore
{

  public static function getIdTaxRulesGroupByIdProduct($id_product, Context $context = null)
  {
    include_once(_PS_ROOT_DIR_.'/modules/avalaratax/avalaratax.php');

    // Instantiate the Avalara module and check if active
    $avalara = new AvalaraTax();
    if (!$avalara->active)
      return parent::getIdTaxRulesGroupByIdProduct((int)$id_product, $context);

    return $avalara->getProductTaxCode($id_product); // Get tax code from Avalara module database
  }

}
