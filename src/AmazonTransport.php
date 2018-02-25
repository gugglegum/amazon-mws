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
 * Fetches transport info for a fulfillment shipment or updates it.
 *
 * This Amazon Inbound Core object retrieves transportation information for
 * an inbound fulfillment shipment. It can also update transport information
 * and confirm or cancel the transport request. In order to retrieve or send
 * any information, the ID of an inbound fulfillment shipment is needed.
 * In order to update the transport information, additional details about the
 * shipment are required, such as shipment type. Use the AmazonShipment object
 * to create an inbound shipment and acquire a shipment ID.
 */
class AmazonTransport extends AmazonInboundCore
{
    /**
     * @var string
     */
    protected $status;

    /**
     * @var array       Associative array
     */
    protected $contents;

    /**
     * AmazonTransport gets or sends transport information about a shipment from Amazon.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param array $config A config array to set.
     * @param string $id [optional] The Fulfillment Shipment ID to set for the object.
     * @param boolean $mock [optional] This is a flag for enabling Mock Mode.
     * This defaults to FALSE.
     * @param array|string $m [optional] The files (or file) to use in Mock Mode.
     */
    public function __construct(array $config, $id = null, $mock = false, $m = null)
    {
        parent::__construct($config, $mock, $m);

        if ($id) {
            $this->setShipmentId($id);
        }
    }

