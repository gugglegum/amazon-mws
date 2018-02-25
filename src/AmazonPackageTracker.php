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
 * Fetches package tracking info from Amazon.
 *
 * This Amazon Outbound Core object retrieves package tracking data
 * from Amazon. A package number is required for this.
 */
class AmazonPackageTracker extends AmazonOutboundCore
{
    /**
     * @var array       Associative array
     */
    protected $details;

    /**
     * AmazonPackageTracker fetches package tracking details from Amazon.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param array $config A config array to set.
     * @param string $id [optional] The package ID to set for the object.
     * @param boolean $mock [optional] This is a flag for enabling Mock Mode.
     * This defaults to FALSE.
     * @param array|string $m [optional] The files (or file) to use in Mock Mode.
     */
    public function __construct(array $config = null, $id = null, $mock = false, $m = null)
    {
        parent::__construct($config, $mock, $m);

        if ($id) {
            $this->setPackageNumber($id);
        }

        $this->options['Action'] = 'GetPackageTrackingDetails';
    }

    /**
     * Sets the package ID. (Required)
     *
     * This method sets the package ID to be sent in the next request.
     * This parameter is required for fetching the tracking information from Amazon.
     * @param string|integer $n Must be numeric
     * @return boolean FALSE if improper input
     */
    public function setPackageNumber($n)
    {
        if (is_numeric($n)) {
            $this->options['PackageNumber'] = $n;
        } else {
            return false;
        }
    }

    /**
     * Sends a request to Amazon for package tracking details.
     *
     * Submits a `GetPackageTrackingDetails` request to Amazon. In order to do this,
     * a package ID is required. Amazon will send
     * the data back as a response, which can be retrieved using `getDetails()`.
     * @return boolean FALSE if something goes wrong
     */
    public function fetchTrackingDetails()
    {
        if (!array_key_exists('PackageNumber', $this->options)) {
            $this->log("Package Number must be set in order to fetch it!", 'Warning');
            return false;
        }

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
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param \SimpleXMLElement $d The XML response from Amazon.
     * @return boolean FALSE if no XML data is found
     */
    protected function parseXML($d)
    {
        if (!$d) {
            return false;
        }
        $this->details['PackageNumber'] = (string)$d->PackageNumber;
        $this->details['TrackingNumber'] = (string)$d->TrackingNumber;
        $this->details['CarrierCode'] = (string)$d->CarrierCode;
        $this->details['CarrierPhoneNumber'] = (string)$d->CarrierPhoneNumber;
        $this->details['CarrierURL'] = (string)$d->CarrierURL;
        $this->details['ShipDate'] = (string)$d->ShipDate;
        //Address
        $this->details['ShipToAddress']['City'] = (string)$d->ShipToAddress->City;
        $this->details['ShipToAddress']['State'] = (string)$d->ShipToAddress->State;
        $this->details['ShipToAddress']['Country'] = (string)$d->ShipToAddress->Country;
        //End of Address
        $this->details['CurrentStatus'] = (string)$d->CurrentStatus;
        $this->details['SignedForBy'] = (string)$d->SignedForBy;
        $this->details['EstimatedArrivalDate'] = (string)$d->EstimatedArrivalDate;

        $i = 0;
        foreach ($d->TrackingEvents->children() as $y) {
            $this->details['TrackingEvents'][$i]['EventDate'] = (string)$y->EventDate;
            //Address
            $this->details['TrackingEvents'][$i]['EventAddress']['City'] = (string)$y->EventAddress->City;
            $this->details['TrackingEvents'][$i]['EventAddress']['State'] = (string)$y->EventAddress->State;
            $this->details['TrackingEvents'][$i]['EventAddress']['Country'] = (string)$y->EventAddress->Country;
            //End of Address
            $this->details['TrackingEvents'][$i]['EventCode'] = (string)$y->EventCode;
            $i++;
        }

        $this->details['AdditionalLocationInfo'] = (string)$d->AdditionalLocationInfo;
    }

    /**
     * Returns the full package tracking information.
     *
     * This method will return FALSE if the data has not yet been filled.
     * The array returned will have the following fields:
     *
     *  - PackageNumber - the same package ID you provided, hopefully
     *  - TrackingNumber - the tracking number for the package
     *  - CarrierCode - name of the carrier
     *  - CarrierPhoneNumber - the phone number of the carrier
     *  - CarrierURL - the URL of the carrier's website
     *  - ShipDate - time the package was shipped, in ISO 8601 date format
     *  - ShipToAddress - an array containing the fields City, State, and Country
     *  - CurrentStatus - delivery status of the package
     *  - SignedForBy - name of the person who signed for the package
     *  - EstimatedArrivalDate - in ISO 8601 date format
     *  - TrackingEvents - multi-dimensional array of tracking events, each with the following fields:
     *
     *  - EventDate - in ISO 8601 date format
     *  - EventAddress - an array containing the fields City, State, and Country
     *  - EventCode - a code number
     *
     *  - AdditionalLocationInfo - further information on how the package was delivered (ex: to a front door)
     *
     * @return array|boolean data array, or FALSE if data not filled yet
     */
    public function getDetails()
    {
        if (isset($this->details)) {
            return $this->details;
        } else {
            return false;
        }
    }
}
