<?php

namespace App\Http\Controllers;

use App\Jobs\AppNexusJob;
use Exactdrive\AppNexus;
use Illuminate\Queue\Queue;

class QueueController extends Controller
{

    /**
     * The user repository instance.
     */
    protected $queue;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->queue = app('queue');
    }

    public function addAdvertiser($userId)
    {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = 'addAdvertiser';
        $payload['body']['data'] = array(
            'userId' => intval($userId),
        );

        $this->queue->push(new AppNexusJob($payload));

        return response()->json(['status' => 'ok', 'message' => 'Request sent to queue']);
    }

    public function deleteAdvertiser($userId)
    {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = 'deleteAdvertiser';
        $payload['body']['data'] = array(
            'userId' => intval($userId),
        );

        $this->queue->push(new AppNexusJob($payload));

        return response()->json(['status' => 'ok', 'message' => 'Request sent to queue']);
    }

    private function createJobCorePayload()
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
