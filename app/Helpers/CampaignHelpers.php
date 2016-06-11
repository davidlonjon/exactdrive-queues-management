<?php

namespace App\Helpers;

use Exactdrive\AppNexus;

class CampaignHelpers
{

    /**
     * Get AppNexus profile frequency data.
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
     * Get AppNexus campaign geographical targeting profile.
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
     * Get campaign inventories.
     *
     * @param  int $campaignId Campaign id
     *
     * @return array             Campaign inventories
     */
    public function getCampaignInventories($campaignId)
    {
        return \DB::table('inventories')
            ->where('campaignId', $campaignId)
            ->get();
    }

    /**
     * Get campaign frequency.
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
     * Get AppNexus demographic market area.
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
     * Get campaign zip codes.
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
     * Get AppNexus category profile.
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


    // TODO
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

    // TODO
    public function retargetingPixels($deleted = false)
    {
        // TODO
        // $pixels = $pixelTable->fetchAllRetargetingPixels($this->row->id, $deleted);
        if (count($pixels) == 0) {
            return null;
        } else {
            return array_map(function ($pixel) {
                return new AppNexus\Segment($pixel);
            }, iterator_to_array($pixels));
        }
    }

    // TODO
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
    //

    /**
     * Get campaign start date.
     *
     * @param  object $campaign Campaign
     *
     * @return string           Campaign start date
     */
    public function getStartDate($campaign)
    {
      if (empty($campaign->startDate)) {
        $date = null;
      } elseif (empty($campaign->startTime)) {
        $date = "$campaign->startDate 00:00:00";
      } else {
        $date = "$campaign->startDate $campaign->startTime";
      }

      return $date;
    }

    /**
     * Get campaign end date.
     *
     * @param  object $campaign Campaign
     *
     * @return string           Campaign end date
     */
    public function getEndDate($campaign)
    {
      if (empty($campaign->endDate) || $campaign->endDate == '0000-00-00') {
        $date = null;
      } elseif (empty($campaign->endTime)) {
        $date = "$campaign->endDate 00:00:00";
      } else {
        $date = "$campaign->endDate $campaign->endTime";
      }

      return $date;
    }

    /**
    * Returns true if the campaign does not have any associated columns marked
    * as 'dirty' in the Logs table.
    *
    * @param  object $campaign Campaign
    *
    * @return bool
    */
    public function isClean($campaign)
    {
        return !$campaign->isDirty();
    }


    // TODO: Implement correctly
    public function getAppNexusPacing($inventory, $data)
    {
        if ( empty($inventory) ) return null;

        if (empty($data)) {
            $data = new stdClass();
        }

        $data->enable_pacing = false;
        $data->lifetime_pacing = false;
        $data->daily_budget_imps = null;
        $data->daily_budget = null;
        $data->lifetime_budget_imps = null;

        if ($inventory->cost > 0.0) {

            // Set revenue pacing

            $data->lifetime_budget = (float) $inventory->cost;

            if ($inventory->costDailyBudget > 0.0) {
                $data->daily_budget = (float) $inventory->costDailyBudget;

                if ($inventory->costDailyBudgetPace) {
                    $data->enable_pacing = true;
                }
            } else {
                $data->enable_pacing = true;

                if ($data->end_date !== null) {
                    $data->lifetime_pacing = true;
                }
            }

            // // Note: This is code to sync the impressions lifetime budget and
            // //       pacing. This code is commented out since we are focusing on
            // //       revenue budgeting and pacing.
            //
            // // Set impression pacing
            //
            // if ($inventory->impressionsDailyBudget > 0) {
            //     $data->daily_budget_imps = (int) $inventory->impressionsDailyBudget;
            //     $data->enable_pacing = true;
            //
            //     if ($data->end_date != null) {
            //         $data->lifetime_pacing = true;
            //     }
            // }

        }

        return $data;
    }

    // TODO: Implement correctly
    public function getAppNexusPerformanceGoals($inventory, $data)
    {
        if ( empty($inventory) ) return null;

        if (empty($data)) {
            $data = new stdClass();
        }

        switch ($this->goals) {
            case 'cpa':
                $pixel = $this->syncConversionPixelToAppNexus();
                $appNexusData = $pixel->getAppNexusData();

                $data->goal_type = 'cpa';
                $data->pixels = array(
                    (object) array(
                        'id' => $appNexusData->id,
                        'state' => $data->state
                    )
                );
                $data->goal_pixels = array(
                    (object) array(
                        'id' => $appNexusData->id,
                        'state' => $data->state,
                        'post_click_goal_target' => (double) $this->postClickCPA,
                        'post_view_goal_target' => (double) $this->postViewCPA
                    )
                );

                // Unset CPC and CTR goal settings
                $data->valuation = null;

                break;

            case 'cpc':
                $data->goal_type = 'cpc';
                $data->valuation = (object) array(
                    'goal_target' => (double) $this->cpc
                );

                // Unset CPA goal settings
                $data->goal_pixels = null;
                $data->pixels = null;

                break;

            case 'ctr':
                $ctr_value = (double) $this->ctr / 100.00;

                if (!empty($ctr_value)) {
                    $data->goal_type = 'ctr';
                    $data->valuation = (object) array(
                        'goal_target' => $ctr_value
                    );
                }

                // Unset CPA goal settings
                $data->goal_pixels = null;
                $data->pixels = null;

                break;

            default:
                $data->goal_type = 'none';
                $data->valuation = null;
                $data->goal_pixels = null;
                $data->pixels = null;
                break;
        }

        return $data;
    }
}
