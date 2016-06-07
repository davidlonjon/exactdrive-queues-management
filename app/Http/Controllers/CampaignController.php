<?php

namespace App\Http\Controllers;

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
     * Controller for the route handling syncing AppNexus campaign domain.
     *
     * @param int $campaignId Campaign Id
     */
    public function syncAppNexusDomains($campaignId)
    {
        $response = $this->createNewJob(
            'AppNexusCampaignJob',
            'syncAppNexusDomains',
            array('campaignId' => intval($campaignId))
        );

        return response()->json($response);
    }

    /**
     * Controller for the route handling syncing AppNexus campaign profile.
     *
     * @param int $campaignId Campaign Id
     */
    public function syncAppNexusCampaignProfile($campaignId)
    {
        $response = $this->createNewJob(
            'AppNexusCampaignJob',
            'syncAppNexusCampaignProfile',
            array('campaignId' => intval($campaignId))
        );

        return response()->json($response);
    }
}
