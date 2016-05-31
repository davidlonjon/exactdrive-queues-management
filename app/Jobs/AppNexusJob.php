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

        $error = $this->createErrorStructure();

        if (!isset($this->payload['body'], $this->payload['body']['action'], $this->payload['body']['data'])) {
            $error['code'] = 'malformedPayload';
            $error['message'] = 'The payload is malformed';

            $this->dispatchError($error);
        }

        $jobAction = $this->payload['body']['action'];

        switch ($jobAction) {
            case 'addAdvertiser':
                $this->addAdvertiser($this->payload);
                break;

            default:
                break;
        }

        dump('OK');
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
    private function createErrorStructure()
    {
        return array(
            'code' => '',
            'message' => '',
            'payload' => $this->payload,
        );
    }

    /**
     * Dispatch error
     *
     * @param  array   $error Error
     * @param  boolean $die     Stop the script
     *
     * @return void
     */
    private function dispatchError($error = array(), $action = 'delete', $die = true)
    {
        // TODO implement logging and or emailing
        dump($error);

        // TODO // Look if job can be released
        if ('delete' === $action) {
            $this->delete();
        }
        if ($die) {
            die;
        }
    }

    private function addAdvertiser($payload = array())
    {
        $error = $this->createErrorStructure();

        if (!isset($payload['body']['data']['userId'])) {
            $error['code'] = 'missingParameter';
            $error['message'] = 'Missing user ID parameter';

            $this->dispatchError($error);
        }

        $userId = intval($payload['body']['data']['userId']);

        $user = \DB::table('users')->where('id', $userId)->first();

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
            // $user->appNexusAdvertiserID = $advertiser->id;
        } catch (\Exception $e) {
            $message = AppNexus\Api::decodeMessage($e->getMessage());
            $error['code'] = 'advertiserAlreadyAdded';
            $error['message'] = $message->error;

            $this->dispatchError($error);
        }
    }
}
