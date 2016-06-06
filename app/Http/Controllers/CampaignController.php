<?php

namespace App\Http\Controllers;

use App\Jobs\AppNexusCampaignJob;
use Exactdrive\AppNexus;
use Illuminate\Queue\Queue;

class CampaignController extends Controller
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

    public function createNewJob($job, $campaignId) {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = $job;
        $payload['body']['data'] = array(
            'campaignId' => intval($campaignId),
        );

        $this->queue->push(new AppNexusCampaignJob($payload));

        return ['status' => 'ok', 'message' => "$job job sent to queue"];
    }

    /**
     * Controller for the route handling syncing AppNexus campaign domain.
     *
     * @param int $campaignId Campaign Id
     */
    public function syncAppNexusDomains($campaignId)
    {
        $response = $this->createNewJob('syncAppNexusDomains', $campaignId);
        return response()->json($response);
    }
}
