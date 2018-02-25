<?php
/**
 * Copyright 2013-2017 CPI Group, LLC
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
 * Fetches list marketplace fee estimates from Amazon.
 *
 * This Amazon Products Core object retrieves a list of fee estimates from Amazon
 * that match the given requests. In order to do this, at least one set of
 * item-related information must be given.
 */
class AmazonProductFeeEstimate extends AmazonProductsCore implements \Iterator
{
    /**
     * @var int
     */
    protected $i = 0;

    /**
     * AmazonProductFeeEstimate fetches a list of fee estimates from Amazon.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param array $config A config array to set.
     * @param boolean $mock [optional] This is a flag for enabling Mock Mode.
     * This defaults to FALSE.
     * @param array|string $m [optional] The files (or file) to use in Mock Mode.
     */
    public function __construct(array $config, $mock = false, $m = null)
    {
        parent::__construct($config, $mock, $m);
        include($this->env);

        $this->options['Action'] = 'GetMyFeesEstimate';

        if (isset($THROTTLE_TIME_PRODUCTFEE)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTFEE;
        }
        $this->throttleGroup = 'GetMyFeesEstimate';
    }

    /**
     * Sets the estimate request(s). (Required)
     *
     * This method sets the list of estimate requests to be sent in the next request.
     * This parameter is required for getting fee estimates from Amazon.
     * The array provided should contain a list of arrays, each with the following fields:
     *
     *  - MarketplaceId - an Amazon marketplace ID
     *  - IdType - "ASIN" or "SellerSKU"
     *  - IdValue - product identifier
     *  - ListingPrice - array
     *
     *  - CurrencyCode - ISO 4217 currency code
     *  - Value - number
     *
     *  - Shipping (optional) - array
     *
     *  - CurrencyCode - ISO 4217 currency code
     *  - Value - number
     *
     *  - Points (optional) - number
     *  - Identifier - unique value that will identify this request
     *  - IsAmazonFulfilled - if offer is fulfilled by Amazon, boolean
     *
     * @param array $a See above.
     * @return boolean FALSE if improper input
     */
    public function setRequests($a)
    {
        if (!is_array($a) || !$a) {
            $this->log('Tried to set Fee Estimate Requests to invalid values', 'Warning');
            return false;
        }
        $this->resetRequests();
        $i = 1;
        foreach ($a as $x) {
            if (is_array($x) && array_key_exists('MarketplaceId', $x) &&
                array_key_exists('IdType', $x) && array_key_exists('IdValue', $x) &&
                array_key_exists('ListingPrice', $x) && array_key_exists('Identifier', $x) &&
                array_key_exists('IsAmazonFulfilled', $x) && is_array($x['ListingPrice']) &&
                array_key_exists('CurrencyCode', $x['ListingPrice']) &&
                array_key_exists('Value', $x['ListingPrice'])) {
                $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.MarketplaceId'] = $x['MarketplaceId'];
                $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.IdType'] = $x['IdType'];
                $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.IdValue'] = $x['IdValue'];
                $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.PriceToEstimateFees.ListingPrice.CurrencyCode'] = $x['ListingPrice']['CurrencyCode'];
                $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.PriceToEstimateFees.ListingPrice.Amount'] = $x['ListingPrice']['Value'];
                if (isset($x['Shipping']) && is_array($x['Shipping'])) {
                    $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.PriceToEstimateFees.Shipping.CurrencyCode'] = $x['Shipping']['CurrencyCode'];
                    $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.PriceToEstimateFees.Shipping.Amount'] = $x['Shipping']['Value'];
                }
                if (array_key_exists('Points', $x)) {
                    $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.PriceToEstimateFees.Points.PointsNumber'] = $x['Points'];
                }
                $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.Identifier'] = $x['Identifier'];
                $this->options['FeesEstimateRequestList.FeesEstimateRequest.' . $i . '.IsAmazonFulfilled'] = $x['IsAmazonFulfilled'];

                $i++;
            } else {
                $this->resetRequests();
                $this->log('Tried to set Fee Estimate Requests with invalid array', 'Warning');
                return false;
            }
        }
    }

    /**
     * Removes request options.
     *
     * Since the list of requests is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetRequests()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#FeesEstimateRequestList#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Fetches a list of product fee estimates from Amazon.
     *
     * Submits a `GetMyFeesEstimate` request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using `getEstimates()`.
     * @return boolean FALSE if something goes wrong
     */
    public function fetchEstimates()
    {
        if (!array_key_exists('FeesEstimateRequestList.FeesEstimateRequest.1.MarketplaceId', $this->options)) {
            $this->log('Fee Requests must be set in order to fetch estimates!', 'Warning');
            return false;
        }

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'] . 'Result';

        if ($this->mockMode) {
            $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)) {
                return false;
            }

