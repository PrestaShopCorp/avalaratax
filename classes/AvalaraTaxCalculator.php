<?php

// TODO: This file should be deleted before our next release

class AvalaraTaxCalculator extends TaxCalculatorCore
{
  public $count = 0;
  public $tax_code = NULL;
  public $tax_rate = 0;

  public function __construct(array $taxes = array(), $computation_method = TaxCalculator::COMBINE_METHOD)
  {
    // Our tax calculator ignores any Tax Rules from Prestashop
    // instead we look for tax code and tax rate values
    $this->taxes = array();

    if (isset($taxes['tax_code']))
      $this->tax_code = $taxes['tax_code'];
    if (isset($taxes['tax_rate']))
      $this->tax_rate = $taxes['tax_rate'];

    $this->computation_method = (int)$computation_method;
  }

  public function addTaxes($price_te)
  {
    return $price_te * (1 + ($this->getTotalRate() / 100));
  }

  public function removeTaxes($price_ti)
  {
    return $price_ti / (1 + $this->getTotalRate() / 100);
  }

  public function getTotalRate()
  {
    return $this->tax_rate;
  }

}
