<?php

namespace App\Jobs;

use App\Helpers\CampaignHelpers;
use App\Helpers\QueueJobsLogingHelpers as LogHelper;
use Exactdrive\AppNexus;

class AppNexusCampaignJob extends AppNexusBaseJob
{
    /**
     * Job payload.
     *
     * @var array
     */
    private $payload;

    /**
     * Log helper.
     *
     * @var object
     */
    private $logHelper;

    /**
     * Campaign helper.
     *
     * @var object
     */
    private $campaignHelper;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
        $this->logHelper = new LogHelper();
        $this->campaignHelper = new CampaignHelpers();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->configureAppNexus();

        $response = $this->createCoreResponse($this->payload);

        if (!isset($this->payload['body'], $this->payload['body']['action'], $this->payload['body']['data'])) {
            $response['code'] = 'malformedPayload';
            $response['message'] = 'The payload is malformed';

            $this->dispatchError($this->logHelper, $response);
        }

        $this->logHelper->updateJobLog($this->payload['uuid'], $response['code'], $response['message'], 'running');

        $jobAction = $this->payload['body']['action'];
        switch ($jobAction) {
            case 'syncAppNexusDomains':
                $response = $this->syncAppNexusDomains($this->payload);
                break;

            case 'syncAppNexusCampaignProfile':
                $response = $this->syncAppNexusCampaignProfile($this->payload);
                break;

            // case 'syncAppNexusLineItem':
            //     $response = $this->syncAppNexusLineItem($this->payload);
            //     break;

            // case 'syncAppNexusCampaign':
            //     $response = $this->syncAppNexusCampaign($this->payload);
            //     break;

            // case 'syncStatus':
            //     $response = $this->syncStatus($this->payload);
            //     break;

            // case 'deleteCampaign':
            //     $response = $this->deleteCampaign($this->payload);
            //     break;

            default:
                break;
        }

        $this->logHelper->updateJobLog($this->payload['uuid'], $response['code'], $response['message'], $response['status']);