    /**
     * Sets the shipment ID. (Required)
     * @param string $s Shipment ID
     * @return boolean FALSE if improper input
     */
    public function setShipmentId($s)
    {
        if (is_string($s) && $s) {
            $this->options['ShipmentId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the parameter for whether or not the shipment is with an Amazon-partnered carrier. (Required for send)
     *
     * The other parameters that will be required will change depending on this setting.
     * This parameter is required for sending transport content information to Amazon.
     * This parameter is removed by all other actions.
     * @param boolean $b Whether or not the shipment's carrier is partnered
     */
    public function setIsPartnered($b)
    {
        if ($b) {
            $v = 'true';
        } else {
            $v = 'false';
        }
        $this->options['IsPartnered'] = $v;
    }

    /**
     * Sets the shipment type. (Required for send)
     *
     * The other parameters that will be required will change depending on this setting.
     * Use "SP" if the shipment is for small parcels and "LTL" when the shipment is for pallets in a truck.
     * This parameter is required for sending transport content information to Amazon.
     * This parameter is removed by all other actions.
     * @param string $s "SP" or "LTL"
     * @return boolean FALSE if improper input
     */
    public function setShipmentType($s)
    {
        $options = array(
            'SP',
            'LTL',
        );
        if (in_array($s, $options)) {
            $this->options['ShipmentType'] = $s;
        } else {
            $this->log('Tried to set ShipmentType to invalid value', 'Warning');
            return false;
        }
    }

    /**
     * Determines which of the four possible transport detail parameter prefixes should be used.
     * The parameter to use depends on the partnered and shipment type parameters.
     * @return string|boolean parameter prefix or FALSE if it could not be determined
     */
    protected function determineDetailOption()
    {
        if (!isset($this->options['IsPartnered']) || !isset($this->options['ShipmentType'])) {
            $this->log('Cannot set transport details without shipment type and partner parameters!', 'Warning');
            return false;
        }
        $op = 'TransportDetails.';
        if ($this->options['ShipmentType'] == 'SP') {
            if ($this->options['IsPartnered'] == 'true') {
                return $op . 'PartneredSmallParcelData';
            } else {
                return $op . 'NonPartneredSmallParcelData';
            }
        } else if ($this->options['ShipmentType'] == 'LTL') {
            if ($this->options['IsPartnered'] == 'true') {
                return $op . 'PartneredLtlData';
            } else {
                return $op . 'NonPartneredLtlData';
            }
        }
        $this->log('Unknown shipment type, cannot set transport details!', 'Warning');
        return false;
    }

    /**
     * Sets the carrier name used for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is required for sending transport content information to Amazon when the
     * carrier is not partnered. This parameter is optional when the carrier is partnered and the
     * shipment type is set to "SP" for Small Parcel.
     * This parameter is removed by all other actions.
     * @param string $s See the comment inside for a list of valid values.
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setCarrier($s)
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set carrier name because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_string($s) && $s) {
            $this->options[$op . '.CarrierName'] = $s;
        } else {
            return false;
        }
        /*
         * Valid carrier names when shipment type is set to LTL:
         * BUSINESS_POST
         * DHL_AIRWAYS_INC
         * DHL_UK
         * PARCELFORCE
         * DPD
         * TNT_LOGISTICS_CORPORATION
         * TNT
         * YODEL
         * UNITED_PARCEL_SERVICE_INC
         * DHL_EXPRESS_USA_INC
         * FEDERAL_EXPRESS_CORP
         * UNITED_STATES_POSTAL_SERVICE
         * OTHER
         *
         * Valid carrier names when shipment type is set to SP:
         * UNITED_PARCEL_SERVICE_INC
         * DHL_STANDARD
         */
    }

    /**
     * Sets the list of packages. (Required for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is required for sending transport content information to Amazon when the
     * shipment type is set to "SP" for Small Parcel.
     * If the carrier is partnered with Amazon, each package array should have the following keys:
     *
     *  - Length - positive decimal number
     *  - Width - positive decimal number
     *  - Height - positive decimal number
     *  - Weight - integer
     *
     * If the carrier is not partnered with Amazon, each package array should have this instead:
     *
     *  - TrackingId - tracking number, maximum 30 characters
     *
     * This parameter is removed by all other actions.
     * @param array $a See above.
     * @param string $du Dimensions unit: "inches" or "centimeters", defaults to centimeters
     * @param string $wu Weight unit: "pounds" or "kilograms", defaults to kilograms
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setPackages($a, $du = 'centimeters', $wu = 'kilograms')
    {
        if (empty($a) || !is_array($a)) {
            $this->log("Tried to set package list to invalid values", 'Warning');
            return false;
        }
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set packages because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        $this->resetPackages();
        $i = 1;
        foreach ($a as $x) {
            $prefix = $op . '.PackageList.member.' . $i;
            if (is_array($x)) {
                if (isset($x['Length']) && isset($x['Width']) && isset($x['Height'])) {
                    $this->options[$prefix . '.Dimensions.Length'] = $x['Length'];
                    $this->options[$prefix . '.Dimensions.Width'] = $x['Width'];
                    $this->options[$prefix . '.Dimensions.Height'] = $x['Height'];
                    $this->options[$prefix . '.Dimensions.Unit'] = $du;
                }
                if (isset($x['Weight'])) {
                    $this->options[$prefix . '.Weight.Value'] = $x['Weight'];
                    $this->options[$prefix . '.Weight.Unit'] = $wu;
                }
                if (isset($x['TrackingId'])) {
                    $this->options[$prefix . '.TrackingId'] = $x['TrackingId'];
                }
                $i++;
            } else {
                $this->resetPackages();
                $this->log("Tried to set packages with invalid array", 'Warning');
                return false;
            }
        }
    }

    /**
     * Resets the package list parameters.
     *
     * Since package details are required, these parameters should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetPackages()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#PackageList#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the PRO number for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is required when the carrier is not partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $s PRO number for the shipment given by the carrier
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setProNumber($s)
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set PRO number because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_string($s) && $s) {
            $this->options[$op . '.ProNumber'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the contact information for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is required when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $n Name of the contact person, maximum 50 characters
     * @param string $p Phone number of the contact person, maximum 20 characters
     * @param string $e E-mail address of the contact person, maximum 50 characters
     * @param string $f Fax number of the contact person, maximum 20 characters
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setContact($n, $p, $e, $f)
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set contact info because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if ($n && $p && $e && $f && is_string($n) && is_string($p) && is_string($e) && is_string($f)) {
            $this->options[$op . '.Contact.Name'] = $n;
            $this->options[$op . '.Contact.Phone'] = $p;
            $this->options[$op . '.Contact.Email'] = $e;
            $this->options[$op . '.Contact.Fax'] = $f;
        } else {
            return false;
        }
    }

    /**
     * Sets the box count for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is required when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param int $n number of boxes
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setBoxCount($n)
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set box count because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_numeric($n) && $n > 1) {
            $this->options[$op . '.BoxCount'] = $n;
        } else {
            return false;
        }
    }

    /**
     * Sets the freight class for the shipment. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * If this parameter is not sent, Amazon will estimate the freight class on their own.
     * This parameter is removed by all other actions.
     * @param int $n See the comment inside for a list of valid values.
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setFreightClass($n)
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set freight class because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_numeric($n) && $n) {
            $this->options[$op . '.SellerFreightClass'] = $n;
        } else {
            return false;
        }
        /*
         * Valid freight class values:
         * 50
         * 55
         * 60
         * 65
         * 70
         * 77.5
         * 85
         * 92.5
         * 100
         * 110
         * 125
         * 150
         * 175
         * 200
         * 250
         * 300
         * 400
         * 500
         */
    }

    /**
     * Sets the date that the shipment will be ready for pickup. (Required to send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is required when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $d A time string
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setReadyDate($d)
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set ready date because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        try {
            $this->options[$op . '.FreightReadyDate'] = strstr($this->genTime($d), 'T', true);
        } catch (\InvalidArgumentException $e) {
            unset($this->options[$op . '.FreightReadyDate']);
            $this->log('Error: ' . $e->getMessage(), 'Warning');
            return false;
        }
    }

    /**
     * Sets the list of pallets. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * Each pallet array should have the following keys:
     *
     *  - Length - positive decimal number
     *  - Width - positive decimal number
     *  - Height - positive decimal number
     *  - IsStacked - boolean
     *  - Weight (optional) - integer
     *
     * This parameter is removed by all other actions.
     * @param array $a See above.
     * @param string $du Dimensions unit: "inches" or "centimeters", defaults to centimeters
     * @param string $wu Weight unit: "pounds" or "kilograms", defaults to kilograms
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setPallets($a, $du = 'centimeters', $wu = 'kilograms')
    {
        if (empty($a) || !is_array($a)) {
            $this->log("Tried to set pallet list to invalid values", 'Warning');
            return false;
        }
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set pallets because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        $this->resetPallets();
        $i = 1;
        foreach ($a as $x) {
            $prefix = $op . '.PalletList.member.' . $i;
            if (is_array($x)) {
                if (isset($x['Length']) && isset($x['Width']) && isset($x['Height'])) {
                    $this->options[$prefix . '.Dimensions.Length'] = $x['Length'];
                    $this->options[$prefix . '.Dimensions.Width'] = $x['Width'];
                    $this->options[$prefix . '.Dimensions.Height'] = $x['Height'];
                    $this->options[$prefix . '.Dimensions.Unit'] = $du;
                }
                if (isset($x['Weight'])) {
                    $this->options[$prefix . '.Weight.Value'] = $x['Weight'];
                    $this->options[$prefix . '.Weight.Unit'] = $wu;
                }
                if (isset($x['IsStacked'])) {
                    if ($x['IsStacked']) {
                        $this->options[$prefix . '.IsStacked'] = 'true';
                    } else {
                        $this->options[$prefix . '.IsStacked'] = 'false';
                    }
                }
                $i++;
            } else {
                $this->resetPallets();
                $this->log("Tried to set pallets with invalid array", 'Warning');
                return false;
            }
        }
    }

    /**
     * Resets the pallet list parameters.
     *
     * Use this in case you change your mind and want to remove the pallet parameters you previously set.
     */
    public function resetPallets()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#PalletList#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the total weight for the shipment. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $v Decimal number
     * @param string $u "pounds" or "kilograms", defaults to kilograms
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setTotalWeight($v, $u = 'kilograms')
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set total weight because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (!empty($v) && !empty($u) && is_numeric($v) && ($u == 'pounds' || $u == 'kilograms')) {
            $this->options[$op . '.TotalWeight.Value'] = $v;
            $this->options[$op . '.TotalWeight.Unit'] = $u;
        } else {
            return false;
        }
    }

