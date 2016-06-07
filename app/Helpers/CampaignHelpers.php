<?php

namespace App\Helpers;

class CampaignHelpers
{

    public function getAppNexusProfileFrequencyData($inventory, $data, $campaignId) {

        if (empty($inventory)) {
            return null;
        }

        if (empty($data)) {
            $data = new \stdClass();
        }

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
}