        if (app()->environment('local')) {
            dump($response);
        }
    }

    /**
     * Job to sync campaign domains.
     *
     * @param array $payload Payload
     *
     * @return Array          Job response
     */
    private function syncAppNexusDomains($payload = array())
    {
        $response = $this->createCoreResponse($payload);

        $campaignId = $this->sanitizeCampaignIdParam($payload);

        $campaign = $this->getCampaign($campaignId);

        $preChecksData = $this->campaignPreSyncChecks($campaign, $this->campaignHelper);
        if ('failed' === $preChecksData['status'] && !empty($preChecksData['code'])) {
            $response = $preChecksData;
            return $response;
        }

        foreach (array('include', 'exclude') as $inventoryUrlFilter) {
            // Build domain array from list of domains in campaign.
            $inventoryUrlProperty = $inventoryUrlFilter."InventoryUrls";
            $domains = $campaign->{$inventoryUrlProperty};
            $domains = preg_replace('/[^A-Za-z0-9\-\.\n]/', '', trim($domains));
            $domainsArray = explode("\n", $domains);

            if (!empty($domainsArray)) {
                // Build data object.
                $data = new \stdClass();
                $data->name = "$campaign->name $inventoryUrlFilter list";
                $data->description = "Domains to $inventoryUrlFilter from campaign $campaign->name";

                $data->domains = $domainsArray;

                if ($inventoryUrlFilter == 'exclude') {
                    $data->type = 'black';
                    $appNexusDomainListId = 'appNexusExcludeDomainListId';
                } else {
                    $data->type = 'white';
                    $appNexusDomainListId = 'appNexusIncludeDomainListId';
                }

                $fieldsToUpdate = array(
                        'lastSyncedWithAppNexus' => date('Y-m-d H:i:s')
                );

                if (empty($campaign->{$appNexusDomainListId})) {
                    $AppNexusResponse = AppNexus\DomainListService::addDomainList($data);
                    $fieldsToUpdate[$appNexusDomainListId] = $AppNexusResponse->id;
                } else {
                    $AppNexusResponse = AppNexus\DomainListService::updateDomainList($campaign->{$appNexusDomainListId}, $data);
                }

                $this->updateCampaignInDb($campaign, $fieldsToUpdate, $response);
            }
        }

        $response['status'] = 'complete';
        $response['code'] = 'jobCompleted';
        $response['message'] = 'AppNexus campaign domains synced';
        $response['data'] = $campaign;
        return $response;
    }

    /**
     * Job to sync campaign profile.
     *
     * @param array $payload Payload
     *
     * @return Array          Job response
     */
    private function syncAppNexusCampaignProfile($payload = array())
    {
        $response = $this->createCoreResponse($payload);

        $campaignId = $this->sanitizeCampaignIdParam($payload);

        $campaign = $this->getCampaign($campaignId);

        $preChecksData = $this->campaignPreSyncChecks($campaign, $this->campaignHelper);
        if ('failed' === $preChecksData['status'] && !empty($preChecksData['code'])) {
            $response = $preChecksData;
            return $response;
        }

        $frequency = $this->campaignHelper->getCampaignFrequency($campaign);
        $countries = $this->campaignHelper->getAppNexusCountryIds($campaign);
        $regions = $this->campaignHelper->getAppNexusRegions($campaign);
        $demographicIds = $this->campaignHelper->getAppNexusDemographicMarketAreaIds($campaign);
        $cityIds = $this->campaignHelper->getAppNexusCityIds($campaign);
        $zipCodes = $this->campaignHelper->getZipCodes($campaign);

        foreach ($preChecksData['data']['inventories'] as $inventory) {
            if ($inventory->cost > 0) {
                //
                // Configure campaign profile data.
                //
                $data = new \stdClass();

                // Shared inventory settings.
                $data->trust = 'appnexus';
                $data->certified_supply = false;
                $data->allow_unaudited = false;
                $data->intended_audience_targets = array(
                    'general',
                    'children',
                    'young_adult',
                    'mature'
                );

                $data->use_inventory_attribute_targets = true;
                $data->inventory_attribute_targets = array(
                    (object) array('id' => 2),
                    (object) array('id' => 4),
                    (object) array('id' => 6),
                    (object) array('id' => 8),
                    (object) array('id' => 10),
                    (object) array('id' => 16)
                );

                // Campaign frequency profile
                $data = $this->campaignHelper->getAppNexusProfileFrequencyData($inventory, $data, $frequency);

                // Campaign geographical targeting profile
                $data = $this->campaignHelper->getAppNexusProfileGeographyData($data, $countries, $regions, $demographicIds, $cityIds, $zipCodes);

                //
                // Campaign Profile Inventory Targeting
                //
                // if ($inventory->type == 'display') {
                //     $data = $this->campaignHelper->getAppNexusProfileCategoryData(
                //         $inventory,
                //         $data
                //    );
                // } elseif ($inventory->type == 'retargeting') {
                //    $data = $campaign->getAppNexusProfileRetargetingData(
                //        $inventory,
                //        $data
                //    );
                // } elseif ($inventory->type == 'mobile') {
                //    $data = $campaign->getAppNexusProfileCategoryData(
                //        $inventory,
                //        $data
                //    );
                //    $data->device_type_action = 'include';
                //    $data->device_type_targets = array(
                //        'phone',
                //        'tablet'
                //    );
                // } elseif ($inventory->type == 'facebook') {
                //    $data = $campaign->getAppNexusProfileFacebookData(
                //        $inventory,
                //        $data
                //    );
                // } elseif ($inventory->type == 'domain_inclusion') {

                //    // If inventoryUrlFilter is NULL or 'advertise', include
                //    // domain list. Otherwise, exclude domain list.
                //    if ($campaign->inventoryUrlFilter == 'exclude') {
                //        if (!empty($campaign->appNexusExcludeDomainListId)) {
                //            $data->domain_list_action = 'exclude';
                //            $data->domain_list_targets = array(
                //                (object) array(
                //                    'id' => (int) $campaign->appNexusExcludeDomainListId
                //                )
                //            );
                //        }
                //    } else {
                //        if (!empty($campaign->appNexusIncludeDomainListId)) {
                //            $data->domain_list_action = 'include';
                //            $data->domain_list_targets = array(
                //                (object) array(
                //                    'id' => (int) $this->appNexusIncludeDomainListId
                //                )
                //            );
                //        }
                //    }
                // }

                //    //
                //    // Sync Profile Data
                //    //

                //    if (empty($inventory->appNexusProfileId)) {
                //        $AppNexusResponse = AppNexus_ProfileService::addProfile(
                //            $appNexusAdvertiserId,
                //            $data
                //        );
                //        $inventory->appNexusProfileId = $AppNexusResponse->id;
                //    } else {
                //        $AppNexusResponse = AppNexus_ProfileService::updateProfile(
                //            $inventory->appNexusProfileId,
                //            $appNexusAdvertiserId,
                //            $data
                //        );
                //    }
            }
        }

        $response['status'] = 'complete';
        $response['code'] = 'jobCompleted';
        $response['message'] = 'AppNexus campaign profile synced';
        // $response['data'] = $campaign;
        return $response;
    }

    /**
     * Sanitize campaign id param
     *
     * @param  array $payload Job payload
     *
     * @return int          Sanitized campaign id
     */
    public function sanitizeCampaignIdParam($payload)
    {
        if (!isset($payload['body']['data']['campaignId'])) {
            $response['code'] = 'missingParameter';
            $response['message'] = 'Missing campaign ID parameter';

            $this->dispatchError($this->logHelper, $response);
        }

        return intval($payload['body']['data']['campaignId']);
    }

    /**
     * Campaign pre sync checks.
     *
     * @param  int $campaign Campaign id
     * @param  object $campaignHelper Campaign helper
     *
     * @return array           Pre checks data
     */
    private function campaignPreSyncChecks($campaign, $campaignHelper)
    {
        $data = $this->createCoreResponse(array());

        $user = $this->getAppNexusUserForAdvertiserId($campaign->advertiserId);

        if (!$this->isValidAppNexusAdvertiser($user, $campaign->advertiserId)) {
            $data['code'] = 'invalidAppNexusAdvertiser';
            $data['message'] = "Invalid AppNexus Advertiser: $campaign->advertiserId";
        }

        $data['data']['user'] = $user;

        if (!$this->isValidSyncStatus($campaign->status)) {
            $data['code'] = 'invalidCampaignStatusSync';
            $data['message'] = "Invalid Campaign Status: $campaign->status";
        }

        $inventories = $campaignHelper->getCampaignInventories($campaign->id);
        if (empty($inventories)) {
            $data['code'] = 'emptyInventories';
            $data['message'] = 'Empty inventories';
        }
        $data['data']['inventories'] = $inventories;

        return $data;
    }

    /**
     * Get campaign from DB.
     *
     * @param  int $campaignId User ID
     *
     * @return object|null         User
     */
    private function getCampaign($campaignId)
    {
        $response = $this->createCoreResponse();

        try {
            $campaign = \DB::table('campaigns')->where('id', $campaignId)->first();
        } catch (\Exception $e) {
            $response['code'] = $e->getCode();
            $response['message'] = $e->getMessage();
            $this->dispatchError($this->logHelper, $response);
        }

        if (!$campaign) {
            $response['code'] = 'campaignNotFound';
            $response['message'] = "User $campaignId not found";
            $this->dispatchError($this->logHelper, $response);
        }

        return $campaign;
    }

    /**
     * Get AppNexus advertiser id.
     *
     * @param  int $advertiserId Advertiser id
     *
     * @return int               Advertiser id
     */
    private function getAppNexusUserForAdvertiserId($advertiserId)
    {
        $response = $this->createCoreResponse();

        try {
            $user = \DB::table('users')
                ->join('advertisers', 'users.id', '=', 'advertisers.userId')
                ->where('advertisers.id', $advertiserId)
                ->select('users.*')
                ->first();

            return $user;
        } catch (\Exception $e) {
            $response['code'] = $e->getCode();
            $response['message'] = $e->getMessage();
            $this->dispatchError($this->logHelper, $response);
        }
    }

    /**
     * Check whether or not user associated to an advertiser has an AppNexus advertiser id.
     *
     * @param  object  $user User
     * @param  int  $advertiserId Advertiser id
     *
     * @return boolean        Returns true if a user has an AppNexus advertiser id.
     */
    private function isValidAppNexusAdvertiser($user, $advertiserId)
    {
        $response = $this->createCoreResponse();

        if (!$user) {
            $response['code'] = 'userAdvertiserNotFound';
            $response['message'] = "User related to advertiser $advertiserId not found";
            $this->dispatchError($this->logHelper, $response);
        }

        return !empty($user->appNexusAdvertiserID);
    }

    /**
     * Check whether or not a campaign as a valid sync status.
     *
     * @param  string  $campaignStatus Campaign status
     *
     * @return boolean                 Return true if the campaign has a valid sync status
     */
    private function isValidSyncStatus($campaignStatus)
    {
        return in_array(
            $campaignStatus,
            array('active', 'inactive'),
            true
        );
    }

    /**
     * Update campaign in database.
     *
     * @param  object $campaign       Campaign
     * @param  array $fieldsToUpdate Campaign fields to update
     * @param  array $response       response
     *
     * @return void
     */
    private function updateCampaignInDb($campaign, $fieldsToUpdate, $response)
    {
        try {
            \DB::table('campaigns')
                ->where('id', $campaign->id)
                ->update($fieldsToUpdate);
        } catch (Exception $e) {
            $response['code'] = $e->getCode();
            $response['message'] = $e->getMessage();

            $this->dispatchError($this->logHelper, $response);
        }
    }
}