    /**
     * Sets the declared value for the shipment. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set `before` setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $v Money amount
     * @param string $c ISO 4217 currency code (ex: USD)
     * @return boolean FALSE if improper input or needed parameters are not set
     */
    public function setDeclaredValue($v, $c)
    {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set declared value because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (!empty($v) && !empty($c) && is_numeric($v) && is_string($c) && !is_numeric($c)) {
            $this->options[$op . '.SellerDeclaredValue.Value'] = $v;
            $this->options[$op . '.SellerDeclaredValue.CurrencyCode'] = $c;
        } else {
            return false;
        }
    }

    /**
     * Resets the transport detail parameters.
     *
     * Since transport details are required, these parameters should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetTransportDetails()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#TransportDetails#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Removes all parameters specific to sending transport content info.
     *  The following parameters are removed:
     * IsPartnered, shipment type, and all transport details.
     * @see resetTransportDetails
     */
    protected function resetSendParams()
    {
        unset($this->options['IsPartnered']);
        unset($this->options['ShipmentType']);
        $this->resetTransportDetails();
    }

    /**
     * Sends transport content information for a shipment with Amazon.
     *
     * Submits a `PutTransportContent` request to Amazon. In order to do this,
     * a fulfillment shipment ID, shipment type, IsPartnered, and
     * various details are required. The exact details required depend on the
     * IsPartnered and shipment type parameters set.
     * Amazon will send a status back as a response, which can be retrieved
     * using `getStatus()`.
     * @return boolean FALSE if something goes wrong
     * @see verifySendParams
     */
    public function sendTransportContents()
    {
        if (!$this->verifySendParams()) {
            return false;
        }

        $this->prepareSend();

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
     * Sets up options for using `sendTransportContents()`.
     *
     * This changes key options for using `sendTransportContents()`.
     */
    protected function prepareSend()
    {
        $this->throttleGroup = 'PutTransportContent';
        $this->options['Action'] = 'PutTransportContent';
    }

    /**
     * Checks to see if all of the parameters needed for `sendTransportContents()` are set.
     * @return boolean TRUE if everything is good, FALSE if something is missing
     */
    protected function verifySendParams()
    {
        $m = ' must be set in order to send transport content!';
        //common requirements
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('Shipment ID' . $m, 'Warning');
            return false;
        }
        if (!array_key_exists('IsPartnered', $this->options)) {
            $this->log('IsPartnered' . $m, 'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentType', $this->options)) {
            $this->log('Shipment type' . $m, 'Warning');
            return false;
        }
        //requirements based on partnership and type
        $p = $this->options['IsPartnered'] == 'true';
        $sp = $this->options['ShipmentType'] == 'SP';
        $ltl = $this->options['ShipmentType'] == 'LTL';
        //options could be in four possible places, so a search is needed
        $foundCarrier = false;
        $foundPackages = false;
        $foundPro = false;
        $foundContact = false;
        $foundBoxCount = false;
        $foundReady = false;
        foreach ($this->options as $op => $junk) {
            if (preg_match("#CarrierName#", $op)) {
                $foundCarrier = true;
            }
            if (preg_match("#PackageList\.member\.1#", $op)) {
                $foundPackages = true;
            }
            if (preg_match("#ProNumber#", $op)) {
                $foundPro = true;
            }
            if (preg_match("#Contact\.Name#", $op)) {
                $foundContact = true;
            }
            if (preg_match("#BoxCount#", $op)) {
                $foundBoxCount = true;
            }
            if (preg_match("#FreightReadyDate#", $op)) {
                $foundReady = true;
            }
        }
        if (!$p && !$foundCarrier) {
            $this->log('Carrier' . $m, 'Warning');
            return false;
        }
        if ($sp && !$foundPackages) {
            $this->log('Packages' . $m, 'Warning');
            return false;
        }
        if (!$p && $ltl && !$foundPro) {
            $this->log('PRO number' . $m, 'Warning');
            return false;
        }
        if ($p && $ltl && !$foundContact) {
            $this->log('Contact info' . $m, 'Warning');
            return false;
        }
        if ($p && $ltl && !$foundBoxCount) {
            $this->log('Box count' . $m, 'Warning');
            return false;
        }
        if ($p && $ltl && !$foundReady) {
            $this->log('Ready date' . $m, 'Warning');
            return false;
        }

        //all good
        return true;
    }