            $xml = simplexml_load_string($response['body']);
        }

        $this->parseXml($xml->$path);
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

        $this->productList = array();
        if (!isset($xml->FeesEstimateResultList)) {
            return;
        }
        foreach ($xml->FeesEstimateResultList->children() as $x) {
            $temp = array();
            $temp['MarketplaceId'] = (string)$x->FeesEstimateIdentifier->MarketplaceId;
            $temp['IdType'] = (string)$x->FeesEstimateIdentifier->IdType;
            $temp['IdValue'] = (string)$x->FeesEstimateIdentifier->IdValue;
            $temp['ListingPrice'] = $this->parseMoney($x->FeesEstimateIdentifier->PriceToEstimateFees->ListingPrice);
            if (isset($x->FeesEstimateIdentifier->PriceToEstimateFees->Shipping)) {
                $temp['Shipping'] = $this->parseMoney($x->FeesEstimateIdentifier->PriceToEstimateFees->Shipping);
            }
            if (isset($x->FeesEstimateIdentifier->PriceToEstimateFees->Points->PointsNumber)) {
                $temp['Points'] = (string)$x->FeesEstimateIdentifier->PriceToEstimateFees->Points->PointsNumber;
            }
            $temp['IsAmazonFulfilled'] = (string)$x->FeesEstimateIdentifier->IsAmazonFulfilled;
            $temp['SellerInputIdentifier'] = (string)$x->FeesEstimateIdentifier->SellerInputIdentifier;
            $temp['TimeOfFeesEstimation'] = (string)$x->FeesEstimateIdentifier->TimeOfFeesEstimation;
            $temp['Status'] = (string)$x->Status;
            if (isset($x->FeesEstimate)) {
                $temp['TotalFeesEstimate'] = $this->parseMoney($x->FeesEstimate->TotalFeesEstimate);
                $temp['FeeDetailList'] = array();
                if (isset($x->FeesEstimate->FeeDetailList)) {
                    foreach ($x->FeesEstimate->FeeDetailList->children() as $z) {
                        $temp['FeeDetailList'][] = $this->parseFeeDetail($z);
                    }
                }
            }
            if (isset($x->Error)) {
                $temp['Error']['Type'] = (string)$x->Error->Type;
                $temp['Error']['Code'] = (string)$x->Error->Code;
                $temp['Error']['Message'] = (string)$x->Error->Message;
            }
            $this->productList[] = $temp;
        }
    }

    /**
     * Parses XML for a single money element into an array.
     * This structure is used many times throughout fee estimates.
     * @param \SimpleXMLElement $xml Money node of the XML response from Amazon.
     * @return array Parsed structure from XML
     */
    protected function parseMoney($xml)
    {
        $r = array();
        $r['Amount'] = (string)$xml->Amount;
        $r['CurrencyCode'] = (string)$xml->CurrencyCode;
        return $r;
    }

    /**
     * Parses XML for a single fee detail into an array.
     * This structure is used recursively in fee estimates.
     * @param \SimpleXMLElement $xml Fee Detail node of the XML response from Amazon.
     * @return array Parsed structure from XML
     */
    protected function parseFeeDetail($xml)
    {
        $r = array();
        $r['FeeType'] = (string)$xml->FeeType;
        $r['FeeAmount'] = $this->parseMoney($xml->FeeAmount);
        if (isset($xml->FeePromotion)) {
            $r['FeePromotion'] = $this->parseMoney($xml->FeePromotion);
        }
        if (isset($xml->TaxAmount)) {
            $r['TaxAmount'] = $this->parseMoney($xml->TaxAmount);
        }
        $r['FinalFee'] = $this->parseMoney($xml->FinalFee);
        if (isset($xml->IncludedFeeDetailList)) {
            $r['IncludedFeeDetailList'] = array();
            foreach ($xml->IncludedFeeDetailList->children() as $x) {
                $r['IncludedFeeDetailList'][] = $this->parseFeeDetail($x);
            }
        }
        return $r;
    }

    /**
     * Returns fee estimate specified or array of fee estimates.
     * Each estimate array will have the following keys:
     *
     *  - MarketplaceId
     *  - IdType - "ASIN" or "SellerSKU"
     *  - IdValue
     *  - ListingPrice - money array
     *  - Shipping (optional) - money array
     *  - Points (optional)
     *  - IsAmazonFulfilled - "true" or "false"
     *  - SellerInputIdentifier
     *  - TimeOfFeesEstimation - ISO 8601 date format
     *  - Status
     *  - TotalFeesEstimate (optional) - money array
     *  - FeeDetailList (optional) - array of fee detail arrays
     *  - Error (optional) - array
     *
     *  - Type
     *  - Code
     *  - Message
     *
     *
     *
     * Each "money" array has the following keys:
     *
     *  - Amount - number
     *  - CurrencyCode - ISO 4217 currency code
     *
     * Each "fee detail" array has the following keys:
     *
     *  - FeeType - "ReferralFee", "VariableClosingFee", "PerItemFee",
     *                      "FBAFees", "FBAPickAndPack", "FBAWeightHandling",
     *                      "FBAOrderHandling", or "FBADeliveryServicesFee"
     *  - FeeAmount - money array
     *  - FeePromotion (optional) - money array
     *  - TaxAmount (optional) - money array
     *  - FinalFee - money array
     *  - IncludedFeeDetailList (optional) - array of fee detail arrays
     *
     * @param int $num [optional] List index to retrieve the value from.
     * @return array|boolean multi-dimensional array, or FALSE if list not filled yet
     */
    public function getEstimates($num = null)
    {
        if (!isset($this->productList)) {
            return false;
        }
        if (is_numeric($num)) {
            return $this->productList[$num];
        } else {
            return $this->productList;
        }
    }

    /**
     * Iterator function
     * @return array
     */
    public function current()
    {
        return $this->productList[$this->i];
    }

    /**
     * Iterator function
     */
    public function rewind()
    {
        $this->i = 0;
    }

    /**
     * Iterator function
     * @return int
     */
    public function key()
    {
        return $this->i;
    }

    /**
     * Iterator function
     */
    public function next()
    {
        $this->i++;
    }

    /**
     * Iterator function
     * @return boolean
     */
    public function valid()
    {
        return isset($this->productList[$this->i]);
    }

}
