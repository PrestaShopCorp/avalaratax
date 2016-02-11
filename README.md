# Avalara AvaTax Integration
## Version Support
This module currently supports versions 1.5.6.x and 1.6.1.x of Prestashop. Versions 1.5.4.x and 1.5.5.x may also work, but are NOT tested nor supported. Version 1.6.0.x does NOT work and is NOT supported.

This module only supports servers that are running PHP 5.4.0 or greater.

## Installation

This module can be installed directly from the "Modules" page of your Prestashop website's back office. After installation there are a few configurations that must be made to both the module and your Prestashop website.

## Module Configuration

### AvaTax Credentials
In order to use this module you must first obtain valid credentials from Avalara. You can [sign up here](http://www.info.avalara.com/prestashop).

Once you have valid credentials, enter them into the Configuration section of this module and click the "Save Settings" button. The module will automatically attempt to connect to Avalara and validate your credentials.

### Default Origin Address
The address you enter here will be used as the origin address for the purposes of tax calculation. When you have entered all of your address information click the "Save Address" button.

### Module Options
* `Enable address validation` - If checked, the module will perform address validation when adding new addresses or modifying existing addresses
* `Enable tax calculation` - Uncheck this box if you need to temporarily disable tax calculation for your store
* `Enable address normalization in uppercase` - If checked, all validated addresses will be normalized into uppercase
* `Enable tax calculation outside of your state` - Uncheck this box if you only have a single state nexus


## Prestashop Configuration

### Disable Native Taxes
This module replaces Prestashop's native tax calculation. To avoid conflicts, you must manually disable native tax calculation for your Prestashop store. 

1. Visit the "Taxes" page under the "Localization" tab of your back office
2. Set the `Enable tax` option to "No"
3. Set the `Use ecotax` option to "No"
4. Set the `Dislpay tax in the shopping cart` option to "Yes"
5. Save your changes


### Product Tax Codes
Avalara calculates taxes for your products based on an extensive list of tax codes. Unless you are relying on UPC codes, you must manually enter a tax code for each of your products. For all version's of Prestashop this can be accomplished by visiting the edit product page of your back office. However, the `tax code` field location differs between versions of Prestashop.

**Prestashop Version 1.6.1.x**

The `tax code` field is located under the "Prices" tab of your product's edit page.


**Prestashop Version 1.5.6.x**

The `tax code` field is located under the "Information" tab of your product's edit page.

To manually specify that one of your products is not taxable you can enter `NT` as the value of the tax code field for a product.

Please note, that if you do not set a tax code for a product, Avalara will attempt to calculate taxes based off of the UPC code that you have set for each product. If neither a tax code nor a UPC code are set for a product, Avalara will assume a default tax code of `P0000000`.

### Customer Entity Use Codes
You can exempt tax calculation for all of a customer's orders by choosing an Entity Use Code for that customer. This setting can be found on a customer's edit page in the Prestashop back office.

