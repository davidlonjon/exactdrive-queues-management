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

    /**
     * Controller for the route handling syncing AppNexus campaign domain
     *
     * @param int $campaignId Campaign Id
     */
    public function syncAppNexusDomains($campaignId)
    {
        $payload = $this->createJobCorePayload();
        $payload['body']['action'] = 'syncAppNexusDomains';
        $payload['body']['data'] = array(
            'campaignId' => intval($campaignId),
        );

        $this->queue->push(new AppNexusCampaignJob($payload));

        return response()->json(['status' => 'ok', 'message' => 'syncAppNexusDomains job sent to queue']);
    }
}
