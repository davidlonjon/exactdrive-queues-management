<?php

namespace App\Helpers;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

class QueueJobsLogingHelpers
{

    /**
     * Create a job log entry in database.
     *
     * @param  string $jobUuid   Job uuid
     * @param  string $jobType   Job type
     * @param  string $jobAction Job action
     * @param  array $payload   Payload
     * @param  string $status    Job status
     * @param  string $queue     Job queue
     *
     * @return void
     */
    public function createJobLog($jobUuid, $jobType, $jobAction, $payload, $status, $queue)
    {
        $timestamp = date('Y-m-d G:i:s');

        \DB::table('queues_jobs_log')->insert(
            array(
                'uuid' => $jobUuid,
                'type' => $jobType,
                'action' => $jobAction,
                'payload' => serialize($payload),
                'status' => $status,
                'queue' => $queue,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            )
        );
    }

    /**
     * Update a job log entry in db
     *
     * @param  string $jobUuid Job uuid
     * @param  string $code    Job response code
     * @param  string $message Job response message
     * @param  string $status  Job status
     *
     * @return void
     */
    public function updateJobLog($jobUuid, $code, $message, $status)
    {
        $timestamp = date('Y-m-d G:i:s');
        \DB::table('queues_jobs_log') ->where('uuid', $jobUuid) ->update(
            array(
                'status' => $status,
                'code' => $code,
                'message' => $message,
                'updated_at' => $timestamp,
            )
        );
    }
}
