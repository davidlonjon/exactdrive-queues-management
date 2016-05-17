<?php

namespace App\Jobs;

class ExampleJob extends Job
{

    private $object;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($object)
    {
        $this->object = $object;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // print_r($this->object);
        // print_r("hello");


    }
}
