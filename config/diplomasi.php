<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App registration (mobile: guest + new account)
    |--------------------------------------------------------------------------
    |
    | When true, auth/guest, auth/register, and auth/register-from-guest
    | respond with 403 and registration_disabled_by_admin. Login and OTP
    | for existing users are unchanged. Set DISABLE_APP_REGISTRATION=false
    | in .env to allow registration again (e.g. local development).
    |
    */

    'disable_app_registration' => (bool) env('DISABLE_APP_REGISTRATION', true),

];
