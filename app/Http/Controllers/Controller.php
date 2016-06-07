<?php

namespace App\Http\Controllers;


use App\Helpers\QueueJobsLogingHelpers as LogHelper;
use Laravel\Lumen\Routing\Controller as BaseController;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

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

        $logHelper = new LogHelper();
        $logHelper->createJobLog(
            $payload['uuid'],
            $job,
            $jobAction,
            $payload,
            'waiting',
            env('RACKSPACECLOUD_QUEUE', 'jobs')
        );

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
            'uuid' => Uuid::uuid1()->toString(),
            'body' => array(
                'action' => '',
                'data' => '',
            ),
            'ttl' => 3600 // TTL to be defined
        );
    }
}
