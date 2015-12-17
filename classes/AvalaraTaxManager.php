<?php

// TODO: This file should be deleted before our next release

require_once('AvalaraTaxCalculator.php');

class AvalaraTaxManager implements TaxManagerInterface
{
    public $address;
    public $type;
    public $tax_calculator;

    public function __construct(Address $address, $type)
    {
        $this->address = $address;
        $this->type = $type;
    }

    public static function isAvailableForThisAddress(Address $address)
    {
      return true; // Available for all addresses
    }

    public function getTaxCalculator()
    {
      static $tax_enabled = null;

      // Check if we already have a tax calculator
      if (isset($this->tax_calculator)) {
        return $this->tax_calculator;
      }

      if ($tax_enabled === null)
        $tax_enabled = (bool)Configuration::get('AVALARATAX_TAX_CALCULATION'); # Check Module config setting

      if (!$tax_enabled)
        return new AvalaraTaxCalculator(array()); // All tax calculations will use 0 as the rate

      $taxes = array();
      $postcode = 0;
      $tax_code = $this->getTaxCode($this->type);
      $taxes['tax_code'] = $tax_code;


      if (!empty($this->address->postcode)) {
        $postcode = $this->address->postcode;
      }

      $cache_id = (int)$this->address->id_country.'-'.(int)$this->address->id_state.'-'.$postcode.'-'.$tax_code;
      if (!Cache::isStored($cache_id)) {
        $taxes['tax_rate'] = $this->getTaxRate($tax_code);

        $result = new AvalaraTaxCalculator($taxes);
        Cache::store($cache_id, $result);
        return $result;
      }
      return Cache::retrieve($cache_id);
    }

    // Obtain the tax code
    private function getTaxCode($type){
      return $type; # For now just return type
    }

    // Return the tax rate based on a given tax code
    private function getTaxRate($tax_code){
      if($tax_code === 'NT')
        return 0; // Product is not taxable

      if(empty($this->address->id_state) || empty($this->address->postcode))
        return 0; // Cannot calculate tax without at minimum both state and postcode

      // Always return zero as we are now going to rely on different cart hooks to inject tax information
      return 0;

      // Send request to Avalara
      $avalara = new AvalaraTax();
      $rate = $avalara->getSingleTaxRate($this->address, $tax_code);

      return $rate;
    }
}
