<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Luma\FreeShippingProgress\Test\Unit\Model;

use Luma\FreeShippingProgress\Model\FreeShippingProgress;
use Luma\FreeShippingProgress\Plugin\Checkout\CustomerData\CartPlugin;
use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FreeShippingProgressTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSession;

    /**
     * @var PriceCurrencyInterface|MockObject
     */
    private $priceCurrency;

    /**
     * @var FreeShippingProgress
     */
    private $model;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote'])
            ->getMock();
        $this->priceCurrency = $this->getMockForAbstractClass(PriceCurrencyInterface::class);

        $this->model = new FreeShippingProgress(
            $this->scopeConfig,
            $this->checkoutSession,
            $this->priceCurrency
        );
    }

    public function testCarrierDisabledHidesTheBar(): void
    {
        $this->configure(false, 50.0);
        $this->checkoutSession->expects($this->never())->method('getQuote');

        $this->assertSame(['enabled' => false], $this->model->getData());
    }

    public function testThresholdOfZeroHidesTheBar(): void
    {
        $this->configure(true, 0.0);
        $this->checkoutSession->expects($this->never())->method('getQuote');

        $this->assertSame(['enabled' => false], $this->model->getData());
    }

    public function testEmptyCartHidesTheBar(): void
    {
        $this->configure(true, 50.0);
        $this->withQuote($this->createQuote(0, false, 0.0, false));

        $this->assertSame(['enabled' => false], $this->model->getData());
    }

    public function testVirtualCartHidesTheBar(): void
    {
        $this->configure(true, 50.0);
        $this->withQuote($this->createQuote(1, true, 30.0, false));

        $this->assertSame(['enabled' => false], $this->model->getData());
    }

    public function testUnavailableQuoteHidesTheBar(): void
    {
        $this->configure(true, 50.0);
        $this->checkoutSession->method('getQuote')
            ->willThrowException(new \Magento\Framework\Exception\LocalizedException(__('No quote')));

        $this->assertSame(['enabled' => false], $this->model->getData());
    }

    public function testBelowThreshold(): void
    {
        $this->configure(true, 50.0);
        $this->withQuote($this->createQuote(2, false, 30.0, false));
        $this->priceCurrency->expects($this->once())
            ->method('convertAndFormat')
            ->with(20.0, false)
            ->willReturn('$20.00');

        $this->assertSame(
            [
                'enabled' => true,
                'qualified' => false,
                'percent' => 60,
                'remaining' => 20.0,
                'remaining_formatted' => '$20.00',
            ],
            $this->model->getData()
        );
    }

    public function testPercentageIsFloored(): void
    {
        $this->configure(true, 50.0);
        $this->withQuote($this->createQuote(1, false, 14.99, false));
        $this->priceCurrency->method('convertAndFormat')->willReturn('$35.01');

        $data = $this->model->getData();

        $this->assertSame(29, $data['percent']);
        $this->assertEqualsWithDelta(35.01, $data['remaining'], 0.0001);
    }

    public function testExactlyAtThresholdQualifies(): void
    {
        $this->configure(true, 50.0);
        $this->withQuote($this->createQuote(3, false, 50.0, false));
        $this->priceCurrency->expects($this->once())
            ->method('convertAndFormat')
            ->with(0.0, false)
            ->willReturn('$0.00');

        $data = $this->model->getData();

        $this->assertTrue($data['qualified']);
        $this->assertSame(100, $data['percent']);
        $this->assertSame(0.0, $data['remaining']);
    }

    public function testAboveThresholdClampsPercentage(): void
    {
        $this->configure(true, 50.0);
        $this->withQuote($this->createQuote(4, false, 125.0, false));
        $this->priceCurrency->method('convertAndFormat')->willReturn('$0.00');

        $data = $this->model->getData();

        $this->assertTrue($data['qualified']);
        $this->assertSame(100, $data['percent']);
    }

    public function testCartPriceRuleFreeShippingQualifies(): void
    {
        $this->configure(true, 50.0);
        $this->withQuote($this->createQuote(1, false, 10.0, true));
        $this->priceCurrency->method('convertAndFormat')->willReturn('$0.00');

        $data = $this->model->getData();

        $this->assertTrue($data['qualified']);
        $this->assertSame(100, $data['percent']);
        $this->assertSame(0.0, $data['remaining']);
    }

    public function testSubtotalFallsBackToQuoteWhenAddressTotalsAreNotCollected(): void
    {
        $this->configure(true, 50.0);

        $address = $this->createAddressMock(null, false);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItemsCount', 'isVirtual', 'getShippingAddress'])
            ->addMethods(['getBaseSubtotal'])
            ->getMock();
        $quote->method('getItemsCount')->willReturn(1);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getBaseSubtotal')->willReturn(25.0);

        $this->withQuote($quote);
        $this->priceCurrency->expects($this->once())
            ->method('convertAndFormat')
            ->with(25.0, false)
            ->willReturn('$25.00');

        $data = $this->model->getData();

        $this->assertSame(50, $data['percent']);
        $this->assertSame('$25.00', $data['remaining_formatted']);
    }

    public function testPluginAppendsProgressToCartSectionData(): void
    {
        $progress = $this->getMockBuilder(FreeShippingProgress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $progress->method('getData')->willReturn(['enabled' => false]);

        $subject = $this->getMockBuilder(Cart::class)->disableOriginalConstructor()->getMock();
        $plugin = new CartPlugin($progress);

        $result = $plugin->afterGetSectionData($subject, ['summary_count' => 2]);

        $this->assertSame(
            [
                'summary_count' => 2,
                'free_shipping_progress' => ['enabled' => false],
            ],
            $result
        );
    }

    /**
     * Configure the Free Shipping carrier.
     *
     * @param bool $active
     * @param float $threshold
     * @return void
     */
    private function configure(bool $active, float $threshold): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('carriers/freeshipping/active', ScopeInterface::SCOPE_STORE)
            ->willReturn($active);
        $this->scopeConfig->method('getValue')
            ->with('carriers/freeshipping/free_shipping_subtotal', ScopeInterface::SCOPE_STORE)
            ->willReturn($threshold);
    }

    /**
     * Make the checkout session return the given quote.
     *
     * @param Quote|MockObject $quote
     * @return void
     */
    private function withQuote($quote): void
    {
        $this->checkoutSession->method('getQuote')->willReturn($quote);
    }

    /**
     * @param int $itemsCount
     * @param bool $isVirtual
     * @param float $subtotalWithDiscount
     * @param bool $ruleFreeShipping
     * @return Quote|MockObject
     */
    private function createQuote(
        int $itemsCount,
        bool $isVirtual,
        float $subtotalWithDiscount,
        bool $ruleFreeShipping
    ) {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItemsCount', 'isVirtual', 'getShippingAddress'])
            ->addMethods(['getBaseSubtotal'])
            ->getMock();
        $quote->method('getItemsCount')->willReturn($itemsCount);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')
            ->willReturn($this->createAddressMock($subtotalWithDiscount, $ruleFreeShipping));
        $quote->method('getBaseSubtotal')->willReturn($subtotalWithDiscount);

        return $quote;
    }

    /**
     * @param float|null $subtotalWithDiscount
     * @param bool $ruleFreeShipping
     * @return Address|MockObject
     */
    private function createAddressMock(?float $subtotalWithDiscount, bool $ruleFreeShipping)
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseSubtotalWithDiscount'])
            ->addMethods(['getFreeShipping'])
            ->getMock();
        $address->method('getBaseSubtotalWithDiscount')->willReturn($subtotalWithDiscount);
        $address->method('getFreeShipping')->willReturn($ruleFreeShipping);

        return $address;
    }
}
