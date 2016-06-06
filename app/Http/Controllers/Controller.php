<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
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
