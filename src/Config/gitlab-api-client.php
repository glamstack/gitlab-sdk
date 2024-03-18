<?php

return [
    'url' => env('GITLAB_API_URL'),
    'token' => env('GITLAB_API_TOKEN'),
    'exceptions' => env('GITLAB_API_EXCEPTIONS', true),
    'log' => [
        'request_data' => [
            'get' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_GET_ENABLED', true),
                'excluded' => [
                    'key',
                    'password'
                ]
            ],
            'post' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_POST_ENABLED', true),
                'excluded' => [
                    'content', // https://docs.gitlab.com/ee/api/repository_files.html
                ]
            ],
            'put' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_PUT_ENABLED', true),
                'excluded' => [
                    'content', // https://docs.gitlab.com/ee/api/repository_files.html
                ]
            ],
            'delete' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_DELETE_ENABLED', true),
                'excluded' => []
            ],
        ]
    ],
    'version' => env('GITLAB_API_VERSION', 4)
];
