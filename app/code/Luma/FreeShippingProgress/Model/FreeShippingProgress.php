<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Luma\FreeShippingProgress\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

/**
 * Calculates how close the current quote is to the free shipping threshold.
 *
 * The threshold and the on/off switch are taken from the existing Free Shipping
 * carrier configuration, so no extra admin configuration is introduced.
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class FreeShippingProgress
{
    /**
     * Free Shipping carrier "Enabled" config path
     */
    private const XML_PATH_ACTIVE = 'carriers/freeshipping/active';

    /**
     * Free Shipping carrier "Minimum Order Amount" config path
     */
    private const XML_PATH_THRESHOLD = 'carriers/freeshipping/free_shipping_subtotal';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CheckoutSession $checkoutSession,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Free shipping progress data for the current quote.
     *
     * @return array
     */
    public function getData(): array
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE)) {
            return $this->disabled();
        }

        $threshold = (float)$this->scopeConfig->getValue(self::XML_PATH_THRESHOLD, ScopeInterface::SCOPE_STORE);
        if ($threshold <= 0) {
            return $this->disabled();
        }

        $quote = $this->getQuote();
        if ($quote === null || (int)$quote->getItemsCount() < 1 || $quote->isVirtual()) {
            return $this->disabled();
        }

        $subtotal = $this->getSubtotal($quote);
        $qualified = $subtotal >= $threshold || (bool)$this->getShippingAddressFreeShipping($quote);
        $remaining = $qualified ? 0.0 : max(0.0, $threshold - $subtotal);
        $percent = $qualified ? 100 : (int)min(100, max(0, (int)floor($subtotal / $threshold * 100)));

        return [
            'enabled' => true,
            'qualified' => $qualified,
            'percent' => $percent,
            'remaining' => $remaining,
            'remaining_formatted' => $this->priceCurrency->convertAndFormat($remaining, false),
        ];
    }

    /**
     * Payload used whenever there is no progress worth showing.
     *
     * @return array
     */
    private function disabled(): array
    {
        return ['enabled' => false];
    }

    /**
     * Current quote, or null when it cannot be loaded.
     *
     * @return Quote|null
     */
    private function getQuote(): ?Quote
    {
        try {
            return $this->checkoutSession->getQuote();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Base subtotal compared against the threshold.
     *
     * Mirrors the Free Shipping carrier, which compares the subtotal *with discount*.
     * Falls back to the plain quote subtotal while address totals are not collected yet.
     *
     * @param Quote $quote
     * @return float
     */
    private function getSubtotal(Quote $quote): float
    {
        $address = $quote->getShippingAddress();
        if ($address !== null && $address->getBaseSubtotalWithDiscount() !== null) {
            return (float)$address->getBaseSubtotalWithDiscount();
        }

        return (float)$quote->getBaseSubtotal();
    }

    /**
     * Whether a cart price rule already granted free shipping.
     *
     * @param Quote $quote
     * @return bool
     */
    private function getShippingAddressFreeShipping(Quote $quote): bool
    {
        $address = $quote->getShippingAddress();

        return $address !== null && (bool)$address->getFreeShipping();
    }
}
