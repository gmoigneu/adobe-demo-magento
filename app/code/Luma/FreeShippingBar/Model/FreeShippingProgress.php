<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Luma\FreeShippingBar\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;

/**
 * Builds the free shipping progress payload used by the minicart progress bar.
 *
 * The data is derived from the existing Free Shipping carrier configuration,
 * so no extra admin configuration is required.
 */
class FreeShippingProgress
{
    /**
     * Free Shipping carrier "Enabled" configuration path
     */
    public const XML_PATH_CARRIER_ACTIVE = 'carriers/freeshipping/active';

    /**
     * Free Shipping carrier "Minimum Order Amount" configuration path
     */
    public const XML_PATH_FREE_SHIPPING_SUBTOTAL = 'carriers/freeshipping/free_shipping_subtotal';

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
     * Shape:
     *  - active (bool): whether the bar should be rendered at all
     *  - qualified (bool): the cart already qualifies for free shipping
     *  - threshold (float): configured minimum order amount, in base currency
     *  - subtotal (float): base subtotal with discount used for the comparison
     *  - remaining (float): amount still missing, in base currency
     *  - remaining_formatted (string): remaining amount in the store currency
     *  - progress (int): fill percentage, 0-100
     *
     * @return array
     */
    public function getData(): array
    {
        if (!$this->isCarrierEnabled()) {
            return ['active' => false];
        }

        $threshold = $this->getThreshold();
        if ($threshold <= 0) {
            return ['active' => false];
        }

        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (LocalizedException $e) {
            return ['active' => false];
        }

        if (!$quote->getId() || !$quote->getItemsCount()) {
            return ['active' => false];
        }

        $subtotal = $this->getSubtotal($quote);
        $qualified = $subtotal >= $threshold || $this->isFreeShippingGranted($quote);
        $remaining = $qualified ? 0.0 : max(0.0, $threshold - $subtotal);
        $progress = $qualified ? 100 : min(100, (int)round($subtotal / $threshold * 100));

        return [
            'active' => true,
            'qualified' => $qualified,
            'threshold' => $threshold,
            'subtotal' => $subtotal,
            'remaining' => $remaining,
            'remaining_formatted' => $this->priceCurrency->convertAndFormat($remaining, false),
            'progress' => $progress,
        ];
    }

    /**
     * Whether the Free Shipping carrier is enabled for the current store
     *
     * @return bool
     */
    private function isCarrierEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CARRIER_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Configured free shipping threshold for the current store
     *
     * @return float
     */
    private function getThreshold(): float
    {
        $threshold = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_SUBTOTAL,
            ScopeInterface::SCOPE_STORE
        );

        return is_numeric($threshold) ? (float)$threshold : 0.0;
    }

    /**
     * Base subtotal with discount, matching what the Free Shipping carrier compares against
     *
     * @param Quote $quote
     * @return float
     */
    private function getSubtotal(Quote $quote): float
    {
        $address = $this->getAddress($quote);
        $subtotal = $address ? $address->getBaseSubtotalWithDiscount() : null;

        if ($subtotal === null) {
            $subtotal = $quote->getBaseSubtotal();
        }

        return (float)$subtotal;
    }

    /**
     * Whether free shipping is already granted, e.g. by a cart price rule
     *
     * @param Quote $quote
     * @return bool
     */
    private function isFreeShippingGranted(Quote $quote): bool
    {
        $address = $this->getAddress($quote);

        return $address !== null && (bool)$address->getFreeShipping();
    }

    /**
     * Address carrying the totals for the quote
     *
     * @param Quote $quote
     * @return Address|null
     */
    private function getAddress(Quote $quote): ?Address
    {
        return $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
    }
}
