<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{

    /**
     * Create new AppNexus job.
     *
     * @param  string $job       Job class name
     * @param  string $jobAction Job action
     * @param  array $data      Job data
     *
     * @return array            Route response
     */
    public function createNewJob($job, $jobAction, $data)
    {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = $jobAction;
        $payload['body']['data'] = $data;

        $jobClass = "App\\Jobs\\$job";
        $this->queue->push(new $jobClass($payload));

        return array('status' => 'ok', 'message' => "$jobAction job sent to queue");
    }

    /**
     * Create job core payload.
     *
     * @return array Core payload
     */
    public function createJobCorePayload()
    {
        return array(
            'body' => array(
                'action' => '',
                'data' => '',
            ),
            'ttl' => 3600 // TTL to be defined
        );
    }
}
