# Luma_FreeShippingProgress

Adds a free shipping progress bar to the top of the minicart, above the item list.

* While the cart subtotal is below the threshold the bar is partially filled and shows
  `Add $X.XX more for free shipping!`.
* Once the threshold is reached (or a cart price rule already grants free shipping) the bar
  is fully filled, turns green and shows `You've unlocked free shipping!`.

## Configuration

No new admin configuration is introduced. The module reuses the existing Free Shipping
carrier settings under **Stores → Configuration → Sales → Delivery Methods → Free Shipping**:

| Setting | Config path | Used for |
| --- | --- | --- |
| Enabled | `carriers/freeshipping/active` | The bar only renders when the carrier is enabled |
| Minimum Order Amount | `carriers/freeshipping/free_shipping_subtotal` | The threshold the progress is measured against |

The bar is hidden when the carrier is disabled, when the threshold is `0`/empty, when the
cart is empty, and for fully virtual carts.

## How it works

* `Model\FreeShippingProgress` computes `enabled`, `qualified`, `percent`, `remaining` and
  `remaining_formatted` (converted to and formatted in the display currency) from the current
  quote. The comparison uses the base subtotal *with discount* — the same value the
  Free Shipping carrier itself compares against.
* `Plugin\Checkout\CustomerData\CartPlugin` appends that payload to the existing `cart`
  customer data section as `free_shipping_progress`, so the bar refreshes through the same
  section invalidation the minicart already uses (add to cart, qty change, item removal) with
  no page reload and no extra requests.
* `view/frontend/layout/default.xml` registers a Knockout child component of `minicart_content`
  in the `extraInfo` display area, which renders above `.minicart-items-wrapper` and is inside
  the core `summary_count` guard — so an empty cart keeps its usual empty-cart message.

## Installation

```bash
bin/magento module:enable Luma_FreeShippingProgress
bin/magento setup:upgrade
bin/magento cache:flush
```