    /**
     * Gets transport content information for a shipment from Amazon.
     *
     * Submits a `GetTransportContent` request to Amazon. In order to do this,
     * a fulfillment shipment ID is required.
     * Before this action can be used, information about the transport contents
     * must be provided to Amazon using `sendTransportContents()`.
     * Amazon will send data back as a response, which can be retrieved using `getContentInfo()`.
     * The status of the transport request can be retrieved using `getStatus()`.
     * @return boolean FALSE if something goes wrong
     */
    public function fetchTransportContent()
    {
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('Shipment ID must be set in order to get transport contents!', 'Warning');
            return false;
        }

        $this->prepareGetContent();

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
     * Sets up options for using `fetchTransportContent()`.
     *
     * This changes key options for using `fetchTransportContent()`.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed.
     * @see resetSendParams
     */
    protected function prepareGetContent()
    {
        $this->throttleGroup = 'GetTransportContent';
        $this->options['Action'] = 'GetTransportContent';
        $this->resetSendParams();
    }

    /**
     * Sends a request to Amazon to start estimating a shipping request.
     *
     * Submits a `EstimateTransportRequest` request to Amazon. In order to do this,
     * a fulfillment shipment ID is required.
     * Before this action can be used, information about the transport contents
     * must be provided to Amazon using `sendTransportContents()`.
     * Amazon will send a status back as a response, which can be retrieved
     * using `getStatus()`.
     * @return boolean FALSE if something goes wrong
     */
    public function estimateTransport()
    {
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('Shipment ID must be set in order to estimate the transport request!', 'Warning');
            return false;
        }

        $this->prepareEstimate();

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
     * Sets up options for using `estimateTransport()`.
     *
     * This changes key options for using `estimateTransport()`.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed.
     * @see resetSendParams
     */
    protected function prepareEstimate()
    {
        $this->throttleGroup = 'EstimateTransportRequest';
        $this->options['Action'] = 'EstimateTransportRequest';
        $this->resetSendParams();
    }

    /**
     * Confirms an estimated transport request with Amazon.
     *
     * Submits a `ConfirmTransportRequest` request to Amazon. In order to do this,
     * a fulfillment shipment ID is required.
     * Before this action can be used, the transport info must be estimated by Amazon,
     * which can be done by using `estimateTransport()`.
     * Amazon will send a status back as a response, which can be retrieved
     * using `getStatus()`.
     * @return boolean FALSE if something goes wrong
     */
    public function confirmTransport()
    {
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('Shipment ID must be set in order to confirm the transport request!', 'Warning');
            return false;
        }

        $this->prepareConfirm();

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
     * Sets up options for using `confirmTransport()`.
     *
     * This changes key options for using `confirmTransport()`.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed.
     * @see resetSendParams
     */
    protected function prepareConfirm()
    {
        $this->throttleGroup = 'ConfirmTransportRequest';
        $this->options['Action'] = 'ConfirmTransportRequest';
        $this->resetSendParams();
    }

    /**
     * Voids a previously-confirmed transport request with Amazon.
     *
     * Submits a `VoidTransportRequest` request to Amazon. In order to do this,
     * a fulfillment shipment ID is required.
     * Before this action can be used, the transport info must have been confirmed
     * using `confirmTransport()`.
     * Amazon will send a status back as a response, which can be retrieved
     * using `getStatus()`.
     * @return boolean FALSE if something goes wrong
     */
    public function voidTransport()
    {
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('Shipment ID must be set in order to void the transport request!', 'Warning');
            return false;
        }

