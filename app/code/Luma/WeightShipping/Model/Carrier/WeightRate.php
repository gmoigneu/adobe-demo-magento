<?php
/**
 * Copyright © Luma. All rights reserved.
 */
declare(strict_types=1);

namespace Luma\WeightShipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

/**
 * Weight-based shipping carrier.
 *
 * Prices shipping as a configurable base fee plus a per-bracket rate applied
 * to the total cart weight, e.g. a 12kg cart with a 5kg bracket size is
 * charged the base fee plus three brackets.
 */
class WeightRate extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'weightshipping';

    /**
     * @var ResultFactory
     */
    private ResultFactory $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private MethodFactory $rateMethodFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Collect the weight-based shipping rate for the current rate request.
     *
     * @param RateRequest $request
     * @return Result
     */
    public function collectRates(RateRequest $request)
    {
        $totalWeight = 0.0;

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                $totalWeight += (float) $item->getWeight();
            }
        }

        $baseFee = (float) $this->getConfigData('base_fee');
        $bracketSize = (float) $this->_scopeConfig->getValue('carriers/weightshipping/bracket_size');
        $ratePerBracket = (float) $this->_scopeConfig->getValue('carriers/weightshipping/rate_per_bracket');

        $brackets = (int) ceil($totalWeight / $bracketSize);
        $shippingPrice = $baseFee + $brackets * $ratePerBracket;

        /** @var Result $result */
        $result = $this->rateResultFactory->create();

        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));
        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $result->append($method);

        return $result;
    }

    /**
     * Get allowed shipping methods.
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
