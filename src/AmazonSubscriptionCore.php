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
 * Core class for Amazon Subscriptions API.
 *
 * This is the core class for all objects in the Amazon Subscriptions section.
 * It contains a method that all Amazon Subscriptions Core objects use.
 */
abstract class AmazonSubscriptionCore extends AmazonCore
{
    /**
     * AmazonSubscriptionCore constructor sets up key information used in all Amazon Subscriptions Core requests
     *
     * This constructor is called when initializing all objects in the Amazon Subscriptions Core.
     * The parameters are passed by the child objects' constructors, which are
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

        if (isset($AMAZON_VERSION_SUBSCRIBE)) {
            $this->urlbranch = 'Subscriptions/' . $AMAZON_VERSION_SUBSCRIBE;
            $this->options['Version'] = $AMAZON_VERSION_SUBSCRIBE;
        }

        if (isset($THROTTLE_LIMIT_SUBSCRIBE)) {
            $this->throttleLimit = $THROTTLE_LIMIT_SUBSCRIBE;
        }
        if (isset($THROTTLE_TIME_SUBSCRIBE)) {
            $this->throttleTime = $THROTTLE_TIME_SUBSCRIBE;
        }

        if (isset($this->config['store']['marketplaceId'])) {
            $this->setMarketplace($this->config['store']['marketplaceId']);
        } else {
            $this->log("Marketplace ID is missing", 'Urgent');
        }
    }

    /**
     * Sets the marketplace associated with the subscription or destination. (Optional)
     *
     * The current store's configured marketplace is used by default.
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

}
