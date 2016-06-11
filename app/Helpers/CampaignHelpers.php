<?php

namespace App\Helpers;

use Exactdrive\AppNexus;

class CampaignHelpers
{

    /**
     * Get AppNexus profile frequency data
     *
     * @param  object $inventory  Inventory
     * @param  object $data       AppNexus sync data
     * @param  string $frequency Campaign frequency
     *
     * @return object             AppNexus sync data
     */
    public function getAppNexusProfileFrequencyData($inventory, $data, $frequency)
    {

        if (empty($inventory)) {
            return null;
        }

        if (empty($data)) {
            $data = new \stdClass();
        }

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
     * @param  array $countries      Campaign countries
     * @param  array $regions        Campaign regions
     * @param  array $demographicIds Campaign demographic ids
     * @param  array $cityIds        Campaign city ids
     * @param  array $zipCodes       Campaign zip codes
     *
     * @return data           AppNexus sync data
     */
    public function getAppNexusProfileGeographyData($data, $countries, $regions, $demographicIds, $cityIds, $zipCodes)
    {
        if (empty($data)) {
            $data = new stdClass();
        }

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
     * Get campaign frequency
     *
     * @param  object $campaign Campaign
     *
     * @return array           Frequency
     */
    public function getCampaignFrequency($campaign)
    {
        return \DB::table('frequencies')
            ->where('campaignId', $campaign->id)
            ->first();
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

    /**
     * Get AppNexus category profile
     *
     * @param  object $inventory Inventory
     * @param  object $data      AppNexus Sync data
     *
     * @return object            AppNexus Sync data
     */
    public function getAppNexusProfileCategoryData($inventory, $data)
    {
        if (empty($inventory)) {
            return null;
        }

        if (empty($data)) {
            $data = new stdClass();
        }

        $categoryIds = null;
        if (!empty($inventory->categories)) {
            $categoryIds = explode(',', $inventory->categories);
        }

        $data->content_category_targets = new stdClass();
        $data->content_category_targets->allow_unknown = false;
        $data->content_category_targets->content_categories = array();

        // Return empty content_categories array if no categories.
        if (empty($categoryIds)) {
            return $data;
        }

        foreach ($categoryIds as $categoryId) {
            // TODO: cache query
            $categoryRow = \DB::table('categories')
                ->where('id', $categoryId)
                ->first();

            if (!empty($categoryRow->appNexusCategoryId)) {
                $data->content_category_targets->content_categories[] =
                    (object) array(
                        'id'     => (int) $categoryRow->appNexusCategoryId,
                        'action' => $inventory->filter
                    );
            }
        }

        return $data;
    }

    public function getAppNexusProfileRetargetingData($inventory, $data)
    {
        if (empty($inventory)) {
            return null;
        }

        if (empty($data)) {
            $data = new stdClass();
        }

        // Todo
        // $pixels = $this->retargetingPixels();

        $data->segment_boolean_operator = 'or';
        $data->segment_targets = array();

        // Return empty segment_targets if no pixels
        if (empty($pixels)) {
            return $data;
        }

        foreach ($pixels as $pixel) {
            $appNexusData = $pixel->getAppNexusData();
            if (!empty($appNexusData)) {
                $data->segment_targets[] = (object) array(
                    'id' => (int) $appNexusData->id
                );
            }
        }

        return $data;
    }

    public function retargetingPixels($deleted = false)
    {
        // Todo
        // $pixels = $pixelTable->fetchAllRetargetingPixels($this->row->id, $deleted);
        if (count($pixels) == 0) {
            return null;
        } else {
            return array_map(function ($pixel) {
                return new AppNexus\Segment($pixel);
            }, iterator_to_array($pixels));
        }
    }

    // Todo
    // public function getAppNexusProfileFacebookData($inventory, $data)
    // {
    //     if (empty($inventory)) {
    //         return null;
    //     }

    //     if (empty($data)) {
    //         $data = new stdClass();
    //     }

    //     $facebookCategoryIds = $inventory->getCategories();

    //     $data->supply_type_action = 'include';
    //     $data->supply_type_targets = array('facebook_sidebar');
    //     $data->use_inventory_attribute_targets = false;
    //     $data->trust = 'seller';
    //     $data->inventory_action = $inventory->filter;

    //     // AppNexus complains if this property is passed when
    //     // use_inventory_attribute_targets is false.
    //     unset($data->inventory_attribute_targets);

    //     // Facebook targeting only works on the Production AppNexus API
    //     if (APPLICATION_ENV !== 'production') return $data;

    //     if (empty($facebookCategoryIds)) {
    //         $data->platform_placement_targets = array();
    //         $data->member_targets = array();
    //         $data->member_targets[] = (object) array(
    //             'id' => 1398,
    //             'action' => $inventory->filter
    //         );
    //     } else {
    //         $facebookCategoriesTable = new Zend_Db_Table('facebookCategories');
    //         $data->member_targets = array();
    //         $data->platform_placement_targets = array();

    //         foreach ($facebookCategoryIds as $facebookCategoryId) {
    //             $facebookCategory =
    //                 $facebookCategoriesTable->find($facebookCategoryId)
    //                                         ->current();

    //             $data->platform_placement_targets[] = (object) array(
    //                 'id' => $facebookCategory->appNexusPlacementId,
    //                 'action' => $inventory->filter
    //             );
    //         }
    //     }

    //     return $data;
    // }
}
