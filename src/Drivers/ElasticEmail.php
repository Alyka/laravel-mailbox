<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\ElasticEmailController;
use Illuminate\Support\Facades\Route;

class ElasticEmail implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/elastic-email', ElasticEmailController::class);
        });
    }
}
