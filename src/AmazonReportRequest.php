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
 * Sends a report request to Amazon.
 *
 * This AmazonReportsCore object makes a request to Amazon to generate a report.
 * In order to do this, a report type is required. Other parameters are also
 * available to limit the scope of the report.
 */
class AmazonReportRequest extends AmazonReportsCore
{
    /**
     * Response data
     *
     * @var array       Associative array
     */
    protected $response;

    /**
     * AmazonReportRequest sends a report request to Amazon.
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

        $this->options['Action'] = 'RequestReport';

        if (isset($THROTTLE_LIMIT_REPORTREQUEST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTREQUEST;
        }
        if (isset($THROTTLE_TIME_REPORTREQUEST)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTREQUEST;
        }
        $this->throttleGroup = 'RequestReport';
    }

    /**
     * Sets the report type. (Required)
     *
     * This method sets the report type to be sent in the next request.
     * This parameter is required for fetching the report from Amazon.
     * @param string|integer $s See comment inside for a list of valid values.
     * @return boolean FALSE if improper input
     */
    public function setReportType($s)
    {
        if (is_string($s) && $s) {
            $this->options['ReportType'] = $s;
        } else {
            return false;
        }
        /*
         * List of valid Report Types:
         * Listings Reports:
         *      Open Listings Report ~ _GET_FLAT_FILE_OPEN_LISTINGS_DATA_
         *      Open Listings Report ~ _GET_MERCHANT_LISTINGS_DATA_BACK_COMPAT_
         *      Merchant Listings Report ~ _GET_MERCHANT_LISTINGS_DATA_
         *      Merchant Listings Lite Report ~ _GET_MERCHANT_LISTINGS_DATA_LITE_
         *      Merchant Listings Liter Report ~ _GET_MERCHANT_LISTINGS_DATA_LITER_
         *      Canceled Listings Report ~ _GET_MERCHANT_CANCELLED_LISTINGS_DATA_
         *      Sold Listings Report ~ _GET_CONVERGED_FLAT_FILE_SOLD_LISTINGS_DATA_
         *      Quality Listing Report ~ _GET_MERCHANT_LISTINGS_DEFECT_DATA_
         * Order Reports:
         *      Unshipped Orders Report ~ _GET_FLAT_FILE_ACTIONABLE_ORDER_DATA_
         *      Scheduled XML Order Report ~ _GET_ORDERS_DATA_
         *      Requested Flat File Order Report ~ _GET_FLAT_FILE_ORDERS_DATA_
         *      Flat File Order Report ~ _GET_CONVERGED_FLAT_FILE_ORDER_REPORT_DATA_
         * Order Tracking Reports:
         *      Flat File Orders By Last Update Report ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      Flat File Orders By Order Date Report ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_
         *      XML Orders By Last Update Report ~ _GET_XML_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      XML Orders By Order Date Report ~ _GET_XML_ALL_ORDERS_DATA_BY_ORDER_DATE_
         * Pending Order Reports:
         *      Flat File Pending Orders Report ~ _GET_FLAT_FILE_PENDING_ORDERS_DATA_
         *      XML Pending Orders Report ~ _GET_PENDING_ORDERS_DATA_
         *      Converged Flat File Pending Orders Report ~ _GET_CONVERGED_FLAT_FILE_PENDING_ORDERS_DATA_
         * Performance Reports:
         *      Flat File Feedback Report ~ _GET_SELLER_FEEDBACK_DATA_
         *      XML Customer Metrics Report ~ _GET_V1_SELLER_PERFORMANCE_REPORT_
         * Settlement Reports:
         *      Flat File Settlement Report ~ _GET_V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_
         *      XML Settlement Report ~ _GET_V2_SETTLEMENT_REPORT_DATA_XML_
         *      Flat File V2 Settlement Report ~ _GET_V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_V2_
         * FBA Sales Reports:
         *      FBA Fulfilled Shipments Report ~ _GET_AMAZON_FULFILLED_SHIPMENTS_DATA_
         *      Flat File All Orders Report by Last Update ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      Flat File All Orders Report by Order Date ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_
         *      XML All Orders Report by Last Update ~ _GET_XML_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      XML All Orders Report by Order Date ~ _GET_XML_ALL_ORDERS_DATA_BY_ORDER_DATE_
         *      FBA Customer Shipment Sales Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_SALES_DATA_
         *      FBA Promotions Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_PROMOTION_DATA_
         *      Customer Taxes ~ _GET_FBA_FULFILLMENT_CUSTOMER_TAXES_DATA_
         * FBA Inventory Reports:
         *      FBA Inventory Report ~ _GET_AFN_INVENTORY_DATA_
         *      FBA Multi-Country Inventory Report ~ _GET_AFN_INVENTORY_DATA_BY_COUNTRY_
         *      FBA Daily Inventory History Report ~ _GET_FBA_FULFILLMENT_CURRENT_INVENTORY_DATA_
         *      FBA Monthly Inventory History Repoty ~ _GET_FBA_FULFILLMENT_MONTHLY_INVENTORY_DATA_
         *      FBA Received Inventory Report ~ _GET_FBA_FULFILLMENT_INVENTORY_RECEIPTS_DATA_
         *      FBA Reserved Inventory Report ~ _GET_RESERVED_INVENTORY_DATA_
         *      FBA Inventory Event Detail Report ~ _GET_FBA_FULFILLMENT_INVENTORY_SUMMARY_DATA_
         *      FBA Inventory Adjustments Report ~ _GET_FBA_FULFILLMENT_INVENTORY_ADJUSTMENTS_DATA_
         *      FBA Inventory Health Report ~ _GET_FBA_FULFILLMENT_INVENTORY_HEALTH_DATA_
         *      FBA Manage Inventory ~ _GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_
         *      FBA Manage Inventory - Archived ~ _GET_FBA_MYI_ALL_INVENTORY_DATA_
         *      FBA Cross-Border Inventory Movement Report ~ _GET_FBA_FULFILLMENT_CROSS_BORDER_INVENTORY_MOVEMENT_DATA_
         *      FBA Inbound Compliance Report ~ _GET_FBA_FULFILLMENT_INBOUND_NONCOMPLIANCE_DATA_
         * FBA Payments Reports:
         *      FBA Fee Preview Report ~ _GET_FBA_ESTIMATED_FBA_FEES_TXT_DATA_
         *      FBA Reimbursements Report ~ _GET_FBA_REIMBURSEMENTS_DATA_
         * FBA Customer Concessions Reports:
         *      FBA Returns Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_
         *      FBA Replacements Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_REPLACEMENT_DATA_
         * FBA Removals Reports:
         *      FBA Recommended Removal Report ~ _GET_FBA_RECOMMENDED_REMOVAL_DATA_
         *      FBA Removal Order Detail Report ~ _GET_FBA_FULFILLMENT_REMOVAL_ORDER_DETAIL_DATA_
         *      FBA Removal Shipment Detail Report ~ _GET_FBA_FULFILLMENT_REMOVAL_SHIPMENT_DETAIL_DATA_
         * Other:
         *      Sales Tax Report ~ _GET_FLAT_FILE_SALES_TAX_DATA_
         *      Browse Tree Report ~ _GET_XML_BROWSE_TREE_DATA_
         */
    }