        $this->prepareVoid();

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
     * Sets up options for using `voidTransport()`.
     *
     * This changes key options for using `voidTransport()`.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed.
     * @see resetSendParams
     */
    protected function prepareVoid()
    {
        $this->throttleGroup = 'VoidTransportRequest';
        $this->options['Action'] = 'VoidTransportRequest';
        $this->resetSendParams();
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

        //response from send, confirm, estimate, void
        if (isset($xml->TransportResult->TransportStatus)) {
            $this->status = (string)$xml->TransportResult->TransportStatus;
        }
        //response from get
        if (isset($xml->TransportContent)) {
            $this->contents = array();
            $this->status = (string)$xml->TransportContent->TransportResult->TransportStatus;
            $this->contents['SellerId'] = (string)$xml->TransportContent->TransportHeader->SellerId;
            $this->contents['ShipmentId'] = (string)$xml->TransportContent->TransportHeader->ShipmentId;
            $this->contents['IsPartnered'] = (string)$xml->TransportContent->TransportHeader->IsPartnered;
            $this->contents['ShipmentType'] = (string)$xml->TransportContent->TransportHeader->ShipmentType;

            //one of four possible response structures for details
            $this->contents['Details'] = array();
            $d = array();
            if (isset($xml->TransportContent->TransportDetails->PartneredSmallParcelData)) {
                //Partnered + SP
                $d = $xml->TransportContent->TransportDetails->PartneredSmallParcelData;
            } else if (isset($xml->TransportContent->TransportDetails->NonPartneredSmallParcelData)) {
                //Non-Partnered + SP
                $d = $xml->TransportContent->TransportDetails->NonPartneredSmallParcelData;
            } else if (isset($xml->TransportContent->TransportDetails->PartneredLtlData)) {
                //Partnered + LTL
                $d = $xml->TransportContent->TransportDetails->PartneredLtlData;
                $this->contents['Details']['Contact']['Name'] = (string)$d->Contact->Name;
                $this->contents['Details']['Contact']['Phone'] = (string)$d->Contact->Phone;
                $this->contents['Details']['Contact']['Email'] = (string)$d->Contact->Email;
                $this->contents['Details']['Contact']['Fax'] = (string)$d->Contact->Fax;
                $this->contents['Details']['BoxCount'] = (string)$d->BoxCount;
                if (isset($d->SellerFreightClass)) {
                    $this->contents['Details']['SellerFreightClass'] = (string)$d->SellerFreightClass;
                }
                $this->contents['Details']['FreightReadyDate'] = (string)$d->FreightReadyDate;
                foreach ($d->PalletList->children() as $x) {
                    $temp = array();
                    $temp['IsStacked'] = (string)$x->IsStacked;
                    $temp['Dimensions']['Unit'] = (string)$x->Dimensions->Unit;
                    $temp['Dimensions']['Length'] = (string)$x->Dimensions->Length;
                    $temp['Dimensions']['Width'] = (string)$x->Dimensions->Width;
                    $temp['Dimensions']['Height'] = (string)$x->Dimensions->Height;
                    if (isset($x->Weight)) {
                        $temp['Weight']['Value'] = (string)$x->Weight->Value;
                        $temp['Weight']['Unit'] = (string)$x->Weight->Unit;
                    }
                    $this->contents['Details']['PalletList'][] = $temp;
                }
                $this->contents['Details']['TotalWeight']['Value'] = (string)$d->TotalWeight->Value;
                $this->contents['Details']['TotalWeight']['Unit'] = (string)$d->TotalWeight->Unit;
                if (isset($d->SellerDeclaredValue)) {
                    $this->contents['Details']['SellerDeclaredValue']['Value'] = (string)$d->SellerDeclaredValue->Value;
                    $this->contents['Details']['SellerDeclaredValue']['CurrencyCode'] = (string)$d->SellerDeclaredValue->CurrencyCode;
                }
                if (isset($d->AmazonCalculatedValue)) {
                    $this->contents['Details']['AmazonCalculatedValue']['Value'] = (string)$d->AmazonCalculatedValue->Value;
                    $this->contents['Details']['AmazonCalculatedValue']['CurrencyCode'] = (string)$d->AmazonCalculatedValue->CurrencyCode;
                }
                $this->contents['Details']['PreviewPickupDate'] = (string)$d->PreviewPickupDate;
                $this->contents['Details']['PreviewDeliveryDate'] = (string)$d->PreviewDeliveryDate;
                $this->contents['Details']['PreviewFreightClass'] = (string)$d->PreviewFreightClass;
                $this->contents['Details']['AmazonReferenceId'] = (string)$d->AmazonReferenceId;
                $this->contents['Details']['IsBillOfLadingAvailable'] = (string)$d->IsBillOfLadingAvailable;
                $this->contents['Details']['CarrierName'] = (string)$d->CarrierName;
            } else if (isset($xml->TransportContent->TransportDetails->NonPartneredLtlData)) {
                //Non-Partnered + LTL
                $d = $xml->TransportContent->TransportDetails->NonPartneredLtlData;
                $this->contents['Details']['CarrierName'] = (string)$d->CarrierName;
                $this->contents['Details']['ProNumber'] = (string)$d->ProNumber;
            }
            //shared by both SP structures
            if (isset($d->PackageList)) {
                foreach ($d->PackageList->children() as $x) {
                    $temp = array();
                    $temp['TrackingId'] = (string)$x->TrackingId;
                    $temp['PackageStatus'] = (string)$x->PackageStatus;
                    $temp['CarrierName'] = (string)$x->CarrierName;
                    if (isset($x->Weight)) {
                        $temp['Weight']['Value'] = (string)$x->Weight->Value;
                        $temp['Weight']['Unit'] = (string)$x->Weight->Unit;
                    }
                    if (isset($x->Dimensions)) {
                        $temp['Dimensions']['Unit'] = (string)$x->Dimensions->Unit;
                        $temp['Dimensions']['Length'] = (string)$x->Dimensions->Length;
                        $temp['Dimensions']['Width'] = (string)$x->Dimensions->Width;
                        $temp['Dimensions']['Height'] = (string)$x->Dimensions->Height;
                    }
                    $this->contents['Details']['PackageList'][] = $temp;
                }
            }
            //shared by both partnered structures
            if (isset($d->PartneredEstimate)) {
                $pe = array();
                $pe['Amount']['Value'] = (string)$d->PartneredEstimate->Amount->Value;
                $pe['Amount']['CurrencyCode'] = (string)$d->PartneredEstimate->Amount->CurrencyCode;
                if (isset($d->PartneredEstimate->ConfirmDeadline)) {
                    $pe['ConfirmDeadline'] = (string)$d->PartneredEstimate->ConfirmDeadline;
                }
                if (isset($d->PartneredEstimate->ConfirmDeadline)) {
                    $pe['VoidDeadline'] = (string)$d->PartneredEstimate->VoidDeadline;
                }
                $this->contents['Details']['PartneredEstimate'] = $pe;
            }
        }
    }

