<?php

namespace App\Jobs;

use App\Helpers\CampaignHelpers;
use App\Helpers\InventoryHelpers;
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
     * Inventory helper.
     *
     * @var object
     */
    private $inventoryHelper;

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
        $this->inventoryHelper = new InventoryHelpers();
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

            // TODO: implement correctly syncAppNexusLineItem and its sub calls
            // case 'syncAppNexusLineItem':
            //     $response = $this->syncAppNexusLineItem($this->payload);
            //     break;

            // TODO: implement correctly syncAppNexusCampaign and its sub calls
            // case 'syncAppNexusCampaign':
            //     $response = $this->syncAppNexusCampaign($this->payload);
            //     break;

            // TODO: implement correctly syncStatus and its sub calls
            // case 'syncStatus':
            //     $response = $this->syncStatus($this->payload);
            //     break;

            // TODO: implement correctly deleteCampaign and its sub calls
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

        $user = $preChecksData['data']['user'];
        $response['data'] = array();

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

                // TODO
                //
                // Campaign Profile Inventory Targeting
                //
                // if ($inventory->type == 'display') {
                //     $data = $this->campaignHelper->getAppNexusProfileCategoryData(
                //         $inventory,
                //         $data
                //    );
                // } elseif ($inventory->type == 'retargeting') {
                //    $data = $this->campaignHelper->getAppNexusProfileRetargetingData(
                //        $inventory,
                //        $data
                //    );
                // } elseif ($inventory->type == 'mobile') {
                //    $data = $this->campaignHelper->getAppNexusProfileCategoryData(
                //        $inventory,
                //        $data
                //    );
                //    $data->device_type_action = 'include';
                //    $data->device_type_targets = array(
                //        'phone',
                //        'tablet'
                //    );
                // } elseif ($inventory->type == 'facebook') {
                //    $data = $this->campaignHelper->getAppNexusProfileFacebookData(
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

                $fieldsToUpdate = array(
                        'lastSyncedWithAppNexus' => date('Y-m-d H:i:s')
                );

               //
               // Sync Profile Data
               //
                if (empty($inventory->appNexusProfileId)) {
                    $AppNexusResponse = AppNexus\ProfileService::addProfile($user->appNexusAdvertiserID, $data);
                    $fieldsToUpdate['appNexusProfileId'] = $AppNexusResponse->id;
                } else {
                    $AppNexusResponse = AppNexus\ProfileService::updateProfile($inventory->appNexusProfileId, $user->appNexusAdvertiserID, $data);
                }

                $this->updateInventoryInDb($inventory, $fieldsToUpdate, $response);
                array_push($response['data'], $inventory);
            }
        }

        $response['status'] = 'complete';
        $response['code'] = 'jobCompleted';
        $response['message'] = 'AppNexus campaign profile synced';
        return $response;
    }

    // TODO: Implement correctly
    /**
     * Sync App Nexus Line Item.
     *
     * @param  array  $payload Job payload
     *
     * @return Array          Job response
     */
    public function syncAppNexusLineItem($payload = array())
    {
        $response = $this->createCoreResponse($payload);

        $campaignId = $this->sanitizeCampaignIdParam($payload);

        $campaign = $this->getCampaign($campaignId);

        $preChecksData = $this->campaignPreSyncChecks($campaign, $this->campaignHelper);
        if ('failed' === $preChecksData['status'] && !empty($preChecksData['code'])) {
            $response = $preChecksData;
            return $response;
        }

        $user = $preChecksData['data']['user'];
        $response['data'] = array();

        foreach ($preChecksData['data']['inventories'] as $inventory) {
            $fieldsToUpdate = array(
                    'lastSyncedWithAppNexus' => date('Y-m-d H:i:s')
            );

            if (!empty($inventory->appNexusLineItemId) && 0 <= $inventory->cost) {
                // If the campaign is active but has no cost, set campaign
                // and line item status in AppNexus to inactive.
                if ($this->status == 'active') {
                    $data = new stdClass();
                    $data->state = 'inactive';

                    $AppNexusResponse = AppNexus\LineItemService::updateLineItem($inventory->appNexusLineItemId, $user->appNexusAdvertiserID, $data);
                }

                continue;
            }

            if (0 < $inventory->cost) {
                $data = $this->inventoryHelper->getAppNexusLineItemData($inventory, $campaign);

                if (empty($inventory->appNexusLineItemId)) {
                    $AppNexusResponse = AppNexus\LineItemService::addLineItem($user->appNexusAdvertiserId, $data);
                    $fieldsToUpdate['appNexusLineItemId'] = $AppNexusResponse->id;
                } else {
                    $AppNexusResponse = AppNexus\LineItemService::updateLineItem($inventory->appNexusLineItemId, $user->appNexusAdvertiserID, $data);
                }

                $this->updateInventoryInDb($inventory, $fieldsToUpdate, $response);
                array_push($response['data'], $inventory);
            }
        }

        $response['status'] = 'complete';
        $response['code'] = 'jobCompleted';
        $response['message'] = 'AppNexus campaign domains synced';
        return $response;
    }

    // TODO: To re-implement
    public function syncAppNexusCampaign()
    {
        $inventories          = $this->fetchInventories();
        $appNexusAdvertiserId = $this->fetchAppNexusAdvertiserId();
        $apiCalls             = array();

        // If no inventories or if ANX Advertising ID is blank, don't sync
        if (empty($inventories) || empty($appNexusAdvertiserId)) {
            return null;
        }

        foreach ($inventories as $inventory) {

            if ( !empty($inventory->appNexusCampaignId)
                 && $inventory->cost <= 0 ) {

                // If the campaign is active but has no cost, set campaign
                // and line item status in AppNexus to inactive.
                if ($this->status == 'active') {
                    $data = new stdClass();
                    $data->state = 'inactive';

                    $response = AppNexus_CampaignService::updateCampaign(
                        $inventory->appNexusCampaignId,
                        $appNexusAdvertiserId,
                        $data
                    );

                    $apiCalls['data'][] = json_encode($data);
                    $apiCalls['response'][] = $response->toJson();
                }

                continue;
            }

            if ( $inventory->cost > 0 ) {

                $data = $this->getAppNexusCampaignData($inventory);

                if (!empty($inventory->appNexusProfileId)) {
                    $data->profile_id = $inventory->appNexusProfileId;
                }

                if (!empty($inventory->appNexusLineItemId)) {
                    $data->line_item_id = $inventory->appNexusLineItemId;
                }

                if (empty($inventory->appNexusCampaignId)) {

                    $response =
                        AppNexus_CampaignService::addCampaign(
                            $appNexusAdvertiserId,
                            $data
                        );

                    $inventory->appNexusCampaignId = $response->id;
                    $inventory->lastSyncedWithAppNexus = date("Y-m-d H:i:s");
                    $inventory->save();

                } else {

                    $response =
                        AppNexus_CampaignService::updateCampaign(
                            $inventory->appNexusCampaignId,
                            $appNexusAdvertiserId,
                            $data
                        );

                    $inventory->lastSyncedWithAppNexus = date("Y-m-d H:i:s");
                    $inventory->save();

                }

                $apiCalls['data'][] = json_encode($data);
                $apiCalls['response'][] = $response->toJson();

            }

        }

        return $apiCalls;

    }

    // TODO: To re-implement
    public function syncAppNexusStatus()
    {
        $appNexusAdvertiserId = $this->fetchAppNexusAdvertiserId();

        $inventories          = $this->fetchInventories();
        $appNexusAdvertiserId = $this->fetchAppNexusAdvertiserId();
        $apiCalls             = array();

        // If no inventories or if ANX Advertising ID is blank, don't sync
        if (empty($inventories) || empty($appNexusAdvertiserId)) {
            return null;
        }

        foreach ($inventories as $inventory) {

            $data = new stdClass();
            $data->state = $this->status;

            if (!empty($inventory->appNexusLineItemId)) {
                $response = AppNexus_LineItemService::updateLineItem(
                    $inventory->appNexusLineItemId,
                    $appNexusAdvertiserId,
                    $data
                );

                $inventory->lastSyncedWithAppNexus = date("Y-m-d H:i:s");

                $apiCalls['data'][] = json_encode($data);
                $apiCalls['response'][] = $response->toJson();
            }

            if (!empty($inventory->appNexusCampaignId)) {
                $response = AppNexus_CampaignService::updateCampaign(
                    $inventory->appNexusCampaignId,
                    $appNexusAdvertiserId,
                    $data
                );
                $inventory->lastSyncedWithAppNexus = date("Y-m-d H:i:s");
                $apiCalls['data'][] = json_encode($data);
                $apiCalls['response'][] = $response->toJson();
            }

            $inventory->save();

            return $apiCalls;
        }
    }

    // TODO: To re-implement
    public function deleteFromAppNexus()
    {
        $inventories = $this->fetchInventories();
        $advertiser  = $this->fetchAdvertiser();
        $apiCalls    = array();

        // If no inventories, there's nothing to do here.
        if (empty($inventories)) {
            return null;
        }

        // Get the AppNexus Advertiser ID from User
        $usersTable = new Zend_Db_Table('users');
        $user = $usersTable->find($advertiser->userId)->current();
        $appNexusAdvertiserId = $user->appNexusAdvertiserID;

        foreach ($inventories as $inventory) {

            // Deleting a Line Item will delete all associated campaigns
            if (!empty($inventory->appNexusLineItemId)) {
                $response = AppNexus_LineItemService::deleteLineItem(
                    $inventory->appNexusLineItemId,
                    $appNexusAdvertiserId
                );

                $apiCalls['data'][] = json_encode($inventory->appNexusLineItemId);
                $apiCalls['response'][] = $response;
            }

            if (!empty($inventory->appNexusProfileId)) {
                $response = AppNexus_ProfileService::deleteProfile(
                    $inventory->appNexusProfileId,
                    $appNexusAdvertiserId
                );

                $apiCalls['data'][] = json_encode($inventory->appNexusLineItemId);
                $apiCalls['response'][] = $response;
            }

            $inventory->appNexusCampaignId = null;
            $inventory->appNexusLineItemId = null;
            $inventory->appNexusProfileId = null;
            $inventory->save();

        }

        return $apiCalls;

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

    /**
     * Update inventory in database.
     *
     * @param  object $inventory       Campaign
     * @param  array $fieldsToUpdate Campaign fields to update
     * @param  array $response       response
     *
     * @return void
     */
    private function updateInventoryInDb($inventory, $fieldsToUpdate, $response)
    {
        try {
            \DB::table('inventories')
                ->where('id', $inventory->id)
                ->update($fieldsToUpdate);
        } catch (Exception $e) {
            $response['code'] = $e->getCode();
            $response['message'] = $e->getMessage();

            $this->dispatchError($this->logHelper, $response);
        }
    }
}
