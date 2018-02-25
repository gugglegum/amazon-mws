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
 * Submits a request to create a fulfillment order to Amazon.
 *
 * This Amazon Outbound Core object can submit a request to Amazon to
 * create a new Fulfillment Order. In order to create an order,
 * a Shipment ID is needed. Shipment IDs are given by Amazon by
 * using the `AmazonFulfillmentPreview` object.
 */
class AmazonFulfillmentOrderCreator extends AmazonOutboundCore
{
    /**
     * Sets the marketplace associated with the fulfillment order. (Optional)
     * @param string $m Marketplace ID
     * @return boolean FALSE if improper input
     */
    public function setMarketplace($m)
    {
        if (is_string($m)) {
            $this->options['MarketplaceId'] = $m;
        } else {
            return false;
        }
    }

    /**
     * Sets the fulfillment order ID. (Required)
     *
     * This method sets the Fulfillment Order ID to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * A fulfillment order ID can be generated using the `AmazonFulfillmentPreview` object.
     * @param string $s Maximum 40 characters.
     * @return boolean FALSE if improper input
     */
    public function setFulfillmentOrderId($s)
    {
        if (is_string($s)) {
            $this->options['SellerFulfillmentOrderId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the displayed order ID. (Required)
     *
     * This method sets the Displayable Order ID to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * This is your own order ID, and is the ID that is displayed on the packing slip.
     * @param string $s Must be alpha-numeric or ISO-8559-1 compliant. Maximum 40 characters.
     * @return boolean FALSE if improper input
     */
    public function setDisplayableOrderId($s)
    {
        if (is_string($s)) {
            $this->options['DisplayableOrderId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the fulfillment action that the shipment should use. (Optional)
     *
     * This method indicates whether the order should ship now or be put on hold.
     * If this option is not sent, Amazon will assume that the order will ship now.
     * @param string $s "Ship" or "Hold"
     * @return boolean FALSE if improper input
     */
    public function setFulfillmentAction($s)
    {
        if ($s === 'Ship' || $s === 'Hold') {
            $this->options['FulfillmentAction'] = $s;
        } else {
            $this->log("Tried to set fulfillment action to invalid value", 'Warning');
            return false;
        }
    }

    /**
     * Sets the displayed timestamp. (Required)
     *
     * This method sets the displayed timestamp to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * The parameter is passed through `strtotime()`, so values such as "-1 hour" are fine.
     * @param string $s Time string.
     * @return boolean FALSE if improper input
     */
    public function setDate($s)
    {
        if (is_string($s)) {
            $time = $this->genTime($s);
            $this->options['DisplayableOrderDateTime'] = $time;
        } else {
            return false;
        }
    }

    /**
     * Sets the displayed comment. (Required)
     *
     * This method sets the displayed order comment to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * @param string $s Maximum 1000 characters.
     * @return boolean FALSE if improper input
     */
    public function setComment($s)
    {
        if (is_string($s)) {
            $this->options['DisplayableOrderComment'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the shipping speed. (Required)
     *
     * This method sets the shipping speed to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * @param string $s "Standard", "Expedited", or "Priority"
     * @return boolean FALSE if improper input
     */
    public function setShippingSpeed($s)
    {
        if (is_string($s)) {
            if ($s == 'Standard' || $s == 'Expedited' || $s == 'Priority') {
                $this->options['ShippingSpeedCategory'] = $s;
            } else {
                $this->log("Tried to set shipping status to invalid value", 'Warning');
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Sets the address. (Required)
     *
     * This method sets the destination address to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * The array provided should have the following fields:
     *
     *  - Name - max: 50 char
     *  - Line1 - max: 180 char
     *  - Line2 (optional) - max: 60 char
     *  - Line3 (optional) - max: 60 char
     *  - DistrictOrCounty (optional) - max: 150 char
     *  - City - max: 50 char
     *  - StateOrProvinceCode - max: 150 char
     *  - CountryCode - 2 digits
     *  - PostalCode - max: 20 char
     *  - PhoneNumber - max: 20 char
     *
     * @param array $a See above.
     * @return boolean FALSE if improper input
     */
    public function setAddress($a)
    {
        if (is_null($a) || is_string($a) || !$a) {
            $this->log("Tried to set address to invalid values", 'Warning');
            return false;
        }
        $this->resetAddress();
        $this->options['DestinationAddress.Name'] = $a['Name'];
        $this->options['DestinationAddress.Line1'] = $a['Line1'];
        if (array_key_exists('Line2', $a)) {
            $this->options['DestinationAddress.Line2'] = $a['Line2'];
        } else {
            $this->options['DestinationAddress.Line2'] = null;
        }
        if (array_key_exists('Line3', $a)) {
            $this->options['DestinationAddress.Line3'] = $a['Line3'];
        } else {
            $this->options['DestinationAddress.Line3'] = null;
        }
        if (array_key_exists('DistrictOrCounty', $a)) {
            $this->options['DestinationAddress.DistrictOrCounty'] = $a['DistrictOrCounty'];
        } else {
            $this->options['DestinationAddress.DistrictOrCounty'] = null;
        }
        $this->options['DestinationAddress.City'] = $a['City'];
        $this->options['DestinationAddress.StateOrProvinceCode'] = $a['StateOrProvinceCode'];
        $this->options['DestinationAddress.CountryCode'] = $a['CountryCode'];
        $this->options['DestinationAddress.PostalCode'] = $a['PostalCode'];
        if (array_key_exists('PhoneNumber', $a)) {
            $this->options['DestinationAddress.PhoneNumber'] = $a['PhoneNumber'];
        } else {
            $this->options['DestinationAddress.PhoneNumber'] = null;
        }
    }

    /**
     * Resets the address options.
     *
     * Since address is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetAddress()
    {
        unset($this->options['DestinationAddress.Name']);
        unset($this->options['DestinationAddress.Line1']);
        unset($this->options['DestinationAddress.Line2']);
        unset($this->options['DestinationAddress.Line3']);
        unset($this->options['DestinationAddress.DistrictOrCounty']);
        unset($this->options['DestinationAddress.City']);
        unset($this->options['DestinationAddress.StateOrProvinceCode']);
        unset($this->options['DestinationAddress.CountryCode']);
        unset($this->options['DestinationAddress.PostalCode']);
        unset($this->options['DestinationAddress.PhoneNumber']);
    }

    /**
     * Sets the fulfillment policy. (Optional)
     *
     * This method sets the Fulfillment Policy to be sent in the next request.
     * If this parameter is not set, Amazon will assume a `FillOrKill` policy.
     * Here is a quick description of the policies:
     *
     *  - FillOrKill - cancel the entire order if any of it cannot be fulfilled
     *  - FillAll - send all possible, wait on any unfulfillable items
     *  - FillAllAvailable - send all possible, cancel any unfulfillable items
     *
     * @param string $s "FillOrKill", "FillAll", or "FillAllAvailable"
     * @return boolean FALSE if improper input
     */
    public function setFulfillmentPolicy($s)
    {
        if (is_string($s)) {
            if ($s == 'FillOrKill' || $s == 'FillAll' || $s == 'FillAllAvailable') {
                $this->options['FulfillmentPolicy'] = $s;
            } else {
                $this->log("Tried to set fulfillment policy to invalid value", 'Warning');
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * The "FulfillmentMethod" option is no longer used.
     * @return boolean FALSE
     * @deprecated since 1.3.0
     */
    public function setFulfillmentMethod()
    {
        $this->log("The FulfillmentMethod option is no longer used for creating fulfillment orders.", 'Warning');
        return FALSE;
    }

    /**
     * Sets the email(s). (Optional)
     *
     * This method sets the list of Email addresses to be sent in the next request.
     * Setting this parameter tells Amazon who to send emails to regarding the
     * completion of the shipment.
     * @param array|string $s A list of email addresses, or a single email address. (max: 64 chars each)
     * @return boolean FALSE if improper input
     */
    public function setEmails($s)
    {
        if (is_string($s)) {
            $this->resetEmails();
            $this->options['NotificationEmailList.member.1'] = $s;
        } else if (is_array($s) && $s) {
            $this->resetEmails();
            $i = 1;
            foreach ($s as $x) {
                $this->options['NotificationEmailList.member.' . $i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Removes email options.
     *
     * Use this in case you change your mind and want to remove the email
     * parameters you previously set.
     */
    public function resetEmails()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#NotificationEmailList#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the COD settings. (Optional)
     *
     * This method sets various settings related to COD (Cash on Delivery) orders.
     * Any setting that is passed as NULL will not be set.
     * Amazon will assume a value of 0 for any of the currency options not set.
     * @param string $cu ISO 4217 currency code
     * @param boolean $r [optional] Whether or not COD is required for the order.
     * @param float $c [optional] COD charge to collect
     * @param float $ct [optional] tax on the COD charge to collect
     * @param float $s [optional] shipping charge to collect
     * @param float $st [optional] tax on the shipping charge to collect
     * @return boolean FALSE if improper input
     */
    public function setCodSettings($cu, $r = null, $c = null, $ct = null, $s = null, $st = null)
    {
        if (empty($cu)) {
            return false;
        }
        if (isset($r)) {
            if (filter_var($r, FILTER_VALIDATE_BOOLEAN)) {
                $r = 'true';
            } else {
                $r = 'false';
            }
            $this->options['CODSettings.IsCODRequired'] = $r;
        }
        //COD charge
        if (isset($c) && is_numeric($c)) {
            $this->options['CODSettings.CODCharge.Value'] = $c;
            $this->options['CODSettings.CODCharge.CurrencyCode'] = $cu;
        }
        //COD charge tax
        if (isset($ct) && is_numeric($ct)) {
            $this->options['CODSettings.CODChargeTax.Value'] = $ct;
            $this->options['CODSettings.CODChargeTax.CurrencyCode'] = $cu;
        }
        //shipping charge
        if (isset($s) && is_numeric($s)) {
            $this->options['CODSettings.ShippingCharge.Value'] = $s;
            $this->options['CODSettings.ShippingCharge.CurrencyCode'] = $cu;
        }
        //shipping charge tax
        if (isset($st) && is_numeric($st)) {
            $this->options['CODSettings.ShippingChargeTax.Value'] = $st;
            $this->options['CODSettings.ShippingChargeTax.CurrencyCode'] = $cu;
        }
    }

    /**
     * Removes COD settings options.
     *
     * Use this in case you change your mind and want to remove the COD settings you previously set.
     */
    public function resetCodSettings()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#CODSettings#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the delivery window for the order. (Optional)
     *
     * This method sets the delivery window's start and end times for the next request.
     * This option is required if the shipping speed is set to "ScheduledDelivery".
     * The parameters are passed through `strtotime()`, so values such as "-1 hour" are fine.
     * @param string $s A time string for the earliest time.
     * @param string $e A time string for the latest time.
     * @return boolean FALSE if improper input
     * @see genTime
     */
    public function setDeliveryWindow($s, $e)
    {
        if (empty($s) || empty($e)) {
            return false;
        }
        $times = $this->genTime($s);
        $this->options['DeliveryWindow.StartDateTime'] = $times;
        $timee = $this->genTime($e);
        $this->options['DeliveryWindow.EndDateTime'] = $timee;
    }

    /**
     * Removes delivery window options.
     *
     * Use this in case you change your mind and want to remove the delivery window option you previously set.
     */
    public function resetDeliveryWindow()
    {
        unset($this->options['DeliveryWindow.StartDateTime']);
        unset($this->options['DeliveryWindow.EndDateTime']);
    }

    /**
     * Sets the items. (Required)
     *
     * This method sets the Fulfillment Order ID to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * The array provided should contain a list of arrays, each with the following fields:
     *
     *  - SellerSKU - max: 50 char
     *  - SellerFulfillmentOrderItemId - useful for differentiating different items with the same SKU, max: 50 char
     *  - Quantity - numeric
     *  - GiftMessage (optional) - max: 512 char
     *  - DisplayableComment (optional) - max: 250 char
     *  - FulfillmentNetworkSKU (optional) - usually returned by `AmazonFulfillmentPreview`
     *  - OrderItemDisposition (optional) - "Sellable" or "Unsellable"
     *  - PerUnitDeclaredValue (optional array) -
     *
     *  - CurrencyCode - ISO 4217 currency code
     *  - Value - number
     *
     *  - PerUnitPrice (optional array) - same structure as PerUnitDeclaredValue
     *  - PerUnitTax (optional array) - same structure as PerUnitDeclaredValue
     *
     * @param array $a See above.
     * @return boolean FALSE if improper input
     */
    public function setItems($a)
    {
        if (is_null($a) || is_string($a) || !$a) {
            $this->log("Tried to set Items to invalid values", 'Warning');
            return false;
        }
        $this->resetItems();
        $i = 1;
        foreach ($a as $x) {
            if (is_array($x) && array_key_exists('SellerSKU', $x) && array_key_exists('SellerFulfillmentOrderItemId', $x) && array_key_exists('Quantity', $x)) {
                $this->options['Items.member.' . $i . '.SellerSKU'] = $x['SellerSKU'];
                $this->options['Items.member.' . $i . '.SellerFulfillmentOrderItemId'] = $x['SellerFulfillmentOrderItemId'];
                $this->options['Items.member.' . $i . '.Quantity'] = $x['Quantity'];
                if (array_key_exists('GiftMessage', $x)) {
                    $this->options['Items.member.' . $i . '.GiftMessage'] = $x['GiftMessage'];
                }
                if (array_key_exists('Comment', $x)) {
                    $this->options['Items.member.' . $i . '.DisplayableComment'] = $x['Comment'];
                }
                if (array_key_exists('FulfillmentNetworkSKU', $x)) {
                    $this->options['Items.member.' . $i . '.FulfillmentNetworkSKU'] = $x['FulfillmentNetworkSKU'];
                }
                if (array_key_exists('OrderItemDisposition', $x)) {
                    $this->options['Items.member.' . $i . '.OrderItemDisposition'] = $x['OrderItemDisposition'];
                }
                if (array_key_exists('PerUnitDeclaredValue', $x)) {
                    $this->options['Items.member.' . $i . '.PerUnitDeclaredValue.CurrencyCode'] = $x['PerUnitDeclaredValue']['CurrencyCode'];
                    $this->options['Items.member.' . $i . '.PerUnitDeclaredValue.Value'] = $x['PerUnitDeclaredValue']['Value'];
                }
                if (array_key_exists('PerUnitPrice', $x)) {
                    $this->options['Items.member.' . $i . '.PerUnitPrice.CurrencyCode'] = $x['PerUnitPrice']['CurrencyCode'];
                    $this->options['Items.member.' . $i . '.PerUnitPrice.Value'] = $x['PerUnitPrice']['Value'];
                }
                if (array_key_exists('PerUnitTax', $x)) {
                    $this->options['Items.member.' . $i . '.PerUnitTax.CurrencyCode'] = $x['PerUnitTax']['CurrencyCode'];
                    $this->options['Items.member.' . $i . '.PerUnitTax.Value'] = $x['PerUnitTax']['Value'];
                }

                $i++;
            } else {
                $this->resetItems();
                $this->log("Tried to set Items with invalid array", 'Warning');
                return false;
            }
        }
    }

    /**
     * Resets the item options.
     *
     * Since the list of items is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetItems()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#Items#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Creates a Fulfillment Order with Amazon.
     *
     * Submits a `CreateFulfillmentOrder` request to Amazon. In order to do this,
     * a number of parameters are required. Amazon will send back an HTTP response,
     * so there is no data to retrieve afterwards. The following parameters are required:
     * fulfillment order ID, displayed order ID, displayed timestamp, comment,
     * shipping speed, address, and items.
     * @return boolean TRUE if the order creation was successful, FALSE if something goes wrong
     */
    public function createOrder()
    {
        if (!array_key_exists('SellerFulfillmentOrderId', $this->options)) {
            $this->log("Seller Fulfillment Order ID must be set in order to create an order", 'Warning');
            return false;
        }
        if (!array_key_exists('DisplayableOrderId', $this->options)) {
            $this->log("Displayable Order ID must be set in order to create an order", 'Warning');
            return false;
        }
        if (!array_key_exists('DisplayableOrderDateTime', $this->options)) {
            $this->log("Date must be set in order to create an order", 'Warning');
            return false;
        }
        if (!array_key_exists('DisplayableOrderComment', $this->options)) {
            $this->log("Comment must be set in order to create an order", 'Warning');
            return false;
        }
        if (!array_key_exists('ShippingSpeedCategory', $this->options)) {
            $this->log("Shipping Speed must be set in order to create an order", 'Warning');
            return false;
        }
        if (!array_key_exists('DestinationAddress.Name', $this->options)) {
            $this->log("Address must be set in order to create an order", 'Warning');
            return false;
        }
        if (!array_key_exists('Items.member.1.SellerSKU', $this->options)) {
            $this->log("Items must be set in order to create an order", 'Warning');
            return false;
        }

        $this->prepareCreate();

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        if ($this->mockMode) {
            $response = $this->fetchMockResponse();
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));
        }
        if (!$this->checkResponse($response)) {
            return false;
        } else {
            $this->log("Successfully created Fulfillment Order " . $this->options['SellerFulfillmentOrderId'] . " / " . $this->options['DisplayableOrderId']);
            return true;
        }
    }

    /**
     * Updates a Fulfillment Order with Amazon.
     *
     * Submits an `UpdateFulfillmentOrder` request to Amazon. In order to do this,
     * a fulfillment order ID is required. Amazon will send back an HTTP response,
     * so there is no data to retrieve afterwards.
     * @return boolean TRUE if the order creation was successful, FALSE if something goes wrong
     */
    public function updateOrder()
    {
        if (!array_key_exists('SellerFulfillmentOrderId', $this->options)) {
            $this->log("Seller Fulfillment Order ID must be set in order to update an order", 'Warning');
            return false;
        }

        $this->prepareUpdate();

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        if ($this->mockMode) {
            $response = $this->fetchMockResponse();
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));
        }
        if (!$this->checkResponse($response)) {
            return false;
        } else {
            $this->log("Successfully updated Fulfillment Order " . $this->options['SellerFulfillmentOrderId']);
            return true;
        }
    }

    /**
     * Sets up options for using `createOrder()`.
     *
     * This changes key options for using `createOrder()`.
     */
    protected function prepareCreate()
    {
        $this->options['Action'] = 'CreateFulfillmentOrder';
    }

    /**
     * Sets up options for using `updateOrder()`.
     *
     * This changes key options for using `updateOrder()`. Please note: because the
     * operation for updating the order does not use all of the parameters, some of the
     * parameters will be removed. The following parameters are removed:
     * COD settings and delivery window.
     */
    protected function prepareUpdate()
    {
        $this->options['Action'] = 'UpdateFulfillmentOrder';
        $this->resetCodSettings();
        $this->resetDeliveryWindow();
    }

}
