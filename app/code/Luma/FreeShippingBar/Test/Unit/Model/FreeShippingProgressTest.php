<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Luma\FreeShippingBar\Test\Unit\Model;

use Luma\FreeShippingBar\Model\FreeShippingProgress;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
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

    public function testInactiveWhenCarrierDisabled(): void
    {
        $this->configureCarrier(false);
        $this->checkoutSession->expects($this->never())->method('getQuote');

        $this->assertSame(['active' => false], $this->model->getData());
    }

    /**
     * @param mixed $threshold
     * @dataProvider unusableThresholdDataProvider
     */
    public function testInactiveWhenThresholdIsUnusable($threshold): void
    {
        $this->configureCarrier(true, $threshold);
        $this->checkoutSession->expects($this->never())->method('getQuote');

        $this->assertSame(['active' => false], $this->model->getData());
    }

    /**
     * @return array
     */
    public function unusableThresholdDataProvider(): array
    {
        return [
            'zero' => ['0'],
            'empty string' => [''],
            'null' => [null],
            'non numeric' => ['abc'],
            'negative' => ['-10'],
        ];
    }

    public function testInactiveWhenQuoteIsEmpty(): void
    {
        $this->configureCarrier(true, '50');
        $this->checkoutSession->method('getQuote')->willReturn($this->createQuote(0, 0.0, false, 0));

        $this->assertSame(['active' => false], $this->model->getData());
    }

    public function testInactiveWhenQuoteRetrievalFails(): void
    {
        $this->configureCarrier(true, '50');
        $this->checkoutSession->method('getQuote')
            ->willThrowException(new NoSuchEntityException(new Phrase('No quote')));

        $this->assertSame(['active' => false], $this->model->getData());
    }

    public function testPartialProgress(): void
    {
        $this->configureCarrier(true, '50');
        $this->checkoutSession->method('getQuote')->willReturn($this->createQuote(1, 30.0));
        $this->priceCurrency->expects($this->once())
            ->method('convertAndFormat')
            ->with(20.0, false)
            ->willReturn('$20.00');

        $data = $this->model->getData();

        $this->assertTrue($data['active']);
        $this->assertFalse($data['qualified']);
        $this->assertSame(60, $data['progress']);
        $this->assertSame(20.0, $data['remaining']);
        $this->assertSame('$20.00', $data['remaining_formatted']);
        $this->assertSame(50.0, $data['threshold']);
        $this->assertSame(30.0, $data['subtotal']);
    }

    /**
     * @param float $subtotal
     * @dataProvider qualifyingSubtotalDataProvider
     */
    public function testQualifiedWhenSubtotalReachesThreshold(float $subtotal): void
    {
        $this->configureCarrier(true, '50');
        $this->checkoutSession->method('getQuote')->willReturn($this->createQuote(1, $subtotal));
        $this->priceCurrency->method('convertAndFormat')->willReturn('$0.00');

        $data = $this->model->getData();

        $this->assertTrue($data['qualified']);
        $this->assertSame(100, $data['progress']);
        $this->assertSame(0.0, $data['remaining']);
    }

    /**
     * @return array
     */
    public function qualifyingSubtotalDataProvider(): array
    {
        return [
            'exactly at threshold' => [50.0],
            'above threshold' => [75.0],
        ];
    }

    public function testQualifiedWhenFreeShippingAlreadyGranted(): void
    {
        $this->configureCarrier(true, '50');
        $this->checkoutSession->method('getQuote')->willReturn($this->createQuote(1, 10.0, true));
        $this->priceCurrency->method('convertAndFormat')->willReturn('$0.00');

        $data = $this->model->getData();

        $this->assertTrue($data['qualified']);
        $this->assertSame(100, $data['progress']);
        $this->assertSame(0.0, $data['remaining']);
    }

    public function testVirtualQuoteUsesBillingAddress(): void
    {
        $this->configureCarrier(true, '50');

        $billingAddress = $this->createAddress(40.0, false);
        $shippingAddress = $this->createAddress(5.0, false);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getItemsCount', 'isVirtual', 'getBillingAddress', 'getShippingAddress'])
            ->addMethods(['getBaseSubtotal'])
            ->getMock();
        $quote->method('getId')->willReturn(1);
        $quote->method('getItemsCount')->willReturn(1);
        $quote->method('isVirtual')->willReturn(true);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBaseSubtotal')->willReturn(5.0);

        $this->checkoutSession->method('getQuote')->willReturn($quote);
        $this->priceCurrency->method('convertAndFormat')->willReturn('$10.00');

        $data = $this->model->getData();

        $this->assertSame(40.0, $data['subtotal']);
        $this->assertSame(80, $data['progress']);
    }

    public function testFallsBackToQuoteSubtotalWhenAddressTotalsAreMissing(): void
    {
        $this->configureCarrier(true, '50');
        $this->checkoutSession->method('getQuote')->willReturn($this->createQuote(1, null, false, 1, 25.0));
        $this->priceCurrency->method('convertAndFormat')->willReturn('$25.00');

        $data = $this->model->getData();

        $this->assertSame(25.0, $data['subtotal']);
        $this->assertSame(50, $data['progress']);
        $this->assertSame(25.0, $data['remaining']);
    }

    /**
     * @param bool $active
     * @param mixed $threshold
     * @return void
     */
    private function configureCarrier(bool $active, $threshold = null): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(FreeShippingProgress::XML_PATH_CARRIER_ACTIVE, ScopeInterface::SCOPE_STORE)
            ->willReturn($active);
        $this->scopeConfig->method('getValue')
            ->with(FreeShippingProgress::XML_PATH_FREE_SHIPPING_SUBTOTAL, ScopeInterface::SCOPE_STORE)
            ->willReturn($threshold);
    }

    /**
     * @param int $quoteId
     * @param float|null $addressSubtotal
     * @param bool $freeShipping
     * @param int $itemsCount
     * @param float $quoteSubtotal
     * @return Quote|MockObject
     */
    private function createQuote(
        int $quoteId,
        ?float $addressSubtotal,
        bool $freeShipping = false,
        int $itemsCount = 1,
        float $quoteSubtotal = 0.0
    ) {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getItemsCount', 'isVirtual', 'getBillingAddress', 'getShippingAddress'])
            ->addMethods(['getBaseSubtotal'])
            ->getMock();
        $quote->method('getId')->willReturn($quoteId);
        $quote->method('getItemsCount')->willReturn($itemsCount);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($this->createAddress($addressSubtotal, $freeShipping));
        $quote->method('getBillingAddress')->willReturn($this->createAddress($addressSubtotal, $freeShipping));
        $quote->method('getBaseSubtotal')->willReturn($quoteSubtotal);

        return $quote;
    }

    /**
     * @param float|null $subtotal
     * @param bool $freeShipping
     * @return Address|MockObject
     */
    private function createAddress(?float $subtotal, bool $freeShipping)
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseSubtotalWithDiscount'])
            ->addMethods(['getFreeShipping'])
            ->getMock();
        $address->method('getBaseSubtotalWithDiscount')->willReturn($subtotal);
        $address->method('getFreeShipping')->willReturn($freeShipping);

        return $address;
    }
}
