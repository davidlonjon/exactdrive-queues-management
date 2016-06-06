<?php

namespace App\Jobs;

use Exactdrive\AppNexus;

class AppNexusAdvertiserJob extends AppNexusBaseJob
{

    private $payload;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
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

            $this->dispatchError($response);
        }

        $jobAction = $this->payload['body']['action'];

        switch ($jobAction) {
            case 'addAdvertiser':
                $response = $this->addAdvertiser($this->payload);
                break;

            case 'deleteAdvertiser':
                $response = $this->deleteAdvertiser($this->payload);
                break;

            default:
                break;
        }

        dump($response);
    }

    /**
     * Job to add a new AppNexus Advertiser
     *
     * @param array $payload Payload
     */
    private function addAdvertiser($payload = array())
    {
        $response = $this->createCoreResponse($this->payload);

        if (!isset($payload['body']['data']['userId'])) {
            $response['code'] = 'missingParameter';
            $response['message'] = 'Missing user ID parameter';

            $this->dispatchError($response);
        }

        $userId = intval($payload['body']['data']['userId']);

        $user = \DB::table('users')->where('id', $userId)->first();
        // TODO implement error when user not found

        $advertiserData = array(
            'name'             => $user->companyName,
            'billing_name'     => $user->email,
            'billing_phone'    => $user->phoneNumber,
            'billing_address1' => $user->address,
            'billing_city'     => $user->city,
            'billing_state'    => $user->stateOrProvince,
            'billing_country'  => $user->country,
            'billing_zip'      => $user->zipPostalCode
        );

        $advertiser = (object) array();
        try {
            $advertiser = AppNexus\AdvertiserService::addAdvertiser($advertiserData);
            \DB::table('users')
                        ->where('id', $userId)
                        ->update(
                            [
                                'appNexusAdvertiserID' => $advertiser->id,
                                'lastSyncedWithAppNexus' => date("Y-m-d H:i:s")
                            ]
                        );

            $response['status'] = 'ok';
            $response['code'] = 'jobSuccessful';
            $response['message'] = 'New AppNexus advertiser added';
            $response['data'] = $advertiser;
            return $response;
        } catch (\Exception $e) {
            $message = AppNexus\Api::decodeMessage($e->getMessage());
            $response['code'] = 'AppNexusAdvertiserAlreadyAdded';
            $response['message'] = $message->error;

            $this->dispatchError($response);
        }

        // TODO implement DB update error
    }

      /**
     * Job to delete a AppNexus advertiser
     *
     * @param  array  $payload Payload
     *
     * @return [type]          [description]
     */
    private function deleteAdvertiser($payload = array())
    {
        $response = $this->createCoreResponse($this->payload);

        if (!isset($payload['body']['data']['userId'])) {
            $response['code'] = 'missingParameter';
            $response['message'] = 'Missing user ID parameter';

            $this->dispatchError($response);
        }

        $userId = intval($payload['body']['data']['userId']);

        $user = \DB::table('users')->where('id', $userId)->first();
        // TODO implement error when user not found

        $advertiser = (object) array();
        try {
            $advertiser = AppNexus\AdvertiserService::deleteAdvertiser($user->appNexusAdvertiserID);
            \DB::table('users')
                        ->where('id', $userId)
                        ->update(
                            ['lastSyncedWithAppNexus' => date("Y-m-d H:i:s")]
                        );

            $response['status'] = 'ok';
            $response['code'] = 'jobSuccessful';
            $response['message'] = 'AppNexus advertiser deleted';
            $response['data'] = $advertiser;

            return $response;
        } catch (\Exception $e) {
            $message = AppNexus\Api::decodeMessage($e->getMessage());
            $response['code'] = 'AppNexusAdvertiserNotFound';
            $response['message'] = $message->error;

            $this->dispatchError($response);
        }

        // TODO implement DB update error
    }
}
