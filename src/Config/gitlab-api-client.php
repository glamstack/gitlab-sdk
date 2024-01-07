<?php

return [
    'url' => env('GITLAB_API_URL'),
    'token' => env('GITLAB_API_TOKEN'),
    'exceptions' => env('GITLAB_API_EXCEPTIONS', true),
    'version' => env('GITLAB_API_VERSION', 4)
];