    /**
     * Returns the transport status.
     *
     * Possible values for the status:
     * "WORKING","ERROR_ON_ESTIMATING","ESTIMATING","ESTIMATED","ERROR_ON_CONFIRMING",
     * "CONFIRMING","CONFIRMED","VOIDING","VOIDED", and "ERROR_IN_VOIDING".
     * This method will return FALSE if the status has not been set yet.
     * @return string|boolean status value, or FALSE if value not set yet
     */
    public function getStatus()
    {
        if (isset($this->status)) {
            return $this->status;
        } else {
            return false;
        }
    }

    /**
     * Returns information about transport contents.
     *
     * The returned array will have the following fields:
     *
     *  - SellerId
     *  - ShipmentId
     *  - IsPartnered - "true" or "false"
     *  - ShipmentType - "SP" or "LTL"
     *  - Details - array, see `getContentDetails()` for details
     *
     * This method will return FALSE if the data has not been set yet.
     * @return array|boolean multi-dimensional array, or FALSE if data not set yet
     * @see getContentDetails
     */
    public function getContentInfo()
    {
        if (isset($this->contents)) {
            return $this->contents;
        } else {
            return false;
        }
    }

    /**
     * Returns details about transport contents.
     *
     * The contents of the array will vary depending on the shipment type and
     * whether or not the shipment is with a partnered carrier.
     * The returned array can have the following fields:
     *
     *  - PackageList (SP) - array, see `getPackageList()` for details
     *  - PartneredEstimate (if Partnered) - array
     *
     *  - Amount - array with keys "Value" and "CurrencyCode"
     *  - ConfirmDeadline (optional) - ISO 8601 date format
     *  - VoidDeadline (optional) - ISO 8601 date format
     *
     *  - CarrierName (LTL)
     *  - ProNumber (LTL + not Partnered)
     *  - Contact (LTL + Partnered) - array
     *
     *  - Name
     *  - Phone
     *  - Email
     *  - Fax
     *
     *  - BoxCount (LTL + Partnered)
     *  - SellerFreightClass (optional, LTL + Partnered)
     *  - FreightReadyDate (LTL + Partnered)
     *  - PalletList (LTL + Partnered) - array, see `getPalletList()` for details
     *  - TotalWeight (LTL + Partnered) - array with keys "Value" and "Unit"
     *  - SellerDeclaredValue (optional, LTL + Partnered) - array with keys "Value" and "CurrencyCode"
     *  - AmazonCalculatedValue (optional, LTL + Partnered) - array with keys "Value" and "CurrencyCode"
     *  - PreviewPickupDate (LTL + Partnered)
     *  - PreviewDeliveryDate (LTL + Partnered)
     *  - PreviewFreightClass (LTL + Partnered)
     *  - AmazonReferenceId (LTL + Partnered)
     *  - IsBillOfLadingAvailable (LTL + Partnered)
     *
     * This method will return FALSE if the data has not been set yet.
     * @return array|boolean multi-dimensional array, or FALSE if data not set yet
     * @see getPackageList
     * @see getPalletList
     */
    public function getContentDetails()
    {
        if (isset($this->contents['Details'])) {
            return $this->contents['Details'];
        } else {
            return false;
        }
    }

