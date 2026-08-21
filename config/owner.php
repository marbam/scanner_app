<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Owner User
    |--------------------------------------------------------------------------
    |
    | This app is single-user by design (no public registration). These
    | values are used by the `app:create-owner-user` artisan command to
    | create or update that one account from your .env file.
    |
    */

    'name' => env('OWNER_NAME'),

    'email' => env('OWNER_EMAIL'),

    'password' => env('OWNER_PASSWORD'),

];
