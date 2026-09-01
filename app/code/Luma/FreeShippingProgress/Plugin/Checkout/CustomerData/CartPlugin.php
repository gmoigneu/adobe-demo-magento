<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Luma\FreeShippingProgress\Plugin\Checkout\CustomerData;

use Luma\FreeShippingProgress\Model\FreeShippingProgress;
use Magento\Checkout\CustomerData\Cart;

/**
 * Adds free shipping progress data to the "cart" customer data section.
 *
 * Reusing the existing section means the bar is refreshed by the same
 * invalidation rules the minicart already relies on (add to cart, qty update,
 * item removal), with no extra requests.
 */
class CartPlugin
{
    /**
     * @var FreeShippingProgress
     */
    private $freeShippingProgress;

    /**
     * @param FreeShippingProgress $freeShippingProgress
     */
    public function __construct(FreeShippingProgress $freeShippingProgress)
    {
        $this->freeShippingProgress = $freeShippingProgress;
    }

    /**
     * Append the free shipping progress payload to the cart section data.
     *
     * @param Cart $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $result['free_shipping_progress'] = $this->freeShippingProgress->getData();

        return $result;
    }
}
