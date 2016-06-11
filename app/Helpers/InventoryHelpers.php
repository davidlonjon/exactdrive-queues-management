<?php

namespace App\Helpers;

use App\Helpers\CampaignHelpers;
use Exactdrive\AppNexus;

class InventoryHelpers
{
    // TODO: Do implementation of sub calls
    public function getAppNexusLineItemData($inventory, $campaign)
    {
        if (empty($inventory)) {
            return null;
        }

        $campaignHelper = new CampaignHelpers();

        $data = new stdClass();
        $data->name = $this->getAppNexusName();
        $data->state = $campaign->status;
        $data->start_date = $campaignHelper->getStartDate();
        $data->end_date = $campaignHelper->getEndDate();
        $data->comments = $campaign->comments;
        $data->manage_creative = false;
        $data->performance_offer = false;

        if ($campaign->inventoryTargetingType == 'cpc') {
            $data->performance_offer = true;
            $data->manage_creative = true;
        }

        // Only sync bidding, pacing, and performance goal data if campaign
        // has been approved.
        if ($campaignHelper->isClean()) {
            $data->revenue_type = $campaign->inventoryTargetingType;
            $data->revenue_value = (double) $inventory->cpm;

            // Set pacing
            $data = $campaignHelper->getAppNexusPacing($inventory, $data);

            // Set performance goals
            $data = $campaignHelper->getAppNexusPerformanceGoals($inventory, $data);
        }

        return $data;
    }

    // TODO: Check if correctly implemented
    public function getAppNexusName($inventory, $campaign)
    {
      if ($inventory->type == 'display') {
        $inventoryType = 'categories';
      } elseif ($inventory->type == 'domain_inclusion') {
        $inventoryType = 'domain_targeting';
      } else {
        $inventoryType = $inventory->type;
      }

      return "$campaign->name + $inventoryType";
    }
}
