<?php
/**
 * Copyright © Luma. All rights reserved.
 */
declare(strict_types=1);

namespace Luma\FreeShippingBar\Plugin;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Exposes free-shipping progress data through the "cart" customer-data section,
 * so the minicart bar updates reactively whenever the cart changes.
 */
class AddFreeShippingBarData
{
    private const XML_PATH_ACTIVE = 'carriers/freeshipping/active';
    private const XML_PATH_THRESHOLD = 'carriers/freeshipping/free_shipping_subtotal';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CheckoutSession $checkoutSession,
        private readonly FormatInterface $localeFormat
    ) {
    }

    /**
     * Append free-shipping progress data to the cart section payload.
     *
     * @param Cart $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE);
        $threshold = (float) $this->scopeConfig->getValue(self::XML_PATH_THRESHOLD, ScopeInterface::SCOPE_STORE);

        $result['free_shipping_bar'] = [
            'enabled' => $enabled && $threshold > 0,
            'threshold' => $threshold,
            'subtotal' => (float) $this->checkoutSession->getQuote()->getSubtotal(),
            'price_format' => $this->localeFormat->getPriceFormat(),
        ];

        return $result;
    }
}
