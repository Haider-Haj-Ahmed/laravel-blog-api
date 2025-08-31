<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/testPusher', function () {
    return view('testPusher');
});

Route::get('/trigger-event', function () {
    event(new \App\Events\MyPusherEvent('Hello from Laravel!'));
    return 'Event triggered!';
});