    /**
     * Sets the time frame options. (Optional)
     *
     * This method sets the start and end times for the report request. If this
     * parameter is set, the report will only contain data that was updated
     * between the two times given. If these parameters are not set, the report
     * will only contain the most recent data.
     * The parameters are passed through `strtotime()`, so values such as "-1 hour" are fine.
     * @param string $s [optional] A time string for the earliest time.
     * @param string $e [optional] A time string for the latest time.
     */
    public function setTimeLimits($s = null, $e = null)
    {
        if ($s && is_string($s)) {
            $times = $this->genTime($s);
            $this->options['StartDate'] = $times;
        }
        if ($e && is_string($e)) {
            $timee = $this->genTime($e);
            $this->options['EndDate'] = $timee;
        }
        if (isset($this->options['StartDate']) &&
            isset($this->options['EndDate']) &&
            $this->options['StartDate'] > $this->options['EndDate']) {
            $this->setTimeLimits($this->options['EndDate'] . ' - 1 second');
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
        unset($this->options['StartDate']);
        unset($this->options['EndDate']);
    }

    /**
     * Sets whether or not the report should return the Sales Channel column. (Optional)
     *
     * Setting this parameter to TRUE adds the Sales Channel column to the report.
     * @param string|boolean $s "true" or "false", or boolean
     * @return boolean FALSE if improper input
     */
    public function setShowSalesChannel($s)
    {
        if ($s == 'true' || (is_bool($s) && $s == true)) {
            $this->options['ReportOptions'] = 'ShowSalesChannel=true';
        } else if ($s == 'false' || (is_bool($s) && $s == false)) {
            $this->options['ReportOptions'] = 'ShowSalesChannel=false';
        } else {
            return false;
        }
    }

    /**
     * Sets the marketplace ID(s). (Optional)
     *
     * This method sets the list of marketplace IDs to be sent in the next request.
     * If this parameter is set, the report will only contain data relevant to the
     * marketplaces listed.
     * @param array|string $s A list of marketplace IDs, or a single ID string.
     * @return boolean FALSE if improper input
     */
    public function setMarketplaces($s)
    {
        if (is_string($s)) {
            $this->resetMarketplaces();
            $this->options['MarketplaceIdList.Id.1'] = $s;
        } else if (is_array($s)) {
            $this->resetMarketplaces();
            $i = 1;
            foreach ($s as $x) {
                $this->options['MarketplaceIdList.Id.' . $i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Removes marketplace ID options.
     *
     * Use this in case you change your mind and want to remove the Marketplace ID
     * parameters you previously set.
     */
    public function resetMarketplaces()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#MarketplaceIdList#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sends a report request to Amazon.
     *
     * Submits a `RequestReport` request to Amazon. In order to do this,
     * a Report Type is required. Amazon will send info back as a response,
     * which can be retrieved using `getResponse()`.
     * Other methods are available for fetching specific values from the list.
     * @return boolean FALSE if something goes wrong
     */
    public function requestReport()
    {
        if (!array_key_exists('ReportType', $this->options)) {
            $this->log("Report Type must be set in order to request a report!", 'Warning');
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

        $this->parseXML($xml->ReportRequestInfo);
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

        $this->response = array();
        $this->response['ReportRequestId'] = (string)$xml->ReportRequestId;
        $this->response['ReportType'] = (string)$xml->ReportType;
        $this->response['StartDate'] = (string)$xml->StartDate;
        $this->response['EndDate'] = (string)$xml->EndDate;
        $this->response['Scheduled'] = (string)$xml->Scheduled;
        $this->response['SubmittedDate'] = (string)$xml->SubmittedDate;
        $this->response['ReportProcessingStatus'] = (string)$xml->ReportProcessingStatus;
    }

    /**
     * Returns the full response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * The returned array will have the following fields:
     *
     *  - ReportRequestId
     *  - ReportType
     *  - StartDate
     *  - EndDate
     *  - Scheduled - "true" or "false"
     *  - SubmittedDate
     *  - ReportProcessingStatus
     *
     * @return array|boolean data array, or FALSE if list not filled yet
     */
    public function getResponse()
    {
        if (isset($this->response)) {
            return $this->response;
        } else {
            return false;
        }
    }

    /**
     * Returns the report request ID from the response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getReportRequestId()
    {
        if (isset($this->response)) {
            return $this->response['ReportRequestId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the report type from the response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getReportType()
    {
        if (isset($this->response)) {
            return $this->response['ReportType'];
        } else {
            return false;
        }
    }

    /**
     * Returns the start date for the report from the response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getStartDate()
    {
        if (isset($this->response)) {
            return $this->response['StartDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the end date for the report from the response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getEndDate()
    {
        if (isset($this->response)) {
            return $this->response['EndDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns whether or not the report is scheduled from the response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean "true" or "false", or FALSE if Non-numeric index
     */
    public function getIsScheduled()
    {
        if (isset($this->response)) {
            return $this->response['Scheduled'];
        } else {
            return false;
        }
    }

    /**
     * Returns the date the report was submitted from the response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getSubmittedDate()
    {
        if (isset($this->response)) {
            return $this->response['SubmittedDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the report processing status from the response.
     *
     * This method will return FALSE if the response data has not yet been filled.
     * @param int $i [optional] List index to retrieve the value from. Defaults to 0.
     * @return string|boolean single value, or FALSE if Non-numeric index
     */
    public function getStatus()
    {
        if (isset($this->response)) {
            return $this->response['ReportProcessingStatus'];
        } else {
            return false;
        }
    }

}
