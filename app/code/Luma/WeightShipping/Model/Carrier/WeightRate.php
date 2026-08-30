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
 * Weight-based flat rate shipping carrier.
 *
 * Charges a base fee plus a configurable rate for each weight bracket
 * the cart falls into.
 */
class WeightRate extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'weightrate';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

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
     * Collect weight-based rates for the given request.
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

        $bracketSize = (float) $this->_scopeConfig->getValue('carriers/weightrate/bracket_size');
        $ratePerBracket = (float) $this->_scopeConfig->getValue('carriers/weightrate/rate_per_bracket');
        $baseFee = (float) $this->getConfigData('base_fee');

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
     * @inheritdoc
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
