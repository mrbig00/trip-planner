<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trip Document Storage
    |--------------------------------------------------------------------------
    |
    | Configuration for user-uploaded documents attached to trips (tickets,
    | reservations, itineraries, etc.). These are stored privately — never on
    | the "public" disk — and served only through an authenticated download
    | action in App\Livewire\Trips\Show.
    |
    */

    'disk' => env('TRIP_DOCUMENTS_DISK', 'documents'),

    'max_upload_kb' => (int) env('TRIP_DOCUMENTS_MAX_UPLOAD_KB', 10240),

    // The hard ceiling PHP/nginx will actually accept a request body of,
    // independent of this app's own max_upload_kb: upload_max_filesize /
    // post_max_size (Dockerfile) and client_max_body_size
    // (docker/nginx/default.conf) are static, build-time infra config, so
    // they can't read this env var themselves — this constant exists so
    // they have one documented value to stay in sync with, and so
    // AppServiceProvider::boot() can catch a drift between the two loudly
    // (a 500 at boot) rather than have max_upload_kb quietly get truncated
    // to a generic 413 at the infra layer before Laravel's own friendly
    // validation error ever runs. Keep this equal to the smaller of the two
    // infra values if they ever differ.
    'infra_max_upload_kb' => (int) env('TRIP_DOCUMENTS_INFRA_MAX_UPLOAD_KB', 12288),

    'allowed_mimes' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
        'jpg', 'jpeg', 'png', 'heic', 'webp',
    ],

];
