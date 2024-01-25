# Lunar Online Payments for HikaShop

The software is provided “as is”, without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose and noninfringement.


## Supported HikaShop versions
*The plugin has been tested with most versions of HikaShop at every iteration. We recommend using the latest version of HikaShop, but if that is not possible for some reason, test the plugin with your HikaShop version and it would probably function properly.*

## Note for version 2.0.0
   1. Before installing the new version, make sure you have processed all orders paid through "Lunar".
   1. Please keep in mind that orders not processed by the old "Lunar" method will no longer be able to be processed by the new method.
   1. It will be necessary to check and adjust the settings for the "Lunar" payment method if you have such a method already created.

## Installation

  Once you have installed HikaShop on your Joomla setup, follow these simple steps:
  1. Signup at [lunar.app](https://lunar.app) (it’s free)
  1. Create an account
  1. Create an app key for your Joomla website
  1. Upload the `lunar_0.0.0.zip` and `lunarstatus_0.0.0.zip` files from the latest [release](https://github.com/lunar/payments-plugin-hikashop/releases) trough the Joomla Admin (where 0.0.0 is the form of the current version of the plugin)
  1. Activate both plugins through the 'Extensions' screen in Joomla.
  1. Under HikaShop payment methods create a new payment method and select `HikaShop Lunar Payment Plugin`.
  1. Please pay attention on selecting proper `payment method` in the settings
  1. Insert your app key and public key in the settings for the Lunar payment gateway you just created


## Updating settings

Under the HikaShop Lunar payment method settings, you can:
 * Update the payment method name & description in the payment gateways list
 * Update the shop title that shows up in the hosted checkout page
 * Add public & app keys
 * Change the capture type (Instant/Delayed)

 ## How to capture / refund / void

These actions can be made from an order view, click Edit on order Main Information section and select the status indicated bellow from Order status field.

 1. Capture
 * In Instant mode, the orders are captured automatically (the status of the order will remain as set in the "Confirmed Status" recorded in the settings)
 * In delayed mode you can capture an order by moving the order to the `shipped` status.
 2. Refund
   * To refund an order move the order into `refunded` status.
 3. Void
   * To void an order you can move the order into `refunded` status. If its not captured it will get voided otherwise it will get refunded.

## Available features
1. Capture
   * HikaShop admin panel: full capture
   * Lunar admin panel: full/partial capture
2. Refund
   * HikaShop admin panel: full refund
   * Lunar admin panel: full/partial refund
3. Void
   * HikaShop admin panel: full void
   * Lunar admin panel: full/partial void
