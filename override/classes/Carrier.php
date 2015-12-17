<?php

// TODO: This file is no longer necessary and should be removed before the next release

class Carrier extends CarrierCore
{
  public static function getIdTaxRulesGroupByIdCarrier($id_carrier, Context $context = null)
  {
    include_once(_PS_ROOT_DIR_.'/modules/avalaratax/avalaratax.php');

    // Instantiate the Avalara module and check if active
    $avalara = new AvalaraTax();
    if (!$avalara->active)
      return parent::getIdTaxRulesGroupByIdProduct((int)$id_product, $context);

    return "FR020100"; // Default TaxCode for Shipping. Avalara will decide depending on the State if taxes should be charged or not
  }
}
