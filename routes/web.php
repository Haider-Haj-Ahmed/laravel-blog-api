<?php

use App\Events\MyPusherEvent;
use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/testPusher', function () {
    return view('testPusher');
});

Route::get('/trigger-event', function () {
    event(new MyPusherEvent('Hello from Laravel!'));

    return 'Event triggered!';
});
// Route::post('login', [AuthController::class, 'login']);
