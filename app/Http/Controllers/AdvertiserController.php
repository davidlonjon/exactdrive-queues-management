<?php

namespace App\Http\Controllers;

use App\Jobs\AppNexusAdvertiserJob;
use Exactdrive\AppNexus;
use Illuminate\Queue\Queue;

class AdvertiserController extends Controller
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

    /**
     * Controller for the route handling adding AppNexus advertiser
     *
     * @param int $userId User Id
     */
    public function addAdvertiser($userId)
    {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = 'addAdvertiser';
        $payload['body']['data'] = array(
            'userId' => intval($userId),
        );

        $this->queue->push(new AppNexusAdvertiserJob($payload));

        return response()->json(['status' => 'ok', 'message' => 'addAdvertiser job sent to queue']);
    }

    /**
     * Controller for the route handling deleting AppNexus advertiser
     *
     * @param int $userId User Id
     */
    public function deleteAdvertiser($userId)
    {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = 'deleteAdvertiser';
        $payload['body']['data'] = array(
            'userId' => intval($userId),
        );

        $this->queue->push(new AppNexusAdvertiserJob($payload));

        return response()->json(['status' => 'ok', 'message' => 'deleteAdvertiser job sent to queue']);
    }

    /**
     * Controller for the route handling updating AppNexus advertiser
     *
     * @param int $userId User Id
     */
    public function updateAdvertiser($userId)
    {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = 'updateAdvertiser';
        $payload['body']['data'] = array(
            'userId' => intval($userId),
        );

        $this->queue->push(new AppNexusAdvertiserJob($payload));

        return response()->json(['status' => 'ok', 'message' => 'updateAdvertiser job sent to queue']);
    }
}
