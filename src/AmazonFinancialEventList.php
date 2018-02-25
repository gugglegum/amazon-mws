<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace gugglegum\AmazonMWS;

/**
 * Pulls a list of financial events from Amazon.
 *
 * This Amazon Finance Core object retrieves a list of financial events
 * from Amazon. Because the object has separate lists for each event type,
 * the object cannot be iterated over.
 */
class AmazonFinancialEventList extends AmazonFinanceCore
{
    /**
     * @var bool
     */
    protected $tokenFlag = false;

    /**
     * @var bool
     */
    protected $tokenUseFlag = false;

    /**
     * @var array       Associative array (not a list actually)
     */
    protected $list;

    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken()
    {
        return $this->tokenFlag;
    }

    /**
     * Sets whether or not the object should automatically use tokens if it receives one.
     *
     * If this option is set to TRUE, the object will automatically perform
     * the necessary operations to retrieve the rest of the list using tokens. If
     * this option is off, the object will only ever retrieve the first section of
     * the list.
     * @param boolean $b [optional] Defaults to TRUE
     * @return boolean FALSE if improper input
     */
    public function setUseToken($b = true)
    {
        if (is_bool($b)) {
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }

    /**
     * Sets the maximum number of responses per page. (Optional)
     *
     * This method sets the maximum number of Financial Events for Amazon to return per page.
     * If this parameter is not set, Amazon will send 100 at a time.
     * @param int $num Positive integer from 1 to 100.
     * @return boolean FALSE if improper input
     */
    public function setMaxResultsPerPage($num)
    {
        if (is_numeric($num) && $num <= 100 && $num >= 1) {
            $this->options['MaxResultsPerPage'] = $num;
        } else {
            return false;
        }
    }

    /**
     * Sets the order ID filter. (Required*)
     *
     * If this parameter is set, Amazon will only return Financial Events that
     * relate to the given order. This parameter is required if none of the
     * other filter options are set.
     * If this parameter is set, the group ID and time range options will be removed.
     * @param string $s Amazon Order ID in 3-7-7 format
     * @return boolean FALSE if improper input
     */
    public function setOrderFilter($s)
    {
        if ($s && is_string($s)) {
            $this->resetFilters();
            $this->options['AmazonOrderId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the financial event group ID filter. (Required*)
     *
     * If this parameter is set, Amazon will only return Financial Events that
     * belong to the given financial event group. This parameter is required if
     * none of the other filter options are set.
     * If this parameter is set, the order ID and time range options will be removed.
     * @param string $s Financial Event Group ID
     * @return boolean FALSE if improper input
     */
    public function setGroupFilter($s)
    {
        if ($s && is_string($s)) {
            $this->resetFilters();
            $this->options['FinancialEventGroupId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the time frame options. (Required*)
     *
     * This method sets the start and end times for the next request. If this
     * parameter is set, Amazon will only return Financial Events posted
     * between the two times given. This parameter is required if none of the
     * other filter options are set.
     * The parameters are passed through `strtotime()`, so values such as "-1 hour" are fine.
     * If this parameter is set, the order ID and group ID options will be removed.
     * @param string $s A time string for the earliest time.
     * @param string $e [optional] A time string for the latest time.
     * @return boolean FALSE if improper input
     */
    public function setTimeLimits($s, $e = null)
    {
        if (empty($s)) {
            return FALSE;
        }
        $this->resetFilters();

        $times = $this->genTime($s);
        $this->options['PostedAfter'] = $times;
        if (!empty($e)) {
            $timee = $this->genTime($e);
            $this->options['PostedBefore'] = $timee;
        }
    }

    /**
     * Removes time limit options.
     *
     * Use this in case you change your mind and want to remove the time limit
     * parameters you previously set.
     */
    public function resetTimeLimits()
    {
        unset($this->options['PostedAfter']);
        unset($this->options['PostedBefore']);
    }

    /**
     * Removes all filter options.
     *
     * Use this in case you change your mind and want to remove all filter
     * parameters you previously set.
     */
    public function resetFilters()
    {
        unset($this->options['AmazonOrderId']);
        unset($this->options['FinancialEventGroupId']);
        $this->resetTimeLimits();
    }

    /**
     * Fetches the inventory supply list from Amazon.
     *
     * Submits a `ListFinancialEvents` request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using `getEvents()`.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] When set to FALSE, the function will not recurse, defaults to TRUE
     * @return boolean FALSE if something goes wrong
     */
    public function fetchEventList($r = true)
    {
        $this->prepareToken();

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'] . 'Result';

        if ($this->mockMode) {
            $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)) {
                return false;
            }

            $xml = simplexml_load_string($response['body'])->$path;
        }

        $this->parseXml($xml->FinancialEvents);

        $this->checkToken($xml);

        if ($this->tokenFlag && $this->tokenUseFlag && $r === true) {
            while ($this->tokenFlag) {
                $this->log("Recursively fetching more Financial Events");
                $this->fetchEventList(false);
            }
        }
    }

    /**
     * Sets up options for using tokens.
     *
     * This changes key options for switching between simply fetching a list and
     * fetching the rest of a list using a token. Please note: because the
     * operation for using tokens does not use any other parameters, all other
     * parameters will be removed.
     */
    protected function prepareToken()
    {
        if ($this->tokenFlag && $this->tokenUseFlag) {
            $this->options['Action'] = 'ListFinancialEventsByNextToken';
            unset($this->options['MaxResultsPerPage']);
            $this->resetFilters();
        } else {
            $this->options['Action'] = 'ListFinancialEvents';
            unset($this->options['NextToken']);
            $this->list = array();
        }
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param \SimpleXMLElement $xml The XML response from Amazon.
     * @return boolean FALSE if no XML data is found
     */
    protected function parseXml($xml)
    {
        if (!$xml) {
            return false;
        }
        if (isset($xml->ShipmentEventList)) {
            foreach ($xml->ShipmentEventList->children() as $x) {
                $this->list['Shipment'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->RefundEventList)) {
            foreach ($xml->RefundEventList->children() as $x) {
                $this->list['Refund'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->GuaranteeClaimEventList)) {
            foreach ($xml->GuaranteeClaimEventList->children() as $x) {
                $this->list['GuaranteeClaim'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->ChargebackEventList)) {
            foreach ($xml->ChargebackEventList->children() as $x) {
                $this->list['Chargeback'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->PayWithAmazonEventList)) {
            foreach ($xml->PayWithAmazonEventList->children() as $x) {
                $temp = array();
                $temp['SellerOrderId'] = (string)$x->SellerOrderId;
                $temp['TransactionPostedDate'] = (string)$x->TransactionPostedDate;
                $temp['BusinessObjectType'] = (string)$x->BusinessObjectType;
                $temp['SalesChannel'] = (string)$x->SalesChannel;
                $temp['Charge'] = $this->parseCharge($x->Charge);
                if (isset($x->FeeList)) {
                    foreach ($x->FeeList->children() as $z) {
                        $temp['FeeList'][] = $this->parseFee($z);
                    }
                }
                $temp['PaymentAmountType'] = (string)$x->PaymentAmountType;
                $temp['AmountDescription'] = (string)$x->AmountDescription;
                $temp['FulfillmentChannel'] = (string)$x->FulfillmentChannel;
                $temp['StoreName'] = (string)$x->StoreName;
                $this->list['PayWithAmazon'][] = $temp;
            }
        }
        if (isset($xml->ServiceProviderCreditEventList)) {
            foreach ($xml->ServiceProviderCreditEventList->children() as $x) {
                $temp = array();
                $temp['ProviderTransactionType'] = (string)$x->ProviderTransactionType;
                $temp['SellerOrderId'] = (string)$x->SellerOrderId;
                $temp['MarketplaceId'] = (string)$x->MarketplaceId;
                $temp['MarketplaceCountryCode'] = (string)$x->MarketplaceCountryCode;
                $temp['SellerId'] = (string)$x->SellerId;
                $temp['SellerStoreName'] = (string)$x->SellerStoreName;
                $temp['ProviderId'] = (string)$x->ProviderId;
                $temp['ProviderStoreName'] = (string)$x->ProviderStoreName;
                $this->list['ServiceProviderCredit'][] = $temp;
            }
        }
        if (isset($xml->RetrochargeEventList)) {
            foreach ($xml->RetrochargeEventList->children() as $x) {
                $temp = array();
                $temp['RetrochargeEventType'] = (string)$x->RetrochargeEventType;
                $temp['AmazonOrderId'] = (string)$x->AmazonOrderId;
                $temp['PostedDate'] = (string)$x->PostedDate;
                $temp['BaseTax']['Amount'] = (string)$x->BaseTax->CurrencyAmount;
                $temp['BaseTax']['CurrencyCode'] = (string)$x->BaseTax->CurrencyCode;
                $temp['ShippingTax']['Amount'] = (string)$x->ShippingTax->CurrencyAmount;
                $temp['ShippingTax']['CurrencyCode'] = (string)$x->ShippingTax->CurrencyCode;
                $temp['MarketplaceName'] = (string)$x->MarketplaceName;
                $this->list['Retrocharge'][] = $temp;
            }
        }
        if (isset($xml->RentalTransactionEventList)) {
            foreach ($xml->RentalTransactionEventList->children() as $x) {
                $temp = array();
                $temp['AmazonOrderId'] = (string)$x->AmazonOrderId;
                $temp['RentalEventType'] = (string)$x->RentalEventType;
                $temp['ExtensionLength'] = (string)$x->ExtensionLength;
                $temp['PostedDate'] = (string)$x->PostedDate;
                if (isset($x->RentalChargeList)) {
                    foreach ($x->RentalChargeList->children() as $z) {
                        $temp['RentalChargeList'][] = $this->parseCharge($z);
                    }
                }
                if (isset($x->RentalFeeList)) {
                    foreach ($x->RentalFeeList->children() as $z) {
                        $temp['RentalFeeList'][] = $this->parseFee($z);
                    }
                }
                $temp['MarketplaceName'] = (string)$x->MarketplaceName;
                if (isset($x->RentalInitialValue)) {
                    $temp['RentalInitialValue']['Amount'] = (string)$x->RentalInitialValue->CurrencyAmount;
                    $temp['RentalInitialValue']['CurrencyCode'] = (string)$x->RentalInitialValue->CurrencyCode;
                }
                if (isset($x->RentalReimbursement)) {
                    $temp['RentalReimbursement']['Amount'] = (string)$x->RentalReimbursement->CurrencyAmount;
                    $temp['RentalReimbursement']['CurrencyCode'] = (string)$x->RentalReimbursement->CurrencyCode;
                }
                $this->list['RentalTransaction'][] = $temp;
            }
        }
        if (isset($xml->PerformanceBondRefundEventList)) {
            foreach ($xml->PerformanceBondRefundEventList->children() as $x) {
                $temp = array();
                $temp['MarketplaceCountryCode'] = (string)$x->MarketplaceCountryCode;
                $temp['Amount'] = (string)$x->Amount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->Amount->CurrencyCode;
                if (isset($x->ProductGroupList)) {
                    foreach ($x->ProductGroupList->children() as $z) {
                        $temp['ProductGroupList'][] = (string)$z;
                    }
                }
                $this->list['PerformanceBondRefund'][] = $temp;
            }
        }
        if (isset($xml->ServiceFeeEventList)) {
            foreach ($xml->ServiceFeeEventList->children() as $x) {
                $temp = array();
                $temp['AmazonOrderId'] = (string)$x->AmazonOrderId;
                $temp['FeeReason'] = (string)$x->FeeReason;
                if (isset($x->FeeList)) {
                    foreach ($x->FeeList->children() as $z) {
                        $temp['FeeList'][] = $this->parseFee($z);
                    }
                }
                $temp['SellerSKU'] = (string)$x->SellerSKU;
                $temp['FnSKU'] = (string)$x->FnSKU;
                $temp['FeeDescription'] = (string)$x->FeeDescription;
                $temp['ASIN'] = (string)$x->ASIN;
                $this->list['ServiceFee'][] = $temp;
            }
        }
        if (isset($xml->DebtRecoveryEventList)) {
            foreach ($xml->DebtRecoveryEventList->children() as $x) {
                $temp = array();
                $temp['DebtRecoveryType'] = (string)$x->DebtRecoveryType;
                $temp['RecoveryAmount']['Amount'] = (string)$x->RecoveryAmount->CurrencyAmount;
                $temp['RecoveryAmount']['CurrencyCode'] = (string)$x->RecoveryAmount->CurrencyCode;
                $temp['OverPaymentCredit']['Amount'] = (string)$x->OverPaymentCredit->CurrencyAmount;
                $temp['OverPaymentCredit']['CurrencyCode'] = (string)$x->OverPaymentCredit->CurrencyCode;
                if (isset($x->DebtRecoveryItemList)) {
                    foreach ($x->DebtRecoveryItemList->children() as $z) {
                        $ztemp = array();
                        $ztemp['RecoveryAmount']['Amount'] = (string)$z->RecoveryAmount->CurrencyAmount;
                        $ztemp['RecoveryAmount']['CurrencyCode'] = (string)$z->RecoveryAmount->CurrencyCode;
                        $ztemp['OriginalAmount']['Amount'] = (string)$z->OriginalAmount->CurrencyAmount;
                        $ztemp['OriginalAmount']['CurrencyCode'] = (string)$z->OriginalAmount->CurrencyCode;
                        $ztemp['GroupBeginDate'] = (string)$z->GroupBeginDate;
                        $ztemp['GroupEndDate'] = (string)$z->GroupEndDate;
                        $temp['DebtRecoveryItemList'][] = $ztemp;
                    }
                }
                if (isset($x->ChargeInstrumentList)) {
                    foreach ($x->ChargeInstrumentList->children() as $z) {
                        $ztemp = array();
                        $ztemp['Description'] = (string)$z->Description;
                        $ztemp['Tail'] = (string)$z->Tail;
                        $ztemp['Amount'] = (string)$z->Amount->CurrencyAmount;
                        $ztemp['CurrencyCode'] = (string)$z->Amount->CurrencyCode;
                        $temp['ChargeInstrumentList'][] = $ztemp;
                    }
                }
                $this->list['DebtRecovery'][] = $temp;
            }
        }
        if (isset($xml->LoanServicingEventList)) {
            foreach ($xml->LoanServicingEventList->children() as $x) {
                $temp = array();
                $temp['Amount'] = (string)$x->LoanAmount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->LoanAmount->CurrencyCode;
                $temp['SourceBusinessEventType'] = (string)$x->SourceBusinessEventType;
                $this->list['LoanServicing'][] = $temp;
            }
        }
        if (isset($xml->AdjustmentEventList)) {
            foreach ($xml->AdjustmentEventList->children() as $x) {
                $temp = array();
                $temp['AdjustmentType'] = (string)$x->AdjustmentType;
                $temp['Amount'] = (string)$x->AdjustmentAmount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->AdjustmentAmount->CurrencyCode;
                if (isset($x->AdjustmentItemList)) {
                    foreach ($x->AdjustmentItemList->children() as $z) {
                        $ztemp = array();
                        $ztemp['Quantity'] = (string)$z->Quantity;
                        $ztemp['PerUnitAmount']['Amount'] = (string)$z->PerUnitAmount->CurrencyAmount;
                        $ztemp['PerUnitAmount']['CurrencyCode'] = (string)$z->PerUnitAmount->CurrencyCode;
                        $ztemp['TotalAmount']['Amount'] = (string)$z->TotalAmount->CurrencyAmount;
                        $ztemp['TotalAmount']['CurrencyCode'] = (string)$z->TotalAmount->CurrencyCode;
                        $ztemp['SellerSKU'] = (string)$z->SellerSKU;
                        $ztemp['FnSKU'] = (string)$z->FnSKU;
                        $ztemp['ProductDescription'] = (string)$z->ProductDescription;
                        $ztemp['ASIN'] = (string)$z->ASIN;
                        $temp['AdjustmentItemList'][] = $ztemp;
                    }
                }
                $this->list['Adjustment'][] = $temp;
            }
        }
        if (isset($xml->SAFETReimbursementEventList)) {
            foreach ($xml->SAFETReimbursementEventList->children() as $x) {
                $temp = array();
                $temp['PostedDate'] = (string)$x->PostedDate;
                $temp['SAFETClaimId'] = (string)$x->SAFETClaimId;
                $temp['Amount'] = (string)$x->ReimbursedAmount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->ReimbursedAmount->CurrencyCode;
                $temp['SAFETReimbursementItemList'] = array();
                if (isset($x->SAFETReimbursementItemList)) {
                    foreach ($x->SAFETReimbursementItemList->children() as $y) {
                        if (!isset($y->ItemChargeList)) {
                            continue;
                        }
                        $ztemp = array();
                        foreach ($y->ItemChargeList->children() as $z) {
                            $ztemp['ItemChargeList'][] = $this->parseCharge($z);
                        }
                        $temp['SAFETReimbursementItemList'][] = $ztemp;
                    }
                }
                $this->list['SAFET'][] = $temp;
            }
        }
    }

    /**
     * Parses XML for a single shipment event into an array.
     * @param \SimpleXMLElement $xml The XML response from Amazon.
     * @return array parsed structure from XML
     */
    protected function parseShipmentEvent($xml)
    {
        $r = array();
        $r['AmazonOrderId'] = (string)$xml->AmazonOrderId;
        $r['SellerOrderId'] = (string)$xml->SellerOrderId;
        $r['MarketplaceName'] = (string)$xml->MarketplaceName;
        $chargeLists = array(
            'OrderChargeList',
            'OrderChargeAdjustmentList',
        );
        foreach ($chargeLists as $key) {
            if (isset($xml->$key)) {
                foreach ($xml->$key->children() as $x) {
                    $r[$key][] = $this->parseCharge($x);
                }
            }
        }
        $feelists = array(
            'ShipmentFeeList',
            'ShipmentFeeAdjustmentList',
            'OrderFeeList',
            'OrderFeeAdjustmentList',
        );
        foreach ($feelists as $key) {
            if (isset($xml->$key)) {
                foreach ($xml->$key->children() as $x) {
                    $r[$key][] = $this->parseFee($x);
                }
            }
        }
        if (isset($xml->DirectPaymentList)) {
            foreach ($xml->DirectPaymentList->children() as $x) {
                $temp = array();
                $temp['DirectPaymentType'] = (string)$x->DirectPaymentType;
                $temp['Amount'] = (string)$x->DirectPaymentAmount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->DirectPaymentAmount->CurrencyCode;
                $r['DirectPaymentList'][] = $temp;
            }
        }
        $r['PostedDate'] = (string)$xml->PostedDate;
        $itemLists = array(
            'ShipmentItemList',
            'ShipmentItemAdjustmentList',
        );
        $itemChargeLists = array(
            'ItemChargeList',
            'ItemChargeAdjustmentList',
        );
        $itemFeeLists = array(
            'ItemFeeList',
            'ItemFeeAdjustmentList',
        );
        $itemPromoLists = array(
            'PromotionList',
            'PromotionAdjustmentList',
        );
        foreach ($itemLists as $key) {
            if (isset($xml->$key)) {
                foreach ($xml->$key->children() as $x) {
                    $temp = array();
                    $temp['SellerSKU'] = (string)$x->SellerSKU;
                    $temp['OrderItemId'] = (string)$x->OrderItemId;
                    if (isset($x->OrderAdjustmentItemId)) {
                        $temp['OrderAdjustmentItemId'] = (string)$x->OrderAdjustmentItemId;
                    }
                    $temp['QuantityShipped'] = (string)$x->QuantityShipped;
                    foreach ($itemChargeLists as $zkey) {
                        if (isset($x->$zkey)) {
                            foreach ($x->$zkey->children() as $z) {
                                $temp[$zkey][] = $this->parseCharge($z);
                            }
                        }
                    }
                    foreach ($itemFeeLists as $zkey) {
                        if (isset($x->$zkey)) {
                            foreach ($x->$zkey->children() as $z) {
                                $temp[$zkey][] = $this->parseFee($z);
                            }
                        }
                    }
                    foreach ($itemPromoLists as $zkey) {
                        if (isset($x->$zkey)) {
                            foreach ($x->$zkey->children() as $z) {
                                $ztemp = array();
                                $ztemp['PromotionType'] = (string)$z->PromotionType;
                                $ztemp['PromotionId'] = (string)$z->PromotionId;
                                $ztemp['Amount'] = (string)$z->PromotionAmount->CurrencyAmount;
                                $ztemp['CurrencyCode'] = (string)$z->PromotionAmount->CurrencyCode;
                                $temp[$zkey][] = $ztemp;
                            }
                        }
                    }
                    if (isset($x->CostOfPointsGranted)) {
                        $temp['CostOfPointsGranted']['Amount'] = (string)$x->CostOfPointsGranted->CurrencyAmount;
                        $temp['CostOfPointsGranted']['CurrencyCode'] = (string)$x->CostOfPointsGranted->CurrencyCode;
                    }
                    if (isset($x->CostOfPointsReturned)) {
                        $temp['CostOfPointsReturned']['Amount'] = (string)$x->CostOfPointsReturned->CurrencyAmount;
                        $temp['CostOfPointsReturned']['CurrencyCode'] = (string)$x->CostOfPointsReturned->CurrencyCode;
                    }
                    $r[$key][] = $temp;
                }
            }
        }
        return $r;
    }

    /**
     * Parses XML for a single charge into an array.
     * This structure is used many times throughout shipment events.
     * @param \SimpleXMLElement $xml Charge node of the XML response from Amazon.
     * @return array Parsed structure from XML
     */
    protected function parseCharge($xml)
    {
        $r = array();
        $r['ChargeType'] = (string)$xml->ChargeType;
        $r['Amount'] = (string)$xml->ChargeAmount->CurrencyAmount;
        $r['CurrencyCode'] = (string)$xml->ChargeAmount->CurrencyCode;
        return $r;
    }

    /**
     * Parses XML for a single charge into an array.
     * This structure is used many times throughout shipment events.
     * @param \SimpleXMLElement $xml The XML response from Amazon.
     * @return array parsed structure from XML
     */
    protected function parseFee($xml)
    {
        $r = array();
        $r['FeeType'] = (string)$xml->FeeType;
        $r['Amount'] = (string)$xml->FeeAmount->CurrencyAmount;
        $r['CurrencyCode'] = (string)$xml->FeeAmount->CurrencyCode;
        return $r;
    }

    /**
     * Returns all financial events.
     *
     * The array will have the following keys:
     *
     *  - Shipment - see `getShipmentEvents()`
     *  - Refund - see `getRefundEvents()`
     *  - GuaranteeClaim - see `getGuaranteeClaimEvents()`
     *  - Chargeback - see `getChargebackEvents()`
     *  - PayWithAmazon - see `getPayWithAmazonEvents()`
     *  - ServiceProviderCredit - see `getServiceProviderCreditEvents()`
     *  - Retrocharge - see `getRetrochargeEvents()`
     *  - RentalTransaction - see `getRentalTransactionEvents()`
     *  - PerformanceBondRefund - see `getPerformanceBondRefundEvents()`
     *  - ServiceFee - see `getServiceFeeEvents()`
     *  - DebtRecovery - see `getDebtRecoveryEvents()`
     *  - LoanServicing - see `getLoanServicingEvents()`
     *  - Adjustment - see `getAdjustmentEvents()`
     *  - SAFET - see `getSafetEvents()`
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     * @see getShipmentEvents
     * @see getRefundEvents
     * @see getGuaranteeClaimEvents
     * @see getChargebackEvents
     * @see getPayWithAmazonEvents
     * @see getServiceProviderCreditEvents
     * @see getRetrochargeEvents
     * @see getRentalTransactionEvents
     * @see getPerformanceBondRefundEvents
     * @see getServiceFeeEvents
     * @see getDebtRecoveryEvents
     * @see getLoanServicingEvents
     * @see getAdjustmentEvents
     * @see getSafetEvents
     */
    public function getEvents()
    {
        if (isset($this->list)) {
            return $this->list;
        } else {
            return false;
        }
    }

    /**
     * Returns all shipment events.
     *
     * Each event array will have the following keys:
     *
     *  - AmazonOrderId
     *  - SellerOrderId
     *  - MarketplaceName
     *  - OrderChargeList (optional) - list of charges, only for MCF COD orders
     *  - ShipmentFeeList - list of fees
     *  - OrderFeeList (optional) - list of fees, only for MCF orders
     *  - DirectPaymentList (optional) - multi-dimensional array, only for COD orders.
     * Each array in the list has the following keys:
     *
     *  - DirectPaymentType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - PostedDate - ISO 8601 date format
     *
     *
     * Each "charge" array has the following keys:
     *
     *  - ChargeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     * Each "fee" array has the following keys:
     *
     *  - FeeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     * Each "item" array has the following keys:
     *
     *  - SellerSKU
     *  - OrderItemId
     *  - QuantityShipped
     *  - ItemChargeList - list of charges
     *  - ItemFeeList - list of fees
     *  - CurrencyCode - ISO 4217 currency code
     *  - PromotionList - list of promotions
     *  - CostOfPointsGranted (optional) - array
     *
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *
     * Each "promotion" array has the following keys:
     *
     *  - PromotionType
     *  - PromotionId
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getShipmentEvents()
    {
        if (isset($this->list['Shipment'])) {
            return $this->list['Shipment'];
        } else {
            return false;
        }
    }

    /**
     * Returns all refund events.
     *
     * The structure for each event array is the same as in `getShipmentEvents()`,
     * but with the following additional keys in each "item" array:
     *
     *  - OrderChargeAdjustmentList (optional) - list of charges, only for MCF COD orders
     *  - ShipmentFeeAdjustmentList - list of fees
     *  - OrderFeeAdjustmentList (optional) - list of fees, only for MCF orders
     *
     * Each "item" array will have the following additional keys:
     *
     *  - OrderAdjustmentItemId
     *  - ItemChargeAdjustmentList - list of charges
     *  - ItemFeeAdjustmentList - list of fees
     *  - PromotionAdjustmentList - list of promotions
     *  - CostOfPointsReturned (optional) - array
     *
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     * @see getShipmentEvents
     */
    public function getRefundEvents()
    {
        if (isset($this->list['Refund'])) {
            return $this->list['Refund'];
        } else {
            return false;
        }
    }

    /**
     * Returns all guarantee claim events.
     *
     * The structure for each event array is the same as in `getRefundEvents()`.
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     * @see getRefundEvents
     */
    public function getGuaranteeClaimEvents()
    {
        if (isset($this->list['GuaranteeClaim'])) {
            return $this->list['GuaranteeClaim'];
        } else {
            return false;
        }
    }

    /**
     * Returns all chargeback events.
     *
     * The structure for each event array is the same as in `getRefundEvents()`.
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     * @see getRefundEvents
     */
    public function getChargebackEvents()
    {
        if (isset($this->list['Chargeback'])) {
            return $this->list['Chargeback'];
        } else {
            return false;
        }
    }

    /**
     * Returns all pay with Amazon events.
     *
     * Each event array will have the following keys:
     *
     *  - SellerOrderId
     *  - TransactionPostedDate - ISO 8601 date format
     *  - BusinessObjectType - "PaymentContract"
     *  - SalesChannel
     *  - Charge - array
     *
     *  - ChargeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - FeeList - multi-dimensional array, each array has the following keys:
     *
     *  - FeeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - PaymentAmountType - "Sales"
     *  - AmountDescription
     *  - FulfillmentChannel - "MFN" or "AFN"
     *  - StoreName
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getPayWithAmazonEvents()
    {
        if (isset($this->list['PayWithAmazon'])) {
            return $this->list['PayWithAmazon'];
        } else {
            return false;
        }
    }

    /**
     * Returns all service provider credit events.
     *
     * Each event array will have the following keys:
     *
     *  - ProviderTransactionType - "ProviderCredit" or "ProviderCreditReversal"
     *  - SellerOrderId
     *  - MarketplaceId
     *  - MarketplaceCountryCode - two-letter country code in ISO 3166-1 alpha-2 format
     *  - SellerId
     *  - SellerStoreName
     *  - ProviderId
     *  - ProviderStoreName
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getServiceProviderCreditEvents()
    {
        if (isset($this->list['ServiceProviderCredit'])) {
            return $this->list['ServiceProviderCredit'];
        } else {
            return false;
        }
    }

    /**
     * Returns all retrocharge events.
     *
     * Each event array will have the following keys:
     *
     *  - RetrochargeEventType -"Retrocharge" or "RetrochargeReversal"
     *  - AmazonOrderId
     *  - PostedDate - ISO 8601 date format
     *  - BaseTax - array
     *
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - ShippingTax - array with Amount and CurrencyCode
     *  - MarketplaceName
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getRetrochargeEvents()
    {
        if (isset($this->list['Retrocharge'])) {
            return $this->list['Retrocharge'];
        } else {
            return false;
        }
    }

    /**
     * Returns all rental transaction events.
     *
     * Each event array will have the following keys:
     *
     *  - AmazonOrderId
     *  - RentalEventType
     *  - ExtensionLength (optional)
     *  - PostedDate - ISO 8601 date format
     *  - RentalChargeList - multi-dimensional array, each with the following keys:
     *
     *  - ChargeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - RentalFeeList - multi-dimensional array, each array has the following keys:
     *
     *  - FeeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - MarketplaceName
     *  - RentalInitialValue (optional) - array with Amount and CurrencyCode
     *  - RentalReimbursement (optional) - array with Amount and CurrencyCode
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getRentalTransactionEvents()
    {
        if (isset($this->list['RentalTransaction'])) {
            return $this->list['RentalTransaction'];
        } else {
            return false;
        }
    }

    /**
     * Returns all performance bond refund events.
     *
     * Each event array will have the following keys:
     *
     *  - MarketplaceCountryCode - two-letter country code in ISO 3166-1 alpha-2 format
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *  - ProductGroupList - simple array of category names
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getPerformanceBondRefundEvents()
    {
        if (isset($this->list['PerformanceBondRefund'])) {
            return $this->list['PerformanceBondRefund'];
        } else {
            return false;
        }
    }

    /**
     * Returns all service fee events.
     *
     * Each event array will have the following keys:
     *
     *  - AmazonOrderId
     *  - FeeReason
     *  - FeeList - multi-dimensional array, each array has the following keys:
     *
     *  - FeeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - SellerSKU
     *  - FnSKU
     *  - FeeDescription
     *  - ASIN
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getServiceFeeEvents()
    {
        if (isset($this->list['ServiceFee'])) {
            return $this->list['ServiceFee'];
        } else {
            return false;
        }
    }

    /**
     * Returns all debt recovery events.
     *
     * Each event array will have the following keys:
     *
     *  - DebtRecoveryType - "DebtPayment", "DebtPaymentFailure", or "DebtAdjustment"
     *  - RecoveryAmount - array
     *
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - OverPaymentCredit (optional) - array with Amount and CurrencyCode
     *  - DebtRecoveryItemList - multi-dimensional array, each array has the following keys:
     *
     *  - RecoveryAmount - array with Amount and CurrencyCode
     *  - OriginalAmount - array with Amount and CurrencyCode
     *  - GroupBeginDate - ISO 8601 date format
     *  - GroupEndDate - ISO 8601 date format
     *
     *  - ChargeInstrumentList - multi-dimensional array, each array has the following keys:
     *
     *  - Description
     *  - Tail
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getDebtRecoveryEvents()
    {
        if (isset($this->list['DebtRecovery'])) {
            return $this->list['DebtRecovery'];
        } else {
            return false;
        }
    }

    /**
     * Returns all loan servicing events.
     *
     * Each event array will have the following keys:
     *
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *  - SourceBusinessEventType - "LoanAdvance", "LoanPayment", or "LoanRefund"
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getLoanServicingEvents()
    {
        if (isset($this->list['LoanServicing'])) {
            return $this->list['LoanServicing'];
        } else {
            return false;
        }
    }

    /**
     * Returns all adjustment events.
     *
     * Each event array will have the following keys:
     *
     *  - AdjustmentType "FBAInventoryReimbursement", "ReserveEvent", "PostageBilling", or "PostageRefund"
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *  - AdjustmentItemList - multi-dimensional array, each array has the following keys:
     *
     *  - Quantity
     *  - PerUnitAmount - array with Amount and CurrencyCode
     *  - TotalAmount - array with Amount and CurrencyCode
     *  - SellerSKU
     *  - FnSKU
     *  - ProductDescription
     *  - ASIN
     *
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getAdjustmentEvents()
    {
        if (isset($this->list['Adjustment'])) {
            return $this->list['Adjustment'];
        } else {
            return false;
        }
    }

    /**
     * Returns all SAFE-T reimbursement events.
     *
     * Each event array will have the following keys:
     *
     *  - PostedDate - ISO 8601 date format
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *  - SAFETClaimId
     *  - SAFETReimbursementItemList - multi-dimensional array, each array has the following keys:
     *
     *  - ItemChargeList - multi-dimensional array, each array has the following keys:
     *
     *  - ChargeType
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     *
     *
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getSafetEvents()
    {
        if (isset($this->list['SAFET'])) {
            return $this->list['SAFET'];
        } else {
            return false;
        }
    }

}
