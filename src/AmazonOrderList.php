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
 * Pulls a list of Orders and turn them into an array of AmazonOrder objects.
 *
 * This Amazon Orders Core object can retrieve a list of orders from Amazon
 * and store them in an array of AmazonOrder objects. A number of filters
 * are available to narrow the number of orders returned, but none of them
 * are required. This object can use tokens when retrieving the list.
 */
class AmazonOrderList extends AmazonOrderCore implements \Iterator
{
    /**
     * @var AmazonOrder[]
     */
    protected $orderList;

    /**
     * @var int
     */
    protected $i = 0;

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
    protected $index = 0;

    /**
     * Amazon Order Lists pull a set of Orders and turn them into an array of `AmazonOrder` objects.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param array $config A config array to set.
     * @param boolean $mock [optional] This is a flag for enabling Mock Mode.
     * This defaults to FALSE.
     * @param array|string $m [optional] The files (or file) to use in Mock Mode.
     */
    public function __construct(array $config = null, $mock = false, $m = null)
    {
        parent::__construct($config, $mock, $m);
        include($this->env);
        $this->resetMarketplaceFilter();

        if (isset($THROTTLE_LIMIT_ORDERLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_ORDERLIST;
        }
        if (isset($THROTTLE_TIME_ORDERLIST)) {
            $this->throttleTime = $THROTTLE_TIME_ORDERLIST;
        }
        $this->throttleGroup = 'ListOrders';
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
     * Sets the time frame for the orders fetched. (Optional)
     *
     * Sets the time frame for the orders fetched. If no times are specified, times default to the current time.
     * @param string $mode "Created" or "Modified"
     * @param string $lower [optional] A time string for the earliest time.
     * @param string $upper [optional] A time string for the latest time.
     * @return boolean FALSE if improper input
     */
    public function setLimits($mode, $lower = null, $upper = null)
    {
        try {
            if ($upper) {
                $before = $this->genTime($upper);
            } else {
                $before = $this->genTime('- 2 min');
            }
            if ($lower) {
                $after = $this->genTime($lower);
            } else {
                $after = $this->genTime('- 2 min');
            }
            if ($after > $before) {
                $after = $this->genTime($upper . ' - 150 sec');
            }
            if ($mode == 'Created') {
                $this->options['CreatedAfter'] = $after;
                if ($before) {
                    $this->options['CreatedBefore'] = $before;
                }
                unset($this->options['LastUpdatedAfter']);
                unset($this->options['LastUpdatedBefore']);
            } else if ($mode == 'Modified') {
                $this->options['LastUpdatedAfter'] = $after;
                if ($before) {
                    $this->options['LastUpdatedBefore'] = $before;
                }
                unset($this->options['CreatedAfter']);
                unset($this->options['CreatedBefore']);
            } else {
                $this->log('First parameter should be either "Created" or "Modified".', 'Warning');
                return false;
            }
        } catch (Exception $e) {
            $this->log('Error: ' . $e->getMessage(), 'Warning');
            return false;
        } catch (\InvalidArgumentException $e) {
            $this->log('Error: ' . $e->getMessage(), 'Warning');
            return false;
        }
    }

    /**
     * Sets the order status(es). (Optional)
     *
     * This method sets the list of Order Statuses to be sent in the next request.
     * Setting this parameter tells Amazon to only return Orders with statuses that match
     * those in the list. If this parameter is not set, Amazon will return
     * Orders of any status.
     * @param array|string $list A list of Order Statuses, or a single status string.
     * @return boolean FALSE if improper input
     */
    public function setOrderStatusFilter($list)
    {
        if (is_string($list)) {
            //if single string, set as filter
            $this->resetOrderStatusFilter();
            $this->options['OrderStatus.Status.1'] = $list;
        } else if (is_array($list)) {
            //if array of strings, set all filters
            $this->resetOrderStatusFilter();
            $i = 1;
            foreach ($list as $x) {
                $this->options['OrderStatus.Status.' . $i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Removes order status options.
     *
     * Use this in case you change your mind and want to remove the Order Status
     * parameters you previously set.
     */
    public function resetOrderStatusFilter()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#OrderStatus#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the marketplace(s). (Optional)
     *
     * This method sets the list of Marketplaces to be sent in the next request.
     * Setting this parameter tells Amazon to only return Orders made in marketplaces that match
     * those in the list. If this parameter is not set, Amazon will return
     * Orders belonging to the current store's default marketplace.
     * @param array|string $list A list of Marketplace IDs, or a single Marketplace ID.
     * @return boolean FALSE if improper input
     */
    public function setMarketplaceFilter($list)
    {
        if (is_string($list)) {
            //if single string, set as filter
            $this->resetMarketplaceFilter();
            $this->options['MarketplaceId.Id.1'] = $list;
        } else if (is_array($list)) {
            //if array of strings, set all filters
            $this->resetMarketplaceFilter();
            $i = 1;
            foreach ($list as $x) {
                $this->options['MarketplaceId.Id.' . $i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Removes marketplace ID options and sets the current store's marketplace instead.
     *
     * Use this in case you change your mind and want to remove the Marketplace ID
     * parameters you previously set.
     * @throws Exception if config file is missing
     */
    public function resetMarketplaceFilter()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#MarketplaceId#", $op)) {
                unset($this->options[$op]);
            }
        }

        if (array_key_exists('marketplaceId', $this->config['store'])) {
            $this->options['MarketplaceId.Id.1'] = $this->config['store']['marketplaceId'];
        } else {
            $this->log("Marketplace ID is missing", 'Urgent');
        }
    }

    /**
     * Sets (or resets) the Fulfillment Channel Filter
     * @param string $filter 'AFN' or 'MFN' or NULL
     * @return boolean FALSE on failure
     */
    public function setFulfillmentChannelFilter($filter)
    {
        if ($filter == 'AFN' || $filter == 'MFN') {
            $this->options['FulfillmentChannel.Channel.1'] = $filter;
        } else if (is_null($filter)) {
            unset($this->options['FulfillmentChannel.Channel.1']);
        } else {
            return false;
        }
    }

    /**
     * Sets the payment method(s). (Optional)
     *
     * This method sets the list of Payment Methods to be sent in the next request.
     * Setting this parameter tells Amazon to only return Orders with payment methods
     * that match those in the list. If this parameter is not set, Amazon will return
     * Orders with any payment method.
     * @param array|string $list A list of Payment Methods, or a single method string.
     * @return boolean FALSE if improper input
     */
    public function setPaymentMethodFilter($list)
    {
        if (is_string($list)) {
            //if single string, set as filter
            $this->resetPaymentMethodFilter();
            $this->options['PaymentMethod.1'] = $list;
        } else if (is_array($list)) {
            //if array of strings, set all filters
            $this->resetPaymentMethodFilter();
            $i = 1;
            foreach ($list as $x) {
                $this->options['PaymentMethod.' . $i++] = $x;
            }
        } else {
            return false;
        }
    }

    /**
     * Removes payment method options.
     *
     * Use this in case you change your mind and want to remove the Payment Method
     * parameters you previously set.
     */
    public function resetPaymentMethodFilter()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#PaymentMethod#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets (or resets) the email address. (Optional)
     *
     * This method sets the email address to be sent in the next request.
     * Setting this parameter tells Amazon to only return Orders with email addresses
     * that match the email address given. If this parameter is set, the following options
     * will be removed: SellerOrderId, OrderStatus, PaymentMethod, FulfillmentChannel, LastUpdatedAfter, LastUpdatedBefore.
     * @param string $filter A single email address string. Set to NULL to remove the option.
     * @return boolean FALSE if improper input
     */
    public function setEmailFilter($filter)
    {
        if (is_string($filter)) {
            $this->options['BuyerEmail'] = $filter;
            //these fields must be disabled
            unset($this->options['SellerOrderId']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else if (is_null($filter)) {
            unset($this->options['BuyerEmail']);
        } else {
            return false;
        }
    }

    /**
     * Sets (or resets) the seller order ID(s). (Optional)
     *
     * This method sets the list of seller order ID to be sent in the next request.
     * Setting this parameter tells Amazon to only return Orders with seller order IDs
     * that match the seller order ID given. If this parameter is set, the following options
     * will be removed: BuyerEmail, OrderStatus, PaymentMethod, FulfillmentChannel, LastUpdatedAfter, LastUpdatedBefore.
     * @param array|string $filter A single seller order ID. Set to NULL to remove the option.
     * @return boolean FALSE if improper input
     */
    public function setSellerOrderIdFilter($filter)
    {
        if (is_string($filter)) {
            $this->options['SellerOrderId'] = $filter;
            //these fields must be disabled
            unset($this->options['BuyerEmail']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else if (is_null($filter)) {
            unset($this->options['SellerOrderId']);
        } else {
            return false;
        }
    }

    /**
     * Sets the maximum response per page count. (Optional)
     *
     * This method sets the maximum number of Feed Submissions for Amazon to return per page.
     * If this parameter is not set, Amazon will send 100 at a time.
     * @param array|string $num Positive integer from 1 to 100.
     * @return boolean FALSE if improper input
     */
    public function setMaxResultsPerPage($num)
    {
        if (is_int($num) && $num <= 100 && $num >= 1) {
            $this->options['MaxResultsPerPage'] = $num;
        } else {
            return false;
        }
    }

    /**
     * Sets the TFM shipment status(es). (Optional)
     *
     * This method sets the list of TFM Shipment Statuses to be sent in the next request.
     * Setting this parameter tells Amazon to only return TFM Orders with statuses that match
     * those in the list. If this parameter is not set, Amazon will return
     * Orders of any status, including non-TFM orders.
     * @param array|string $list A list of TFM Shipment Statuses, or a single status string.
     * @return boolean FALSE if improper input
     */
    public function setTfmShipmentStatusFilter($list)
    {
        if (is_string($list)) {
            //if single string, set as filter
            $this->resetTfmShipmentStatusFilter();
            $this->options['TFMShipmentStatus.Status.1'] = $list;
        } else if (is_array($list)) {
            //if array of strings, set all filters
            $this->resetTfmShipmentStatusFilter();
            $i = 1;
            foreach ($list as $x) {
                $this->options['TFMShipmentStatus.Status.' . $i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Removes order status options.
     *
     * Use this in case you change your mind and want to remove the TFM Shipment Status
     * parameters you previously set.
     */
    public function resetTfmShipmentStatusFilter()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#TFMShipmentStatus#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Fetches orders from Amazon and puts them in an array of `AmazonOrder` objects.
     *
     * Submits a `ListOrders` request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using `getList()`.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] When set to FALSE, the function will not recurse, defaults to TRUE
     * @return boolean FALSE if something goes wrong
     */
    public function fetchOrders($r = true)
    {
        if (!array_key_exists('CreatedAfter', $this->options) && !array_key_exists('LastUpdatedAfter', $this->options)) {
            $this->setLimits('Created');
        }

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

        $this->parseXML($xml);

        $this->checkToken($xml);

        if ($this->tokenFlag && $this->tokenUseFlag && $r === true) {
            while ($this->tokenFlag) {
                $this->log("Recursively fetching more orders");
                $this->fetchOrders(false);
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
            $this->options['Action'] = 'ListOrdersByNextToken';

            //When using tokens, only the NextToken option should be used
            unset($this->options['SellerOrderId']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            $this->setSellerOrderIdFilter(null);
            $this->setEmailFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
            unset($this->options['CreatedAfter']);
            unset($this->options['CreatedBefore']);
            unset($this->options['MaxResultsPerPage']);
        } else {
            $this->options['Action'] = 'ListOrders';
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->orderList = array();
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

        foreach ($xml->Orders->children() as $key => $data) {
            if ($key != 'Order') {
                break;
            }
            $this->orderList[$this->index] = new AmazonOrder($this->config, null, $data, $this->mockMode, $this->mockFiles);
            $this->orderList[$this->index]->setLogPath($this->logpath);
            $this->orderList[$this->index]->mockIndex = $this->mockIndex;
            $this->index++;
        }
    }

    /**
     * Clears previously fetched orders list. May be used with NextToken feature without autoload all pages.
     * This allow to fetch large amount of orders with small memory usage. After each page loaded and parsed,
     * previously loaded orders should be removed from memory by this method.
     */
    public function clearOrderList()
    {
        $this->orderList = [];
        $this->index = 0;
        $this->i = 0;
    }

    /**
     * Returns array of item lists or a single item list.
     *
     * If `$i` is not specified, the method will fetch the items for every
     * order in the list. Please note that for lists with a high number of orders,
     * this operation could take a while due to throttling. (Two seconds per order when throttled.)
     * @param boolean $token [optional] whether or not to automatically use tokens when fetching items.
     * @param int $i [optional] List index to retrieve the value from. Defaults to null.
     * @return AmazonOrderItemList|AmazonOrderItemList[]|bool `AmazonOrderItemList` object or array of objects, or FALSE if non-numeric index
     */
    public function fetchItems($token = false, $i = null)
    {
        if (!isset($this->orderList)) {
            return false;
        }
        if (!is_bool($token)) {
            $token = false;
        }
        if (is_int($i)) {
            return $this->orderList[$i]->fetchItems($token);
        } else {
            $a = array();
            foreach ($this->orderList as $x) {
                $a[] = $x->fetchItems($token);
            }
            return $a;
        }
    }

    /**
     * Returns the list of orders.
     * @return AmazonOrder[]|boolean array of `AmazonOrder` objects, or FALSE if list not filled yet
     */
    public function getList()
    {
        if (isset($this->orderList)) {
            return $this->orderList;
        } else {
            return false;
        }
    }

    /**
     * Iterator function
     * @return AmazonOrder
     */
    public function current()
    {
        return $this->orderList[$this->i];
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
        return isset($this->orderList[$this->i]);
    }
}
