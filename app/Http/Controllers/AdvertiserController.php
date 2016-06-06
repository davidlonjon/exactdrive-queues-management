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
     * Controller for the route handling adding AppNexus advertiser.
     *
     * @param int $userId User Id
     */
    public function addAdvertiser($userId)
    {
        $response = $this->createNewJob(
            'AppNexusAdvertiserJob',
            'addAdvertiser',
            array('userId' => intval($userId))
        );

        return response()->json($response);
    }

    /**
     * Controller for the route handling deleting AppNexus advertiser.
     *
     * @param int $userId User Id
     */
    public function deleteAdvertiser($userId)
    {
        $response = $this->createNewJob(
            'AppNexusAdvertiserJob',
            'deleteAdvertiser',
            array('userId' => intval($userId))
        );

        return response()->json($response);
    }

    /**
     * Controller for the route handling updating AppNexus advertiser.
     *
     * @param int $userId User Id
     */
    public function updateAdvertiser($userId)
    {
        $response = $this->createNewJob(
            'AppNexusAdvertiserJob',
            'updateAdvertiser',
            array('userId' => intval($userId))
        );

        return response()->json($response);
    }
}
