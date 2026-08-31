<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Luma\FreeShippingBar\Plugin\Checkout\CustomerData;

use Luma\FreeShippingBar\Model\FreeShippingProgress;
use Magento\Checkout\CustomerData\Cart;

/**
 * Adds the free shipping progress payload to the "cart" customer data section.
 *
 * Reusing the existing section means the minicart progress bar is refreshed by
 * the very same section invalidation that already refreshes the minicart.
 */
class AddFreeShippingProgress
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
     * Merge the free shipping progress data into the cart section data
     *
     * @param Cart $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $result['free_shipping'] = $this->freeShippingProgress->getData();

        return $result;
    }
}