    /**
     * Returns the seller ID for the transport request.
     *
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getSellerId()
    {
        if (isset($this->contents['SellerId'])) {
            return $this->contents['SellerId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the shipment ID for the transport request.
     *
     * This should be the same as the value that was sent when creating the transport request.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getShipmentId()
    {
        if (isset($this->contents['ShipmentId'])) {
            return $this->contents['ShipmentId'];
        } else {
            return false;
        }
    }

    /**
     * Returns whether or not the transport is with a partnered carrier.
     *
     * This should be the same as the value that was sent when creating the transport request.
     * Note that this method will return the string "false" if Amazon indicates
     * that the shipment's carrier is not partnered.
     * This method will return boolean FALSE if the value has not been set yet.
     * @return string|boolean "true" or "false", or FALSE if value not set yet
     */
    public function getIsPartnered()
    {
        if (isset($this->contents['IsPartnered'])) {
            return $this->contents['IsPartnered'];
        } else {
            return false;
        }
    }

    /**
     * Returns the shipment type for the transport request.
     *
     * This should be the same as the value that was sent when creating the transport request.
     * The possible values are "SP" and "LTL".
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean "SP" or "LTL", or FALSE if value not set yet
     */
    public function getShipmentType()
    {
        if (isset($this->contents['ShipmentType'])) {
            return $this->contents['ShipmentType'];
        } else {
            return false;
        }
    }

    /**
     * Returns the list of package data for the transport request.
     *
     * This value will only be set if the shipment type is set to "SP" for Small Parcel.
     * Most of the data should be the same as the data that was sent when creating the transport request.
     * The returned array may have the following fields:
     *
     *  - TrackingId
     *  - PackageStatus - "SHIPPED", "IN_TRANSIT", "DELIVERED", "CHECKED_IN", "RECEIVING", or "CLOSED"
     *  - CarrierName - see `setCarrier()` for a list of possible carrier names
     *  - Weight (partnered only) - array
     *
     *  - Value - positive integer
     *  - Unit - "pounds" or "kilograms"
     *
     *  - Dimensions (partnered only) - array
     *
     *  - Length - positive decimal number
     *  - Width - positive decimal number
     *  - Height - positive decimal number
     *  - Unit - "inches" or "centimeters"
     *
     *
     * This method will return FALSE if the value has not been set yet.
     * @return array|boolean multi-dimensional array, or FALSE if data not set yet
     * @see setCarrier
     */
    public function getPackageList()
    {
        if (isset($this->contents['Details']['PackageList'])) {
            return $this->contents['Details']['PackageList'];
        } else {
            return false;
        }
    }

