<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});

// Routes related to AppNexus advertisers.
$app->get('addAdvertiser/{userId}', 'AdvertiserController@addAdvertiser');
$app->get('deleteAdvertiser/{userId}', 'AdvertiserController@deleteAdvertiser');
$app->get('updateAdvertiser/{userId}', 'AdvertiserController@updateAdvertiser');

// Routes related to AppNexus campaigns.
$app->get('syncAppNexusDomains/{campaignId}', 'CampaignController@syncAppNexusDomains');
$app->get('syncAppNexusCampaignProfile/{campaignId}', 'CampaignController@syncAppNexusCampaignProfile');
