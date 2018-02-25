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
 * Gets all of the items for a given order.
 *
 * This Amazon Orders Core object can retrieve the list of items associated
 * with a specific order. Before any items can be retrieved, an Order ID is
 * required. This object can use tokens when retrieving the list.
 */
class AmazonOrderItemList extends AmazonOrderCore implements \Iterator
{
    protected $orderId;

    /**
     * @var array[]     Indexed array of associative arrays
     */
    protected $itemList;

    /**
     * @var bool
     */
    protected $tokenFlag = false;

    /**
     * @var bool
     */
    protected $tokenUseFlag = false;

    /**
     * @var int
     */
    protected $i = 0;

    /**
     * @var int
     */
    protected $index = 0;

    /**
     * AmazonItemLists contain all of the items for a given order.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param array $config A config array to set.
     * @param string $id [optional] The order ID to set for the object.
     * @param boolean $mock [optional] This is a flag for enabling Mock Mode.
     * This defaults to FALSE.
     * @param array|string $m [optional] The files (or file) to use in Mock Mode.
     */
    public function __construct(array $config, $id = null, $mock = false, $m = null)
    {
        parent::__construct($config, $mock, $m);
        include($this->env);

        if (!is_null($id)) {
            $this->setOrderId($id);
        }

        if (isset($THROTTLE_LIMIT_ITEM)) {
            $this->throttleLimit = $THROTTLE_LIMIT_ITEM;
        }
        if (isset($THROTTLE_TIME_ITEM)) {
            $this->throttleTime = $THROTTLE_TIME_ITEM;
        }
        $this->throttleGroup = 'ListOrderItems';
    }

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
     * Sets the Amazon Order ID. (Required)
     *
     * This method sets the Amazon Order ID to be sent in the next request.
     * This parameter is required for fetching the order's items from Amazon.
     * @param string $id Amazon Order ID
     * @return boolean FALSE if improper input
     */
    public function setOrderId($id)
    {
        if (is_string($id) || is_numeric($id)) {
            $this->options['AmazonOrderId'] = $id;
        } else {
            return false;
        }
    }

    /**
     * Retrieves the items from Amazon.
     *
     * Submits a `ListOrderItems` request to Amazon. In order to do this,
     * an Amazon order ID is required. Amazon will send
     * the data back as a response, which can be retrieved using `getItems()`.
     * Other methods are available for fetching specific values from the order.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] When set to FALSE, the function will not recurse, defaults to TRUE
     * @return boolean FALSE if something goes wrong
     */
    public function fetchItems($r = true)
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

        if (is_null($xml->AmazonOrderId)) {
            $this->log("You just got throttled.", 'Warning');
            return false;
        }
        $this->orderId = (string)$xml->AmazonOrderId;
        if (!empty($this->options['AmazonOrderId']) && $this->options['AmazonOrderId'] != $this->orderId) {
            $this->log('You grabbed the wrong Order\'s items! - ' . $this->options['AmazonOrderId'] . ' =/= ' . $this->orderId, 'Urgent');
        }

        $this->parseXML($xml->OrderItems);

        $this->checkToken($xml);

