<?php

namespace App\Jobs;

use Exactdrive\AppNexus;

class AppNexusJob extends Job
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

        $response = $this->createCoreResponse();

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
     * Configure AppNexus settings
     *
     * @return void
     */
    private function configureAppNexus()
    {
        AppNexus\Api::setUserName(config('appnexus.username'));
        AppNexus\Api::setPassword(config('appnexus.password'));
        AppNexus\Api::setBaseUrl(config('appnexus.url'));
    }

    /**
     * Create error structure
     *
     * @return void
     */
    private function createCoreResponse()
    {
        return array(
            'status' => 'error',
            'code' => '',
            'message' => '',
            'payload' => $this->payload,
        );
    }

    /**
     * Dispatch error
     *
     * @param  array   $response Response
     * @param  boolean $die     Stop the script
     *
     * @return void
     */
    private function dispatchError($response = array(), $action = 'delete', $die = true)
    {
        // TODO implement logging and or emailing
        dump($response);

        // TODO // Look if job can be released
        if ('delete' === $action) {
            $this->delete();
        }
        if ($die) {
            die;
        }
    }

    /**
     * Job to add a new AppNexus Advertiser
     *
     * @param array $payload Payload
     */
    private function addAdvertiser($payload = array())
    {
        $response = $this->createCoreResponse();

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
            $response['code'] = 'advertiserAlreadyAdded';
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
        $response = $this->createCoreResponse();

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
            $response['code'] = 'advertiserAlreadyAdded';
            $response['message'] = $message->error;

            $this->dispatchError($response);
        }

        // TODO implement DB update error
    }
}
