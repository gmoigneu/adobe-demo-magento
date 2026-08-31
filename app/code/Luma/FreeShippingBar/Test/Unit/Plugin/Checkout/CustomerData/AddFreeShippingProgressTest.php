<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Luma\FreeShippingBar\Test\Unit\Plugin\Checkout\CustomerData;

use Luma\FreeShippingBar\Model\FreeShippingProgress;
use Luma\FreeShippingBar\Plugin\Checkout\CustomerData\AddFreeShippingProgress;
use Magento\Checkout\CustomerData\Cart;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddFreeShippingProgressTest extends TestCase
{
    /**
     * @var FreeShippingProgress|MockObject
     */
    private $freeShippingProgress;

    /**
     * @var Cart|MockObject
     */
    private $subject;

    /**
     * @var AddFreeShippingProgress
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->freeShippingProgress = $this->getMockBuilder(FreeShippingProgress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $this->subject = $this->getMockBuilder(Cart::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new AddFreeShippingProgress($this->freeShippingProgress);
    }

    public function testFreeShippingPayloadIsMergedAndExistingKeysArePreserved(): void
    {
        $payload = [
            'active' => true,
            'qualified' => false,
            'threshold' => 50.0,
            'subtotal' => 30.0,
            'remaining' => 20.0,
            'remaining_formatted' => '$20.00',
            'progress' => 60,
        ];
        $this->freeShippingProgress->expects($this->once())->method('getData')->willReturn($payload);

        $result = $this->plugin->afterGetSectionData(
            $this->subject,
            [
                'summary_count' => 2,
                'subtotal' => '$30.00',
                'items' => [['item_id' => 1]],
            ]
        );

        $this->assertSame(2, $result['summary_count']);
        $this->assertSame('$30.00', $result['subtotal']);
        $this->assertSame([['item_id' => 1]], $result['items']);
        $this->assertSame($payload, $result['free_shipping']);
    }

    public function testInactivePayloadIsStillAdded(): void
    {
        $this->freeShippingProgress->method('getData')->willReturn(['active' => false]);

        $result = $this->plugin->afterGetSectionData($this->subject, ['summary_count' => 0]);

        $this->assertSame(['active' => false], $result['free_shipping']);
    }
}