        if ($this->tokenFlag && $this->tokenUseFlag && $r === true) {
            while ($this->tokenFlag) {
                $this->log("Recursively fetching more items");
                $this->fetchItems(false);
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
            $this->options['Action'] = 'ListOrderItemsByNextToken';
            //When using tokens, only the NextToken option should be used
            unset($this->options['AmazonOrderId']);
        } else {
            $this->options['Action'] = 'ListOrderItems';
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->itemList = array();
        }
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param \SimpleXMLElement $xml The XML response from Amazon.
     * @return boolean FALSE if no XML data is found
     */
    protected function parseXML($xml)
    {
        if (!$xml) {
            return false;
        }

        foreach ($xml->children() as $item) {
            $n = $this->index;

            $this->itemList[$n]['ASIN'] = (string)$item->ASIN;
            $this->itemList[$n]['SellerSKU'] = (string)$item->SellerSKU;
            $this->itemList[$n]['OrderItemId'] = (string)$item->OrderItemId;
            $this->itemList[$n]['Title'] = (string)$item->Title;
            $this->itemList[$n]['QuantityOrdered'] = (string)$item->QuantityOrdered;
            if (isset($item->QuantityShipped)) {
                $this->itemList[$n]['QuantityShipped'] = (string)$item->QuantityShipped;
            }
            if (isset($item->BuyerCustomizedInfo->CustomizedURL)) {
                $this->itemList[$n]['BuyerCustomizedInfo'] = (string)$item->BuyerCustomizedInfo->CustomizedURL;
            }
            if (isset($item->PointsGranted)) {
                $this->itemList[$n]['PointsGranted']['PointsNumber'] = (string)$item->PointsGranted->PointsNumber;
                $this->itemList[$n]['PointsGranted']['Amount'] = (string)$item->PointsGranted->PointsMonetaryValue->Amount;
                $this->itemList[$n]['PointsGranted']['CurrencyCode'] = (string)$item->PointsGranted->PointsMonetaryValue->CurrencyCode;
            }
            if (isset($item->PriceDesignation)) {
                $this->itemList[$n]['PriceDesignation'] = (string)$item->PriceDesignation;
            }
            if (isset($item->GiftMessageText)) {
                $this->itemList[$n]['GiftMessageText'] = (string)$item->GiftMessageText;
            }
            if (isset($item->GiftWrapLevel)) {
                $this->itemList[$n]['GiftWrapLevel'] = (string)$item->GiftWrapLevel;
            }
            if (isset($item->ItemPrice)) {
                $this->itemList[$n]['ItemPrice']['Amount'] = (string)$item->ItemPrice->Amount;
                $this->itemList[$n]['ItemPrice']['CurrencyCode'] = (string)$item->ItemPrice->CurrencyCode;
            }
            if (isset($item->ShippingPrice)) {
                $this->itemList[$n]['ShippingPrice']['Amount'] = (string)$item->ShippingPrice->Amount;
                $this->itemList[$n]['ShippingPrice']['CurrencyCode'] = (string)$item->ShippingPrice->CurrencyCode;
            }
            if (isset($item->GiftWrapPrice)) {
                $this->itemList[$n]['GiftWrapPrice']['Amount'] = (string)$item->GiftWrapPrice->Amount;
                $this->itemList[$n]['GiftWrapPrice']['CurrencyCode'] = (string)$item->GiftWrapPrice->CurrencyCode;
            }
            if (isset($item->ItemTax)) {
                $this->itemList[$n]['ItemTax']['Amount'] = (string)$item->ItemTax->Amount;
                $this->itemList[$n]['ItemTax']['CurrencyCode'] = (string)$item->ItemTax->CurrencyCode;
            }
            if (isset($item->ShippingTax)) {
                $this->itemList[$n]['ShippingTax']['Amount'] = (string)$item->ShippingTax->Amount;
                $this->itemList[$n]['ShippingTax']['CurrencyCode'] = (string)$item->ShippingTax->CurrencyCode;
            }
            if (isset($item->GiftWrapTax)) {
                $this->itemList[$n]['GiftWrapTax']['Amount'] = (string)$item->GiftWrapTax->Amount;
                $this->itemList[$n]['GiftWrapTax']['CurrencyCode'] = (string)$item->GiftWrapTax->CurrencyCode;
            }
            if (isset($item->ShippingDiscount)) {
                $this->itemList[$n]['ShippingDiscount']['Amount'] = (string)$item->ShippingDiscount->Amount;
                $this->itemList[$n]['ShippingDiscount']['CurrencyCode'] = (string)$item->ShippingDiscount->CurrencyCode;
            }
            if (isset($item->PromotionDiscount)) {
                $this->itemList[$n]['PromotionDiscount']['Amount'] = (string)$item->PromotionDiscount->Amount;
                $this->itemList[$n]['PromotionDiscount']['CurrencyCode'] = (string)$item->PromotionDiscount->CurrencyCode;
            }
            if (isset($item->CODFee)) {
                $this->itemList[$n]['CODFee']['Amount'] = (string)$item->CODFee->Amount;
                $this->itemList[$n]['CODFee']['CurrencyCode'] = (string)$item->CODFee->CurrencyCode;
            }
            if (isset($item->CODFeeDiscount)) {
                $this->itemList[$n]['CODFeeDiscount']['Amount'] = (string)$item->CODFeeDiscount->Amount;
                $this->itemList[$n]['CODFeeDiscount']['CurrencyCode'] = (string)$item->CODFeeDiscount->CurrencyCode;
            }
            if (isset($item->PromotionIds)) {
                $i = 0;
                foreach ($item->PromotionIds->children() as $x) {
                    $this->itemList[$n]['PromotionIds'][$i] = (string)$x;
                    $i++;
                }
            }
            if (isset($item->InvoiceData)) {
                if (isset($item->InvoiceData->InvoiceRequirement)) {
                    $this->itemList[$n]['InvoiceData']['InvoiceRequirement'] = (string)$item->InvoiceData->InvoiceRequirement;
                }
                if (isset($item->InvoiceData->BuyerSelectedInvoiceCategory)) {
                    $this->itemList[$n]['InvoiceData']['BuyerSelectedInvoiceCategory'] = (string)$item->InvoiceData->BuyerSelectedInvoiceCategory;
                }
                if (isset($item->InvoiceData->InvoiceTitle)) {
                    $this->itemList[$n]['InvoiceData']['InvoiceTitle'] = (string)$item->InvoiceData->InvoiceTitle;
                }
                if (isset($item->InvoiceData->InvoiceInformation)) {
                    $this->itemList[$n]['InvoiceData']['InvoiceInformation'] = (string)$item->InvoiceData->InvoiceInformation;
                }
            }
            if (isset($item->ConditionId)) {
                $this->itemList[$n]['ConditionId'] = (string)$item->ConditionId;
            }
            if (isset($item->ConditionSubtypeId)) {
                $this->itemList[$n]['ConditionSubtypeId'] = (string)$item->ConditionSubtypeId;
            }
            if (isset($item->ConditionNote)) {
                $this->itemList[$n]['ConditionNote'] = (string)$item->ConditionNote;
            }
            if (isset($item->ScheduledDeliveryStartDate)) {
                $this->itemList[$n]['ScheduledDeliveryStartDate'] = (string)$item->ScheduledDeliveryStartDate;
            }
            if (isset($item->ScheduledDeliveryEndDate)) {
                $this->itemList[$n]['ScheduledDeliveryEndDate'] = (string)$item->ScheduledDeliveryEndDate;
            }
            $this->index++;
        }
    }

    /**
     * Returns the order ID for the items.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @return string|boolean single value, or FALSE if not set yet
     */
    public function getOrderId()
    {
        if (isset($this->orderId)) {
            return $this->orderId;
        } else {
            return false;
        }
    }

    /**
     * Returns the specified order item, or all of them.
     *
     * This method will return FALSE if the list has not yet been filled.
     * The array for a single order item will have the following fields:
     *
     *  - ASIN - the ASIN for the item
     *  - SellerSKU - the SKU for the item
     *  - OrderItemId - the unique ID for the order item
     *  - Title - the name of the item
     *  - QuantityOrdered - the quantity of the item ordered
     *  - QuantityShipped (optional) - the quantity of the item shipped
     *  - GiftMessageText (optional) - gift message for the item
     *  - GiftWrapLevel (optional) - the type of gift wrapping for the item
     *  - ItemPrice (optional) - price for the item, array with the fields Amount and CurrencyCode
     *  - ShippingPrice (optional) - price for shipping, array with the fields Amount and CurrencyCode
     *  - GiftWrapPrice (optional) - price for gift wrapping, array with the fields Amount and CurrencyCode
     *  - ItemTax (optional) - tax on the item, array with the fields Amount and CurrencyCode
     *  - ShippingTax (optional) - tax on shipping, array with the fields Amount and CurrencyCode
     *  - GiftWrapTax (optional) - tax on gift wrapping, array with the fields Amount and CurrencyCode
     *  - ShippingDiscount (optional) - discount on shipping, array with the fields Amount and CurrencyCode
     *  - PromotionDiscount (optional) -promotional discount, array with the fields Amount and CurrencyCode
     *  - CODFee (optional) -fee charged for COD service, array with the fields Amount and CurrencyCode
     *  - CODFeeDiscount (optional) -discount on COD fee, array with the fields Amount and CurrencyCode
     *  - PromotionIds (optional) -array of promotion IDs
     *
     * @param int $i [optional] List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.
     * @return array|boolean array, multi-dimensional array, or FALSE if list not filled yet
     */
    public function getItems($i = null)
    {
        if (isset($this->itemList)) {
            if (is_numeric($i)) {
                return $this->itemList[$i];
            } else {
                return $this->itemList;
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the ASIN for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getASIN($i = 0)
    {
        if (isset($this->itemList[$i]['ASIN'])) {
            return $this->itemList[$i]['ASIN'];
        } else {
            return false;
        }
    }

    /**
     * Returns the seller SKU for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getSellerSKU($i = 0)
    {
        if (isset($this->itemList[$i]['SellerSKU'])) {
            return $this->itemList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }

    /**
     * Returns the order item ID for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getOrderItemId($i = 0)
    {
        if (isset($this->itemList[$i]['OrderItemId'])) {
            return $this->itemList[$i]['OrderItemId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the name for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getTitle($i = 0)
    {
        if (isset($this->itemList[$i]['Title'])) {
            return $this->itemList[$i]['Title'];
        } else {
            return false;
        }
    }

    /**
     * Returns the quantity ordered for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getQuantityOrdered($i = 0)
    {
        if (isset($this->itemList[$i]['QuantityOrdered'])) {
            return $this->itemList[$i]['QuantityOrdered'];
        } else {
            return false;
        }
    }

    /**
     * Returns the quantity shipped for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getQuantityShipped($i = 0)
    {
        if (isset($this->itemList[$i]['QuantityShipped'])) {
            return $this->itemList[$i]['QuantityShipped'];
        } else {
            return false;
        }
    }

    /**
     * Returns the URL for the ZIP file containing the customized options for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getCustomizedInfo($i = 0)
    {
        if (isset($this->itemList[$i]['BuyerCustomizedInfo'])) {
            return $this->itemList[$i]['BuyerCustomizedInfo'];
        } else {
            return false;
        }
    }

    /**
     * Returns the number of Amazon Points granted for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields PointsNumber, Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the number of points
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getPointsGranted($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['PointsGranted'])) {
            if ($only) {
                return $this->itemList[$i]['PointsGranted']['PointsNumber'];
            } else {
                return $this->itemList[$i]['PointsGranted'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the price designation for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getPriceDesignation($i = 0)
    {
        if (isset($this->itemList[$i]['PriceDesignation'])) {
            return $this->itemList[$i]['PriceDesignation'];
        } else {
            return false;
        }
    }

    /**
     * Returns the seller SKU for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return float|boolean decimal number from 0 to 1, or FALSE if Non-numeric index
     */
    public function getPercentShipped($i = 0)
    {
        if (!$this->getQuantityOrdered($i) || !$this->getQuantityShipped($i)) {
            return false;
        }
        if (isset($this->itemList[$i]['QuantityOrdered']) && isset($this->itemList[$i]['QuantityShipped'])) {
            return $this->itemList[$i]['QuantityShipped'] / $this->itemList[$i]['QuantityOrdered'];
        } else {
            return false;
        }
    }

    /**
     * Returns the gift message text for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getGiftMessageText($i = 0)
    {
        if (isset($this->itemList[$i]['GiftMessageText'])) {
            return $this->itemList[$i]['GiftMessageText'];
        } else {
            return false;
        }
    }

    /**
     * Returns the gift wrap level for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getGiftWrapLevel($i = 0)
    {
        if (isset($this->itemList[$i]['GiftWrapLevel'])) {
            return $this->itemList[$i]['GiftWrapLevel'];
        } else {
            return false;
        }
    }

    /**
     * Returns the item price for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getItemPrice($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['ItemPrice'])) {
            if ($only) {
                return $this->itemList[$i]['ItemPrice']['Amount'];
            } else {
                return $this->itemList[$i]['ItemPrice'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the shipping price for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getShippingPrice($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['ShippingPrice'])) {
            if ($only) {
                return $this->itemList[$i]['ShippingPrice']['Amount'];
            } else {
                return $this->itemList[$i]['ShippingPrice'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the gift wrap price for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getGiftWrapPrice($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['GiftWrapPrice'])) {
            if ($only) {
                return $this->itemList[$i]['GiftWrapPrice']['Amount'];
            } else {
                return $this->itemList[$i]['GiftWrapPrice'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the item tax for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getItemTax($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['ItemTax'])) {
            if ($only) {
                return $this->itemList[$i]['ItemTax']['Amount'];
            } else {
                return $this->itemList[$i]['ItemTax'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the shipping tax for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getShippingTax($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['ShippingTax'])) {
            if ($only) {
                return $this->itemList[$i]['ShippingTax']['Amount'];
            } else {
                return $this->itemList[$i]['ShippingTax'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the gift wrap tax for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getGiftWrapTax($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['GiftWrapTax'])) {
            if ($only) {
                return $this->itemList[$i]['GiftWrapTax']['Amount'];
            } else {
                return $this->itemList[$i]['GiftWrapTax'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the shipping discount for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getShippingDiscount($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['ShippingDiscount'])) {
            if ($only) {
                return $this->itemList[$i]['ShippingDiscount']['Amount'];
            } else {
                return $this->itemList[$i]['ShippingDiscount'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the promotional discount for the specified entry.
     *
     * This method will return FALSE if the list has not yet been filled.
     * If an array is returned, it will have the fields Amount and CurrencyCode.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param boolean $only [optional] set to TRUE to get only the amount
     * @return array|string|boolean array, single value, or FALSE if Non-numeric index
     */
    public function getPromotionDiscount($i = 0, $only = false)
    {
        if (isset($this->itemList[$i]['PromotionDiscount'])) {
            if ($only) {
                return $this->itemList[$i]['PromotionDiscount']['Amount'];
            } else {
                return $this->itemList[$i]['PromotionDiscount'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns specified promotion ID for the specified item.
     *
     * This method will return the entire list of Promotion IDs if `$j` is not set.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @param int $j [optional] Second list index to retrieve the value from. Defaults to NULL.
     * @return array|string|boolean array, single value, or FALSE if incorrect index
     */
    public function getPromotionIds($i = 0, $j = null)
    {
        if (isset($this->itemList[$i]['PromotionIds'])) {
            if (isset($this->itemList[$i]['PromotionIds'][$j])) {
                return $this->itemList[$i]['PromotionIds'][$j];
            } else {
                return $this->itemList[$i]['PromotionIds'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns invoice data for the specified item.
     *
     * This method will return FALSE if the list has not yet been filled.
     * The array for invoice data may have the following fields:
     *
     *  - InvoiceRequirement - invoice requirement information
     *  - BuyerSelectedInvoiceCategory - invoice category information selected by the buyer
     *  - InvoiceTitle - the title of the invoice as specified by the buyer
     *  - InvoiceInformation - additional invoice information
     *
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return array|boolean array, or FALSE if incorrect index
     */
    public function getInvoiceData($i = 0)
    {
        if (isset($this->itemList[$i]['InvoiceData'])) {
            return $this->itemList[$i]['InvoiceData'];
        } else {
            return false;
        }
    }

    /**
     * Returns the condition for the specified item.
     *
     * This method will return FALSE if the list has not yet been filled.
     * Possible values for the condition ID are...
     *
     *  - New
     *  - Used
     *  - Collectible
     *  - Refurbished
     *  - Preorder
     *  - Club
     *
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if incorrect index
     */
    public function getConditionId($i = 0)
    {
        if (isset($this->itemList[$i]['ConditionId'])) {
            return $this->itemList[$i]['ConditionId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the subcondition for the specified item.
     *
     * This method will return FALSE if the list has not yet been filled.
     * Possible values for the subcondition ID are...
     *
     *  - New
     *  - Mint
     *  - Very Good
     *  - Good
     *  - Acceptable
     *  - Poor
     *  - Club
     *  - OEM
     *  - Warranty
     *  - Refurbished Warranty
     *  - Refurbished
     *  - Open Box
     *  - Any
     *  - Other
     *
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if incorrect index
     */
    public function getConditionSubtypeId($i = 0)
    {
        if (isset($this->itemList[$i]['ConditionSubtypeId'])) {
            return $this->itemList[$i]['ConditionSubtypeId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the condition description for the specified item.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if incorrect index
     */
    public function getConditionNote($i = 0)
    {
        if (isset($this->itemList[$i]['ConditionNote'])) {
            return $this->itemList[$i]['ConditionNote'];
        } else {
            return false;
        }
    }

    /**
     * Returns the earliest date in the scheduled delivery window for the specified item.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if incorrect index
     */
    public function getScheduledDeliveryStartDate($i = 0)
    {
        if (isset($this->itemList[$i]['ScheduledDeliveryStartDate'])) {
            return $this->itemList[$i]['ScheduledDeliveryStartDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the latest date in the scheduled delivery window for the specified item.
     *
     * This method will return FALSE if the list has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if incorrect index
     */
    public function getScheduledDeliveryEndDate($i = 0)
    {
        if (isset($this->itemList[$i]['ScheduledDeliveryEndDate'])) {
            return $this->itemList[$i]['ScheduledDeliveryEndDate'];
        } else {
            return false;
        }
    }

    /**
     * Iterator function
     * @return array        Associative array
     */
    public function current()
    {
        return $this->itemList[$this->i];
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
     * @return bool
     */
    public function valid()
    {
        return isset($this->itemList[$this->i]);
    }
}
