<?php
/**
 * Flat fee shipping, flat fees can be added for each supported shipping country from 
 * the SiteConfig using {@link FlatFeeShippingRate}s.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package shop
 * @subpackage shipping
 * @version 1.0
 */
class FlatFeeTax extends Modifier implements Modifier_Interface {
  
	/**
   * For setting configuration, should be called from _config.php files only
   */
  public static function enable() {
    Modifier::$supported_methods[] = 'FlatFeeTax';
    Object::add_extension('SiteConfig', 'FlatFeeTaxConfigDecorator');
  }
  
  /**
   * Form fields for displaying on the Checkout form, a dropdown of {@link FlatFeeShippingRate}s
   * that are filtered depending on the shipping country selected.
   * 
   * @see FlatFeeShippingRate
   * @see Modifier::combined_form_fields()
   * @param Order $order
   * @return FieldSet
   */
  public function getFormFields($order) {
    
    //TODO use SiteConfig object to get the countries back, but at the moment
    //SiteConfig ID not being set correctly on country db rows

	  $fields = new FieldSet();
	  
    //Get tax rate based on shipping address
	  $shippingCountry = null;
	  if ($order && $order->exists()) {
	    $shippingAddress = $order->ShippingAddress();
  	  if ($shippingAddress) $shippingCountry = $shippingAddress->Country;
	  }

	  if ($shippingCountry) {
	    $flatFeeTaxRate = DataObject::get_one('FlatFeeTaxRate', "CountryCode = '$shippingCountry'");
	    
	    if ($flatFeeTaxRate && $flatFeeTaxRate->exists()) {
	      
	      $flatFeeTaxField = new FlatFeeTaxField(
    	    $this,
    	  	$flatFeeTaxRate->Label(),
    	  	$flatFeeTaxRate->ID
    	  );
	      
	      //Set the amount for display on the Order form
    	  $flatFeeTaxField->setAmount($this->Amount($order, $flatFeeTaxField->Value()));
    	  
    	  $fields->push($flatFeeTaxField);
	    }
	  }
	  
	  return $fields;
	}
	
	/**
   * Get form requirements for this modifier.
   * 
   * @see Modifier::combined_form_fields()
   * @param Order $order
   * @return FieldSet
   */
	public function getFormRequirements($order) {
	  return new FieldSet();
	}

  /**
   * Get Amount for this modifier so that it can be saved into an {@link Order} {@link Modification}.
   * 
   * @see Modification
   * @param Order $order
   * @param Int $value ID for a {@link FlatFeeShippingRate} 
   * @return Money
   */
  public function Amount($order, $value) {
    
    $currency = Modification::currency();
    $amount = new Money();
    $amount->setCurrency($currency);

    $taxRate = DataObject::get_by_id('FlatFeeTaxRate', $value);
    
    if ($taxRate && $taxRate->exists()) {
      $amount->setAmount($order->SubTotal->getAmount() * ($taxRate->Rate / 100));
    }
    else {
      user_error("Cannot find flat tax rate for that ID.", E_USER_WARNING);
      //TODO return meaningful error to browser in case error not shown
      return;
    }
    
    return $amount;
  }
  
  /**
   * Get Description for this modifier so that it can be saved into an {@link Order} {@link Modification}.
   * 
   * @see Modification
   * @param Order $order
   * @param Mixed $value A value passed from the Checkout form POST data usually
   * @return String
   */
  public function Description($order, $value) {
    
    $taxRate = DataObject::get_by_id('FlatFeeTaxRate', $value);
    $description = null;
    
    if ($taxRate && $taxRate->exists()) {
      $description = $taxRate->Description;
    }
    else {
      user_error("Cannot find flat fee tax rate for that ID.", E_USER_WARNING);
      //TODO return meaningful error to browser in case error not shown
      return; 
    }
    return $description;
  }
  
  public function addToOrder($order, $value) {
    
    $modification = new Modification();
    $modification->ModifierClass = get_class($this);
    $modification->ModifierOptionID = $value;
    
    $modification->Amount = $this->Amount($order, $value);
    $modification->Description = $this->Description($order, $value);

    $modification->OrderID = $order->ID;
    $modification->write();
  }

}