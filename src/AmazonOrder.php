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
 * Gets the details for a single order from Amazon.
 *
 * This Amazon Order Core object retrieves (or simply contains) the data
 * for a single order on Amazon. In order to fetch this data, an Amazon
 * Order ID is required.
 */
class AmazonOrder extends AmazonOrderCore
{
    /**
     * Amazon order data
     *
     * @var array       Associative array
     */
    protected $data;

    /**
     * AmazonOrder object gets the details for a single object from Amazon.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that two extra parameters come before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param array $config A config array to set.
     * @param string $id [optional] The Order ID to set for the object.
     * @param \SimpleXMLElement $data [optional] XML data from Amazon to be parsed.
     * @param boolean $mock [optional] This is a flag for enabling Mock Mode.
     * This defaults to FALSE.
     * @param array|string $m [optional] The files (or file) to use in Mock Mode.
     */
    public function __construct(array $config, $id = null, $data = null, $mock = false, $m = null)
    {
        parent::__construct($config, $mock, $m);
        include($this->env);

        if ($id) {
            $this->setOrderId($id);
        }
        if ($data) {
            $this->parseXML($data);
        }

        $this->options['Action'] = 'GetOrder';

        if (isset($THROTTLE_LIMIT_ORDER)) {
            $this->throttleLimit = $THROTTLE_LIMIT_ORDER;
        }
        if (isset($THROTTLE_TIME_ORDER)) {
            $this->throttleTime = $THROTTLE_TIME_ORDER;
        }
        $this->throttleGroup = 'GetOrder';
    }

    /**
     * Sets the Amazon Order ID. (Required)
     *
     * This method sets the Amazon Order ID to be sent in the next request.
     * This parameter is required for fetching the order from Amazon.
     * @param string $id either string or number
     * @return boolean FALSE if improper input
     */
    public function setOrderId($id)
    {
        if (is_string($id) || is_numeric($id)) {
            $this->options['AmazonOrderId.Id.1'] = $id;
        } else {
            $this->log("Attempted to set AmazonOrderId to invalid value", 'Warning');
            return false;
        }
    }

    /**
     * Fetches the specified order from Amazon.
     *
     * Submits a `GetOrder` request to Amazon. In order to do this,
     * an Amazon order ID is required. Amazon will send
     * the data back as a response, which can be retrieved using `getData()`.
     * Other methods are available for fetching specific values from the order.
     * @return boolean FALSE if something goes wrong
     */
    public function fetchOrder()
    {
        if (!array_key_exists('AmazonOrderId.Id.1', $this->options)) {
            $this->log("Order ID must be set in order to fetch it!", 'Warning');
            return false;
        }

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        if ($this->mockMode) {
            $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)) {
                return false;
            }

