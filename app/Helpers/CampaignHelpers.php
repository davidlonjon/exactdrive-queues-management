<?php

namespace App\Helpers;

class CampaignHelpers
{

    /**
     * Get AppNexus profile frequency data
     *
     * @param  object $inventory  Inventory
     * @param  object $data       AppNexus sync data
     * @param  string $campaignId Campaign id
     *
     * @return object             AppNexus sync data
     */
    public function getAppNexusProfileFrequencyData($inventory, $data, $campaignId) {

        if (empty($inventory)) {
            return null;
        }

        if (empty($data)) {
            $data = new \stdClass();
        }

        // TODO Implement query caching
        $frequency = \DB::table('frequencies')
            ->where('campaignId', $campaignId)
            ->first();

        if (!empty($frequency) && $frequency->applyState == 'enabled') {

            if ($frequency->lifetimeState == 'enabled'
                 && $frequency->lifetimeImpressions > 0) {
                $data->max_lifetime_imps = (int) $frequency->lifetimeImpressions;
            }

            if ($frequency->perUserState == 'enabled'
                 && $frequency->perUserPerDayImpressions > 0) {
                $data->max_day_imps = (int) $frequency->perUserPerDayImpressions;
            }

            if ($frequency->perUserPerTimeState == 'enabled'
                 && $frequency->perUserPerTimeAmount > 0) {

                switch ($frequency->perUserPerTimeType) {
                    case 'minutes':
                        $data->min_minutes_per_imp = (int) $frequency->perUserPerTimeAmount;
                        break;

                    case 'hours':
                        $data->min_minutes_per_imp = (int) $frequency->perUserPerTimeAmount * 60;
                        break;

                    case 'days':
                        $data->min_minutes_per_imp = (int) $frequency->perUserPerTimeAmount * 1440;
                        break;

                    default:
                        $data->min_minutes_per_imp = (int) 0;
                        break;
                }
            }
        }

        return $data;
    }

    /**
     * Get AppNexus campaign geographical targeting profile
     *
     * @param  object $data     AppNexus sync data
     * @param  object $campaign Campaign
     *
     * @return data           AppNexus sync data
     */
    public function getAppNexusProfileGeographyData($data, $campaign)
    {
        if (empty($data)) {
            $data = new stdClass();
        }

        $countries = $this->getAppNexusCountryIds($campaign);
        $regions = $this->getAppNexusRegions($campaign);
        $demographicIds = $this->getAppNexusDemographicMarketAreaIds($campaign);
        $cityIds = $this->getAppNexusCityIds($campaign);
        $zipCodes = $this->getZipCodes($campaign);

        // Add Country Targets
        // Default: US
        $data->country_action = 'include';
        if (count($countries)) {
            $data->country_targets = array();
            foreach ($countries as $country) {
                array_push($data->country_targets, (object) array('id' => $country));
            }
        } else {
            $data->country_targets = array((object) array('id' => 233)); // US
        }

        // Add Region Targets
        // Default: Any region
        if (!empty($regions)) {
            $data->region_action = 'include';
            $data->region_targets = array();
            foreach ($regions as $region) {
                array_push($data->region_targets, (object) array('id' => $region));
            }
        }

        // Add Demographic Market Area Targets
        // Default: Any DMA
        if (!empty($demographicIds)) {
            $data->dma_action = 'include';
            $data->dma_targets = array();
            foreach ($demographicIds as $demographicId) {
                array_push($data->dma_targets, (object) array('dma' => (int) $demographicId));
            }
        }

        // Add City Targets
        // Default: Any city
        if (!empty($cityIds)) {
            $data->city_action = 'include';
            $data->city_targets = array();
            foreach ($cityIds as $cityId) {
                array_push($data->city_targets, (object) array('id' => (int) $cityId));
            }
        }

        // Add Zip Code Targets
        // Default: Any zip code
        if (!empty($zipCodes)) {
            $data->zip_targets = array();
            foreach ($zipCodes as $zipCode) {
                array_push($data->zip_targets, (object) array('from_zip' => "$zipCode", 'to_zip' => "$zipCode"));
            }
        }

        return $data;
    }

    /**
     * Get AppNexus country ids.
     *
     * @param  object $campaign Campaign
     *
     * @return array           AppNexus country ids
     */
    public function getAppNexusCountryIds($campaign)
    {
        // TODO Implement query caching
        return \DB::table('countries')
            ->whereIn('id', explode(',', $campaign->countries))
            ->lists('appNexusCountryId');
    }

    /**
     * Get AppNexus state ids.
     *
     * @param  object $campaign Campaign
     *
     * @return array           AppNexus state ids
     */
    public function getAppNexusRegions($campaign)
    {
        // TODO Implement query caching
        return \DB::table('states')
            ->whereIn('id', explode(',', $campaign->states))
            ->lists('appNexusStateId');
    }

    /**
     * Get AppNexus demographic market area
     *
     * @param  object $campaign Campaign
     *
     * @return array           AppNexus demographic market area
     */
    public function getAppNexusDemographicMarketAreaIds($campaign)
    {
        // TODO Implement query caching
        return \DB::table('demographics')
            ->whereIn('id', explode(',', $campaign->demographicMarketArea))
            ->lists('appNexusDemographicAreaId');
    }

    /**
     * Get AppNexus city ids.
     *
     * @param  object $campaign Campaign
     *
     * @return array           AppNexus city ids
     */
    public function getAppNexusCityIds($campaign)
    {
        // TODO Implement query caching
        return \DB::table('cities')
            ->whereIn('id', explode(',', $campaign->cities))
            ->lists('appNexusCityId');
    }

    /**
     * Get campaign zip codes
     *
     * @param  object $campaign Campaign
     *
     * @return array           Campaign zip codes
     */
    public function getZipCodes($campaign)
    {
        $check = preg_match('/,/', $campaign->zipCodes);
        if (!empty($check)) {
            $zipCodes = explode(',', preg_replace('/\s+/', '', $campaign->zipCodes));
        } else {
            $zipCodes = explode(',', preg_replace('/\s+/', '', preg_replace('/\r\n/', ',', $campaign->zipCodes)));
        }

        foreach($zipCodes as $key => $value) {
            if (empty($value)) {
                unset($zipCodes[$key]);
            }
        }

        return $zipCodes;
    }
}
