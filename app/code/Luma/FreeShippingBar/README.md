# Luma_FreeShippingBar

Adds a free-shipping progress bar to the minicart. The bar is rendered above the
item list and nudges shoppers toward the free-shipping threshold:

* below the threshold — a partially filled bar and the message
  `Add $X.XX more for free shipping!`
* at or above the threshold — a fully filled bar in a success color and the
  message `You've unlocked free shipping!`

## Configuration

No new admin configuration is introduced. The module reuses the Free Shipping
carrier settings (**Stores → Configuration → Sales → Delivery Methods → Free
Shipping**):

| Config path | Usage |
| --- | --- |
| `carriers/freeshipping/active` | The bar is only rendered when the carrier is enabled. |
| `carriers/freeshipping/free_shipping_subtotal` | The threshold the progress is measured against. |

Both values are read at store scope.

## How it works

* `Luma\FreeShippingBar\Model\FreeShippingProgress` computes the payload
  (`active`, `qualified`, `threshold`, `subtotal`, `remaining`,
  `remaining_formatted`, `progress`) from the current quote. The comparison uses
  the base subtotal *with discount*, matching what the Free Shipping carrier
  itself evaluates, and the remaining amount is converted and formatted in the
  store currency.
* `Luma\FreeShippingBar\Plugin\Checkout\CustomerData\AddFreeShippingProgress`
  merges that payload into the existing `cart` customer-data section under the
  `free_shipping` key. Because Magento already invalidates the `cart` section on
  add-to-cart, qty change and item removal, the bar updates without a page
  reload.
* `Luma_FreeShippingBar/js/view/free-shipping-bar` is added to the minicart's
  `extraInfo` display area through `view/frontend/layout/default.xml` and renders
  `Luma_FreeShippingBar/minicart/free-shipping-bar`.

The component renders nothing when the carrier is disabled, when the threshold
is missing or not a positive number, or when the cart is empty — an empty cart
keeps showing only the standard empty-cart message.

## Styling

`view/frontend/web/css/source/_module.less` is picked up automatically by
Luma-derived themes via `@magento_import 'source/_module.less'`.

## Tests

```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Luma/FreeShippingBar/Test/Unit
```
