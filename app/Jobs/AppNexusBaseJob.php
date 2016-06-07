<?php

namespace App\Jobs;

use Exactdrive\AppNexus;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

abstract class AppNexusBaseJob implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "queueOn" and "delay" queue helper methods.
    |
    */

    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Configure AppNexus settings.
     *
     * @return void
     */
    public function configureAppNexus()
    {
        AppNexus\Api::setUserName(config('appnexus.username'));
        AppNexus\Api::setPassword(config('appnexus.password'));
        AppNexus\Api::setBaseUrl(config('appnexus.url'));
    }

    /**
     * Create error structure.
     *
     * @param  Array $payload Payload
     *
     * @return Array          Job core response
     */
    public function createCoreResponse($payload = array())
    {
        return array(
            'status' => 'failed',
            'code' => '',
            'message' => '',
            'payload' => $payload,
        );
    }

    /**
     * Dispatch error.
     *
     * @param  array   $response Response
     * @param  boolean $die     Stop the script
     *
     * @return void
     */
    public function dispatchError($response = array(), $action = 'delete', $die = true)
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
}
