<?php
    return [

    'default' => env('QUEUE_DRIVER', 'sync'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'expire' => 60,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host'   => 'localhost',
            'queue'  => 'default',
            'ttr'    => 60,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key'    => 'your-public-key',
            'secret' => 'your-secret-key',
            'queue'  => 'your-queue-url',
            'region' => 'us-east-1',
        ],

        'iron' => [
            'driver'  => 'iron',
            'host'    => 'mq-aws-us-east-1.iron.io',
            'token'   => 'your-token',
            'project' => 'your-project-id',
            'queue'   => 'your-queue-name',
            'encrypt' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue'  => 'default',
            'expire' => 60,
        ],

        'RackspaceCloud' => [
           'driver' => 'RackspaceCloud',
            'queue' => env('RACKSPACECLOUD_QUEUE', 'jobs'), // The default queue
            'endpoint' => env('RACKSPACECLOUD_ENDPOINT', 'US'), // US or UK
            'username' => env('RACKSPACECLOUD_USERNAME', 'your-username'), // Some Rackspace Cloud username
            'apiKey' => env('RACKSPACECLOUD_APIKEY', 'your-api_key'), // Some Rackspace Cloud api key
            'region' => env('RACKSPACECLOUD_REGION', 'DFW'), // The region the queue is setup
            'urlType'  => env('RACKSPACECLOUD_URLTYPE', 'internalURL'), // Optional, defaults to internalURL
        ],

    ],

    'failed' => [
        'database' => 'mysql', 'table' => 'failed_jobs',
    ],

];