    /**
     * Returns the estimated cost data from the partnered carrier for the transport request.
     *
     * This data includes the carrier's estimated shipping charge, the deadline for when
     * the transport request must be confirmed (if it has not already been confirmed), and
     * the deadline for when the request can be voided.
     * This value will only be set if the shipment is with an Amazon-partnered carrier.
     * The returned array will have the following fields:
     *
     *  - Amount - array
     *
     *  - Value
     *  - CurrencyCode - ISO 4217 currency code
     *
     *  - ConfirmDeadline (optional) - ISO 8601 date format
     *  - VoidDeadline (optional) - ISO 8601 date format
     *
     * This method will return FALSE if the value has not been set yet.
     * @return array|boolean multi-dimensional array, or FALSE if data not set yet
     */
    public function getPartneredEstimate()
    {
        if (isset($this->contents['Details']['PartneredEstimate'])) {
            return $this->contents['Details']['PartneredEstimate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the name of the carrier for the transport request.
     *
     * This value will only be set if the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the value that was sent when creating the transport request.
     * See `setCarrier()` for a list of possible values.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     * @see setCarrier
     */
    public function getCarrier()
    {
        if (isset($this->contents['Details']['CarrierName'])) {
            return $this->contents['Details']['CarrierName'];
        } else {
            return false;
        }
    }

    /**
     * Returns the PRO number for the transport request.
     *
     * This value will only be set if the shipment is with a non-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the value that was sent when creating the transport request.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getProNumber()
    {
        if (isset($this->contents['Details']['ProNumber'])) {
            return $this->contents['Details']['ProNumber'];
        } else {
            return false;
        }
    }

    /**
     * Returns the contact information for the transport request.
     *
     * This data includes the carrier's estimated shipping charge, the deadline for when
     * the transport request must be confirmed (if it has not already been confirmed), and
     * the deadline for when the request can be voided.
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the value that was sent when creating the transport request.
     * The returned array will have the following fields:
     *
     *  - Name
     *  - Phone
     *  - Email
     *  - Fax
     *
     * This method will return FALSE if the value has not been set yet.
     * @return array|boolean multi-dimensional array, or FALSE if value not set yet
     */
    public function getContact()
    {
        if (isset($this->contents['Details']['Contact'])) {
            return $this->contents['Details']['Contact'];
        } else {
            return false;
        }
    }

    /**
     * Returns the number of boxes for the transport request.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the value that was sent when creating the transport request.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getBoxCount()
    {
        if (isset($this->contents['Details']['BoxCount'])) {
            return $this->contents['Details']['BoxCount'];
        } else {
            return false;
        }
    }

    /**
     * Returns the freight class for the transport request.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the value that was sent when creating the transport request.
     * If the freight class was not sent before, this is Amazon's estimated freight class
     * based on the description of the contents.
     * See `setFreightClass()` for a list of possible values.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     * @see setFreightClass
     */
    public function getFreightClass()
    {
        if (isset($this->contents['Details']['PreviewFreightClass'])) {
            return $this->contents['Details']['PreviewFreightClass'];
        } else if (isset($this->contents['Details']['SellerFreightClass'])) {
            return $this->contents['Details']['SellerFreightClass'];
        } else {
            return false;
        }
    }

    /**
     * Returns the date by which the shipment will be ready to be picked up.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the value that was sent when creating the transport request.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean date in YYYY-MM-DD format, or FALSE if value not set yet
     */
    public function getReadyDate()
    {
        if (isset($this->contents['Details']['FreightReadyDate'])) {
            return $this->contents['Details']['FreightReadyDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the list of pallet data for the transport request.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the data that was sent when creating the transport request.
     * The returned array may have the following fields:
     *
     *  - IsStacked - "true" or "false"
     *  - Weight (optional) - array
     *
     *  - Value - positive integer
     *  - Unit - "pounds" or "kilograms"
     *
     *  - Dimensions - array
     *
     *  - Length - positive decimal number
     *  - Width - positive decimal number
     *  - Height - positive decimal number
     *  - Unit - "inches" or "centimeters"
     *
     *
     * This method will return FALSE if the value has not been set yet.
     * @return array|boolean multi-dimensional array, or FALSE if data not set yet
     * @see setCarrier
     */
    public function getPalletList()
    {
        if (isset($this->contents['Details']['PalletList'])) {
            return $this->contents['Details']['PalletList'];
        } else {
            return false;
        }
    }

    /**
     * Returns the total weight for the transport request.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the data that was sent when creating the transport request.
     * If an array is returned, it will have the keys Value and Unit.
     * This method will return FALSE if the value has not been set yet.
     * @param boolean $only [optional] set to TRUE to get only the value
     * @return array|string|boolean array, single value, or FALSE if value not set yet
     */
    public function getTotalWeight($only = false)
    {
        if (isset($this->contents['Details']['TotalWeight'])) {
            if ($only) {
                return $this->contents['Details']['TotalWeight']['Value'];
            } else {
                return $this->contents['Details']['TotalWeight'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the seller's declared value for the transport request.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This should be the same as the data that was sent when creating the transport request.
     * If an array is returned, it will have the fields Value and CurrencyCode.
     * This method will return FALSE if the value has not been set yet.
     * @param boolean $only [optional] set to TRUE to get only the value
     * @return array|string|boolean array, single value, or FALSE if value not set yet
     */
    public function getDeclaredValue($only = false)
    {
        if (isset($this->contents['Details']['SellerDeclaredValue'])) {
            if ($only) {
                return $this->contents['Details']['SellerDeclaredValue']['Value'];
            } else {
                return $this->contents['Details']['SellerDeclaredValue'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns Amazon's calculated value for the transport request.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * If an array is returned, it will have the fields Value and CurrencyCode.
     * This method will return FALSE if the value has not been set yet.
     * @param boolean $only [optional] set to TRUE to get only the value
     * @return array|string|boolean array, single value, or FALSE if value not set yet
     */
    public function getCalculatedValue($only = false)
    {
        if (isset($this->contents['Details']['AmazonCalculatedValue'])) {
            if ($only) {
                return $this->contents['Details']['AmazonCalculatedValue']['Value'];
            } else {
                return $this->contents['Details']['AmazonCalculatedValue'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the estimated pickup date for the transport request.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean date in ISO 8601 format, or FALSE if value not set yet
     */
    public function getPickupDate()
    {
        if (isset($this->contents['Details']['PreviewPickupDate'])) {
            return $this->contents['Details']['PreviewPickupDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the estimated date for when the shipment will be delivered to an Amazon fulfillment center.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean date in ISO 8601 format, or FALSE if value not set yet
     */
    public function getDeliveryDate()
    {
        if (isset($this->contents['Details']['PreviewDeliveryDate'])) {
            return $this->contents['Details']['PreviewDeliveryDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Amazon-generated reference ID for the shipment.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This method will return FALSE if the value has not been set yet.
     * @return string|boolean single value, or FALSE if value not set yet
     */
    public function getReferenceId()
    {
        if (isset($this->contents['Details']['AmazonReferenceId'])) {
            return $this->contents['Details']['AmazonReferenceId'];
        } else {
            return false;
        }
    }

    /**
     * Returns whether or not the bill of lading for the shipment is available.
     *
     * This value will only be set if the shipment is with an Amazon-partnered carrier and
     * the shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * Note that this method will return the string "false" if Amazon indicates
     * that the bill of lading is not available.
     * This method will return boolean FALSE if the value has not been set yet.
     * @return string|boolean "true" or "false", or FALSE if value not set yet
     */
    public function getIsBillOfLadingAvailable()
    {
        if (isset($this->contents['Details']['IsBillOfLadingAvailable'])) {
            return $this->contents['Details']['IsBillOfLadingAvailable'];
        } else {
            return false;
        }
    }

}