            $xml = simplexml_load_string($response['body']);
        }

        $this->parseXML($xml->GetOrderResult->Orders->Order);
    }

    /**
     * Fetches items for the order from Amazon.
     *
     * See the `AmazonOrderItemList` class for more information on the returned object.
     * @param boolean $token [optional] whether or not to automatically use item tokens in the request
     * @return AmazonOrderItemList container for order's items
     */
    public function fetchItems($token = false)
    {
        if (!isset($this->data['AmazonOrderId'])) {
            return false;
        }
        if (!is_bool($token)) {
            $token = false;
        }
        $items = new AmazonOrderItemList($this->config, $this->data['AmazonOrderId'], $this->mockMode, $this->mockFiles);
        $items->setLogPath($this->logpath);
        $items->mockIndex = $this->mockIndex;
        $items->setUseToken($token);
        $items->fetchItems();
        return $items;
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
        $d = array();
        $d['AmazonOrderId'] = (string)$xml->AmazonOrderId;
        if (isset($xml->SellerOrderId)) {
            $d['SellerOrderId'] = (string)$xml->SellerOrderId;
        }
        $d['PurchaseDate'] = (string)$xml->PurchaseDate;
        $d['LastUpdateDate'] = (string)$xml->LastUpdateDate;
        $d['OrderStatus'] = (string)$xml->OrderStatus;
        if (isset($xml->FulfillmentChannel)) {
            $d['FulfillmentChannel'] = (string)$xml->FulfillmentChannel;
        }
        if (isset($xml->SalesChannel)) {
            $d['SalesChannel'] = (string)$xml->SalesChannel;
        }
        if (isset($xml->OrderChannel)) {
            $d['OrderChannel'] = (string)$xml->OrderChannel;
        }
        if (isset($xml->ShipServiceLevel)) {
            $d['ShipServiceLevel'] = (string)$xml->ShipServiceLevel;
        }
        if (isset($xml->ShippingAddress)) {
            $d['ShippingAddress'] = array();
            $d['ShippingAddress']['Name'] = (string)$xml->ShippingAddress->Name;
            $d['ShippingAddress']['AddressLine1'] = (string)$xml->ShippingAddress->AddressLine1;
            $d['ShippingAddress']['AddressLine2'] = (string)$xml->ShippingAddress->AddressLine2;
            $d['ShippingAddress']['AddressLine3'] = (string)$xml->ShippingAddress->AddressLine3;
            $d['ShippingAddress']['City'] = (string)$xml->ShippingAddress->City;
            $d['ShippingAddress']['County'] = (string)$xml->ShippingAddress->County;
            $d['ShippingAddress']['District'] = (string)$xml->ShippingAddress->District;
            $d['ShippingAddress']['StateOrRegion'] = (string)$xml->ShippingAddress->StateOrRegion;
            $d['ShippingAddress']['PostalCode'] = (string)$xml->ShippingAddress->PostalCode;
            $d['ShippingAddress']['CountryCode'] = (string)$xml->ShippingAddress->CountryCode;
            $d['ShippingAddress']['Phone'] = (string)$xml->ShippingAddress->Phone;
        }
        if (isset($xml->OrderTotal)) {
            $d['OrderTotal'] = array();
            $d['OrderTotal']['Amount'] = (string)$xml->OrderTotal->Amount;
            $d['OrderTotal']['CurrencyCode'] = (string)$xml->OrderTotal->CurrencyCode;
        }
        if (isset($xml->NumberOfItemsShipped)) {
            $d['NumberOfItemsShipped'] = (string)$xml->NumberOfItemsShipped;
        }
        if (isset($xml->NumberOfItemsUnshipped)) {
            $d['NumberOfItemsUnshipped'] = (string)$xml->NumberOfItemsUnshipped;
        }
        if (isset($xml->PaymentExecutionDetail)) {
            $d['PaymentExecutionDetail'] = array();

            $i = 0;
            foreach ($xml->PaymentExecutionDetail->children() as $x) {
                $d['PaymentExecutionDetail'][$i]['Amount'] = (string)$x->Payment->Amount;
                $d['PaymentExecutionDetail'][$i]['CurrencyCode'] = (string)$x->Payment->CurrencyCode;
                $d['PaymentExecutionDetail'][$i]['SubPaymentMethod'] = (string)$x->SubPaymentMethod;
                $i++;
            }
        }
        if (isset($xml->PaymentMethod)) {
            $d['PaymentMethod'] = (string)$xml->PaymentMethod;
        }
        if (isset($xml->PaymentMethodDetails)) {
            foreach ($xml->PaymentMethodDetails as $x) {
                $d['PaymentMethodDetails'][] = (string)$x->PaymentMethodDetail;
            }
        }
        if (isset($xml->IsReplacementOrder)) {
            $d['IsReplacementOrder'] = (string)$xml->IsReplacementOrder;
            $d['ReplacedOrderId'] = (string)$xml->ReplacedOrderId;
        }
        $d['MarketplaceId'] = (string)$xml->MarketplaceId;
        if (isset($xml->BuyerName)) {
            $d['BuyerName'] = (string)$xml->BuyerName;
        }
        if (isset($xml->BuyerEmail)) {
            $d['BuyerEmail'] = (string)$xml->BuyerEmail;
        }
        if (isset($xml->BuyerCounty)) {
            $d['BuyerCounty'] = (string)$xml->BuyerCounty;
        }
        if (isset($xml->BuyerTaxInfo)) {
            $d['BuyerTaxInfo'] = array();
            if (isset($xml->BuyerTaxInfo->CompanyLegalName)) {
                $d['BuyerTaxInfo']['CompanyLegalName'] = (string)$xml->BuyerTaxInfo->CompanyLegalName;
            }
            if (isset($xml->BuyerTaxInfo->TaxingRegion)) {
                $d['BuyerTaxInfo']['TaxingRegion'] = (string)$xml->BuyerTaxInfo->TaxingRegion;
            }
            if (isset($xml->BuyerTaxInfo->TaxClassifications)) {
                foreach ($xml->BuyerTaxInfo->TaxClassifications->children() as $x) {
                    $temp = array();
                    $temp['Name'] = (string)$x->Name;
                    $temp['Value'] = (string)$x->Value;
                    $d['BuyerTaxInfo']['TaxClassifications'][] = $temp;
                }
            }
        }
        if (isset($xml->ShipmentServiceLevelCategory)) {
            $d['ShipmentServiceLevelCategory'] = (string)$xml->ShipmentServiceLevelCategory;
        }
        if (isset($xml->CbaDisplayableShippingLabel)) {
            $d['CbaDisplayableShippingLabel'] = (string)$xml->CbaDisplayableShippingLabel;
        }
        if (isset($xml->ShippedByAmazonTFM)) {
            $d['ShippedByAmazonTFM'] = (string)$xml->ShippedByAmazonTFM;
        }
        if (isset($xml->TFMShipmentStatus)) {
            $d['TFMShipmentStatus'] = (string)$xml->TFMShipmentStatus;
        }
        if (isset($xml->OrderType)) {
            $d['OrderType'] = (string)$xml->OrderType;
        }
        if (isset($xml->EarliestShipDate)) {
            $d['EarliestShipDate'] = (string)$xml->EarliestShipDate;
        }
        if (isset($xml->LatestShipDate)) {
            $d['LatestShipDate'] = (string)$xml->LatestShipDate;
        }
        if (isset($xml->EarliestDeliveryDate)) {
            $d['EarliestDeliveryDate'] = (string)$xml->EarliestDeliveryDate;
        }
        if (isset($xml->LatestDeliveryDate)) {
            $d['LatestDeliveryDate'] = (string)$xml->LatestDeliveryDate;
        }
        if (isset($xml->IsBusinessOrder)) {
            $d['IsBusinessOrder'] = (string)$xml->IsBusinessOrder;
        }
        if (isset($xml->PurchaseOrderNumber)) {
            $d['PurchaseOrderNumber'] = (string)$xml->PurchaseOrderNumber;
        }
        if (isset($xml->IsPrime)) {
            $d['IsPrime'] = (string)$xml->IsPrime;
        }
        if (isset($xml->IsPremiumOrder)) {
            $d['IsPremiumOrder'] = (string)$xml->IsPremiumOrder;
        }

        $this->data = $d;
    }

    /**
     * Returns the full set of data for the order.
     *
     * This method will return FALSE if the order data has not yet been filled.
     * The array returned will have the following fields:
     *
     *  - AmazonOrderId - unique ID for the order, which you sent in the first place
     *  - SellerOrderId (optional) - your unique ID for the order
     *  - PurchaseDate - time in ISO8601 date format
     *  - LastUpdateDate - time in ISO8601 date format
     *  - OrderStatus - the current status of the order, see `getOrderStatus()` for more details
     *  - MarketplaceId - the marketplace in which the order was placed
     *  - FulfillmentChannel (optional) - "AFN" or "MFN"
     *  - SalesChannel (optional) - sales channel for the first item in the order
     *  - OrderChannel (optional) - order channel for the first item in the order
     *  - ShipServiceLevel (optional) - shipment service level of the order
     *  - ShippingAddress (optional) - array, see `getShippingAddress()` for more details
     *  - OrderTotal (optional) - array with the fields Amount and CurrencyCode
     *  - NumberOfItemsShipped (optional) - number of items shipped
     *  - NumberOfItemsUnshipped (optional) - number of items not shipped
     *  - PaymentExecutionDetail (optional) - multi-dimensional array, see `getPaymentExecutionDetail()` for more details
     *  - PaymentMethod (optional) - "COD", "CVS", or "Other"
     *  - PaymentMethodDetails (optional) - array of payment detail strings
     *  - IsReplacementOrder (optional) - "true" or "false"
     *  - ReplacedOrderId (optional) - Amazon Order ID, only given if `IsReplacementOrder` is true
     *  - MarketplaceId (optional) - marketplace for the order
     *  - BuyerName (optional) - name of the buyer
     *  - BuyerEmail (optional) - Amazon-generated email for the buyer
     *  - BuyerCounty (optional) - county for the buyer
     *  - BuyerTaxInfo (optional) - tax information about the buyer, see `getBuyerTaxInfo()` for more details
     *  - ShipmentServiceLevelCategory (optional) - "Expedited", "FreeEconomy", "NextDay",
     * "SameDay", "SecondDay", "Scheduled", or "Standard"
     *  - ShippedByAmazonTFM (optional) - "true" or "false"
     *  - TFMShipmentStatus (optional) - the status of the TFM shipment, see `getTfmShipmentStatus()` for more details
     *  - CbaDisplayableShippingLabel (optional) - customized Checkout by Amazon label of the order
     *  - OrderType (optional) - "StandardOrder" or "Preorder"
     *  - EarliestShipDate (optional) - time in ISO8601 date format
     *  - LatestShipDate (optional) - time in ISO8601 date format
     *  - EarliestDeliveryDate (optional) - time in ISO8601 date format
     *  - LatestDeliveryDate (optional) - time in ISO8601 date format
     *  - IsBusinessOrder (optional) - "true" or "false"
     *  - PurchaseOrderNumber (optional) - the Purchase Order number entered by the buyer
     *  - IsPrime (optional) - "true" or "false"
     *  - IsPremiumOrder (optional) - "true" or "false"
     *
     * @return array|boolean array of data, or FALSE if data not filled yet
     */
    public function getData()
    {
        if (isset($this->data) && $this->data) {
            return $this->data;
        } else {
            return false;
        }
    }

    /**
     * Returns the Amazon Order ID for the Order.
     *
     * This method will return FALSE if the order ID has not been set yet.
     * @return string|boolean single value, or FALSE if order ID not set yet
     */
    public function getAmazonOrderId()
    {
        if (isset($this->data['AmazonOrderId'])) {
            return $this->data['AmazonOrderId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the seller-defined ID for the Order.
     *
     * This method will return FALSE if the order ID has not been set yet.
     * @return string|boolean single value, or FALSE if order ID not set yet
     */
    public function getSellerOrderId()
    {
        if (isset($this->data['SellerOrderId'])) {
            return $this->data['SellerOrderId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the purchase date of the Order.
     *
     * This method will return FALSE if the timestamp has not been set yet.
     * @return string|boolean timestamp, or FALSE if timestamp not set yet
     */
    public function getPurchaseDate()
    {
        if (isset($this->data['PurchaseDate'])) {
            return $this->data['PurchaseDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the timestamp of the last modification date.
     *
     * This method will return FALSE if the timestamp has not been set yet.
     * @return string|boolean timestamp, or FALSE if timestamp not set yet
     */
    public function getLastUpdateDate()
    {
        if (isset($this->data['LastUpdateDate'])) {
            return $this->data['LastUpdateDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the status of the Order.
     *
     * This method will return FALSE if the order status has not been set yet.
     * Possible Order Statuses are:
     *
     *  - Pending
     *  - Unshipped
     *  - Partially Shipped
     *  - Shipped
     *  - Cancelled
     *  - Unfulfillable
     *
     * @return string|boolean single value, or FALSE if status not set yet
     */
    public function getOrderStatus()
    {
        if (isset($this->data['OrderStatus'])) {
            return $this->data['OrderStatus'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Fulfillment Channel.
     *
     * This method will return FALSE if the fulfillment channel has not been set yet.
     * @return string|boolean "AFN" or "MFN", or FALSE if channel not set yet
     */
    public function getFulfillmentChannel()
    {
        if (isset($this->data['FulfillmentChannel'])) {
            return $this->data['FulfillmentChannel'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Sales Channel of the Order.
     *
     * This method will return FALSE if the sales channel has not been set yet.
     * @return string|boolean single value, or FALSE if channel not set yet
     */
    public function getSalesChannel()
    {
        if (isset($this->data['SalesChannel'])) {
            return $this->data['SalesChannel'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Order Channel of the first item in the Order.
     *
     * This method will return FALSE if the order channel has not been set yet.
     * @return string|boolean single value, or FALSE if channel not set yet
     */
    public function getOrderChannel()
    {
        if (isset($this->data['OrderChannel'])) {
            return $this->data['OrderChannel'];
        } else {
            return false;
        }
    }

    /**
     * Returns the shipment service level of the Order.
     *
     * This method will return FALSE if the shipment service level has not been set yet.
     * @return string|boolean single value, or FALSE if level not set yet
     */
    public function getShipServiceLevel()
    {
        if (isset($this->data['ShipServiceLevel'])) {
            return $this->data['ShipServiceLevel'];
        } else {
            return false;
        }
    }

    /**
     * Returns an array containing all of the address information.
     *
     * This method will return FALSE if the address has not been set yet.
     * The returned array will have the following fields:
     *
     *  - Name
     *  - AddressLine1
     *  - AddressLine2
     *  - AddressLine3
     *  - City
     *  - County
     *  - District
     *  - StateOrRegion
     *  - PostalCode
     *  - CountryCode
     *  - Phone
     *
     * @return array|boolean associative array, or FALSE if address not set yet
     */
    public function getShippingAddress()
    {
        if (isset($this->data['ShippingAddress'])) {
            return $this->data['ShippingAddress'];
        } else {
            return false;
        }
    }

    /**
     * Returns an array containing the total cost of the Order along with the currency used.
     *
     * This method will return FALSE if the order total has not been set yet.
     * The returned array has the following fields:
     *
     *  - Amount
     *  - CurrencyCode
     *
     * @return array|boolean associative array, or FALSE if total not set yet
     */
    public function getOrderTotal()
    {
        if (isset($this->data['OrderTotal'])) {
            return $this->data['OrderTotal'];
        } else {
            return false;
        }
    }

    /**
     * Returns just the total cost of the Order.
     *
     * This method will return FALSE if the order total has not been set yet.
     * @return string|boolean number, or FALSE if total not set yet
     */
    public function getOrderTotalAmount()
    {
        if (isset($this->data['OrderTotal']) && isset($this->data['OrderTotal']['Amount'])) {
            return $this->data['OrderTotal']['Amount'];
        } else {
            return false;
        }
    }

    /**
     * Returns the number of items in the Order that have been shipped.
     *
     * This method will return FALSE if the number has not been set yet.
     * @return integer|boolean non-negative number, or FALSE if number not set yet
     */
    public function getNumberofItemsShipped()
    {
        if (isset($this->data['NumberOfItemsShipped'])) {
            return $this->data['NumberOfItemsShipped'];
        } else {
            return false;
        }
    }

    /**
     * Returns the number of items in the Order that have yet to be shipped.
     *
     * This method will return FALSE if the number has not been set yet.
     * @return integer|boolean non-negative number, or FALSE if number not set yet
     */
    public function getNumberOfItemsUnshipped()
    {
        if (isset($this->data['NumberOfItemsUnshipped'])) {
            return $this->data['NumberOfItemsUnshipped'];
        } else {
            return false;
        }
    }

    /**
     * Returns an array of the complete payment details.
     *
     * This method will return FALSE if the payment details has not been set yet.
     * The array returned contains one or more arrays with the following fields:
     *
     *  - Amount
     *  - CurrencyCode
     *  - SubPaymentMethod
     *
     * @return array|boolean multi-dimensional array, or FALSE if details not set yet
     */
    public function getPaymentExecutionDetail()
    {
        if (isset($this->data['PaymentExecutionDetail'])) {
            return $this->data['PaymentExecutionDetail'];
        } else {
            return false;
        }
    }

    /**
     * Returns the payment method of the Order.
     *
     * This method will return FALSE if the payment method has not been set yet.
     * @return string|boolean "COD", "CVS", "Other", or FALSE if method not set yet
     */
    public function getPaymentMethod()
    {
        if (isset($this->data['PaymentMethod'])) {
            return $this->data['PaymentMethod'];
        } else {
            return false;
        }
    }

    /**
     * Returns the payment method details of the Order.
     *
     * This method will return FALSE if the payment method details have not been set yet.
     * @return array|boolean array of detail strings, or FALSE if value not set yet
     */
    public function getPaymentMethodDetails()
    {
        if (isset($this->data['PaymentMethodDetails'])) {
            return $this->data['PaymentMethodDetails'];
        } else {
            return false;
        }
    }

    /**
     * Returns an indication of whether or not the Order is a Replacement Order.
     *
     * This method will return FALSE if the replacement order flag has not been set yet.
     * @return string|boolean "true" or "false", or FALSE if value not set yet
     */
    public function getIsReplacementOrder()
    {
        if (isset($this->data['IsReplacementOrder'])) {
            return $this->data['IsReplacementOrder'];
        } else {
            return false;
        }
    }

    /**
     * Returns the ID of the Order that this Order replaces.
     *
     * This method will return FALSE if the replaced order ID has not been set yet.
     * @return string|boolean single value, or FALSE if ID not set yet
     */
    public function getReplacedOrderId()
    {
        if (isset($this->data['ReplacedOrderId'])) {
            return $this->data['ReplacedOrderId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the ID of the Marketplace in which the Order was placed.
     *
     * This method will return FALSE if the marketplace ID has not been set yet.
     * @return string|boolean single value, or FALSE if ID not set yet
     */
    public function getMarketplaceId()
    {
        if (isset($this->data['MarketplaceId'])) {
            return $this->data['MarketplaceId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the name of the buyer.
     *
     * This method will return FALSE if the buyer name has not been set yet.
     * @return string|boolean single value, or FALSE if name not set yet
     */
    public function getBuyerName()
    {
        if (isset($this->data['BuyerName'])) {
            return $this->data['BuyerName'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Amazon-generated email address of the buyer.
     *
     * This method will return FALSE if the buyer email has not been set yet.
     * @return string|boolean single value, or FALSE if email not set yet
     */
    public function getBuyerEmail()
    {
        if (isset($this->data['BuyerEmail'])) {
            return $this->data['BuyerEmail'];
        } else {
            return false;
        }
    }

    /**
     * Returns the county of the buyer.
     *
     * This method will return FALSE if the buyer county has not been set yet.
     * @return string|boolean single value, or FALSE if county not set yet
     */
    public function getBuyerCounty()
    {
        if (isset($this->data['BuyerCounty'])) {
            return $this->data['BuyerCounty'];
        } else {
            return false;
        }
    }

    /**
     * Returns additional tax information about the buyer.
     *
     * This method will return FALSE if the tax info has not been set yet.
     * The returned array has the following fields:
     *
     *  - CompanyLegalName
     *  - TaxingRegion
     *  - TaxClassifications - array of arrays, each with the following keys:
     *
     *  - Name
     *  - Value
     *
     *
     * @return array|boolean associative array, or FALSE if info not set yet
     */
    public function getBuyerTaxInfo()
    {
        if (isset($this->data['BuyerTaxInfo'])) {
            return $this->data['BuyerTaxInfo'];
        } else {
            return false;
        }
    }

    /**
     * Returns the shipment service level category of the Order.
     *
     * This method will return FALSE if the service level category has not been set yet.
     * Valid values for the service level category are...
     *
     *  - Expedited
     *  - FreeEconomy
     *  - NextDay
     *  - SameDay
     *  - SecondDay
     *  - Scheduled
     *  - Standard
     *
     * @return string|boolean single value, or FALSE if category not set yet
     */
    public function getShipmentServiceLevelCategory()
    {
        if (isset($this->data['ShipmentServiceLevelCategory'])) {
            return $this->data['ShipmentServiceLevelCategory'];
        } else {
            return false;
        }
    }

    /**
     * Use getShipmentServiceLevelCategory instead.
     * @deprecated since version 1.3.0
     * @return string|boolean single value, or FALSE if category not set yet
     */
    public function getShipServiceLevelCategory()
    {
        return $this->getShipmentServiceLevelCategory();
    }

    /**
     * Returns the customized Checkout by Amazon (CBA) label of the Order.
     *
     * This method will return FALSE if the CBA label category has not been set yet.
     * @return string|boolean single value, or FALSE if label not set yet
     */
    public function getCbaDisplayableShippingLabel()
    {
        if (isset($this->data['CbaDisplayableShippingLabel'])) {
            return $this->data['CbaDisplayableShippingLabel'];
        } else {
            return false;
        }
    }

    /**
     * Returns an indication of whether or not the Order was shipped with the Amazon TFM service.
     *
     * This method will return FALSE if the Amazon TFM flag has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getShippedByAmazonTfm()
    {
        if (isset($this->data['ShippedByAmazonTFM'])) {
            return $this->data['ShippedByAmazonTFM'];
        } else {
            return false;
        }
    }

    /**
     * Returns the status of an Order shipped using Amazon TFM.
     *
     * This method will return FALSE if the status has not been set yet.
     * Valid values for the status are...
     *
     *  - PendingPickUp
     *  - LabelCanceled
     *  - PickedUp
     *  - AtDestinationFC
     *  - Delivered
     *  - RejectedByBuyer
     *  - Undeliverable
     *  - ReturnedToSeller
     *
     * @return string|boolean single value, or FALSE if status not set yet
     */
    public function getTfmShipmentStatus()
    {
        if (isset($this->data['TFMShipmentStatus'])) {
            return $this->data['TFMShipmentStatus'];
        } else {
            return false;
        }
    }

    /**
     * Returns the type of the order.
     *
     * This method will return FALSE if the type has not been set yet.
     * Valid values for the type are...
     *
     *  - StandardOrder
     *  - Preorder
     *
     * @return string|boolean single value, or FALSE if order type not set yet
     */
    public function getOrderType()
    {
        if (isset($this->data['OrderType'])) {
            return $this->data['OrderType'];
        } else {
            return false;
        }
    }

    /**
     * Returns the timestamp of the earliest shipping date.
     *
     * This method will return FALSE if the timestamp has not been set yet.
     * @return string|boolean timestamp, or FALSE if timestamp not set yet
     */
    public function getEarliestShipDate()
    {
        if (isset($this->data['EarliestShipDate'])) {
            return $this->data['EarliestShipDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the timestamp of the latest shipping date.
     *
     * Note that this could be set to midnight of the day after the last date,
     * so the timestamp "2013-09-025T00:00:00Z" indicates the last day is the 24th and not the 25th.
     * This method will return FALSE if the timestamp has not been set yet.
     * @return string|boolean timestamp, or FALSE if timestamp not set yet
     */
    public function getLatestShipDate()
    {
        if (isset($this->data['LatestShipDate'])) {
            return $this->data['LatestShipDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the timestamp of the estimated earliest delivery date.
     *
     * This method will return FALSE if the timestamp has not been set yet.
     * @return string|boolean timestamp, or FALSE if timestamp not set yet
     */
    public function getEarliestDeliveryDate()
    {
        if (isset($this->data['EarliestDeliveryDate'])) {
            return $this->data['EarliestDeliveryDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the timestamp of the estimated latest delivery date.
     *
     * Note that this could be set to midnight of the day after the last date,
     * so the timestamp "2013-09-025T00:00:00Z" indicates the last day is the 24th and not the 25th.
     * This method will return FALSE if the timestamp has not been set yet.
     * @return string|boolean timestamp, or FALSE if timestamp not set yet
     */
    public function getLatestDeliveryDate()
    {
        if (isset($this->data['LatestDeliveryDate'])) {
            return $this->data['LatestDeliveryDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the ratio of shipped items to unshipped items.
     *
     * This method will return FALSE if the shipment numbers have not been set yet.
     * @return float|boolean Decimal number from 0 to 1, or FALSE if numbers not set yet
     */
    public function getPercentShipped()
    {
        if (isset($this->data['NumberOfItemsShipped']) && isset($this->data['NumberOfItemsUnshipped'])) {
            $total = $this->data['NumberOfItemsShipped'] + $this->data['NumberOfItemsUnshipped'];

            if ($total == 0) {
                return 0;
            }

            $ratio = $this->data['NumberOfItemsShipped'] / $total;
            return $ratio;
        } else {
            return false;
        }
    }

    /**
     * Returns an indication of whether or not the Order is a business number.
     *
     * This method will return FALSE if the business order flag has not been set yet.
     * @return string|boolean "true" or "false", or FALSE if value not set yet
     */
    public function getIsBusinessOrder()
    {
        if (isset($this->data['IsBusinessOrder'])) {
            return $this->data['IsBusinessOrder'];
        } else {
            return false;
        }
    }

    /**
     * Returns the purchase order number associated with the order.
     *
     * This method will return FALSE if the purchase order number has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getPurchaseOrderNumber()
    {
        if (isset($this->data['PurchaseOrderNumber'])) {
            return $this->data['PurchaseOrderNumber'];
        } else {
            return false;
        }
    }

    /**
     * Returns an indication of whether or not the Order uses the Amazon Prime service.
     *
     * This method will return FALSE if the Prime flag has not been set yet.
     * @return string|boolean "true" or "false", or FALSE if value not set yet
     */
    public function getIsPrime()
    {
        if (isset($this->data['IsPrime'])) {
            return $this->data['IsPrime'];
        } else {
            return false;
        }
    }

    /**
     * Returns an indication of whether or not the Order is a premium order.
     *
     * This method will return FALSE if the premium order flag has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getIsPremiumOrder()
    {
        if (isset($this->data['IsPremiumOrder'])) {
            return $this->data['IsPremiumOrder'];
        } else {
            return false;
        }
    }
}
