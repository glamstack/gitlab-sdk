<?php

return [

    /**
     * Log Channel Name
     * ------------------------------------------------------------------------
     * Throughout the SDK, we use the config('glamstack-gitlab.log_channels')
     * array variable to allow you to set the log channels (custom log stack)
     * that you want API logs to be sent to.
     *
     * If you leave this at the value of `['single']`, all API call logs will
     * be sent to the default log file for Laravel that you have configured
     * in config/logging.php which is usually storage/logs/laravel.log.
     *
     * If you would like to see GitLab API logs in a separate log file that
     * is easier to triage without unrelated log messages, you can create a
     * custom log channel and add the channel name to the array. For example,
     * we recommend creating a custom channel the name `glamstack-gitlab`,
     * however you can choose any name you would like.
     * Ex. ['single', 'glamstack-gitlab']
     *
     * You can also add additional channels that should be logged to (ex. slack).
     * Ex. ['single', 'glamstack-gitlab', 'slack']
     *
     * https://laravel.com/docs/8.x/logging
     */

    'log_channels' => ['single'],

    /**
     * Default Hosted GitLab Instances
     * ------------------------------------------------------------------------
     * If you use GitLab.com (SaaS instance), simply create a Personal Access
     * Token or Project Access Token and add it to your `.env` file.
     *
     * Security Warning: It is important that you don't add your access token to
     * this file to avoid committing to your repository (secret leak). All
     * access tokens should be defined in the `.env` file which is included
     * in `.gitignore` and not committed to your repository.
     */

    'gitlab_com' => [
        'base_url' => env('GITLAB_COM_BASE_URL', 'https://gitlab.com'),
        'access_token' => env('GITLAB_COM_ACCESS_TOKEN'),
    ],

    /**
     * Self-Managed GitLab Instances
     * ------------------------------------------------------------------------
     * If you have self-managed (private) GitLab instances, you can add the
     * configuration below. The key should be a snakecase alias/nickname that
     * you will refer to when connecting to this instance in your code.
     *
     * If you have more than one self-managed GitLab instance, you can add
     * additional instances to the array below.
     */

    'gitlab_private' => [
        'base_url' => env('GITLAB_PRIVATE_BASE_URL', 'https://gitlab.example.net'),
        'access_token' => env('GITLAB_PRIVATE_ACCESS_TOKEN'),
    ],

    /**
     * Least Privilege Projects
     * ------------------------------------------------------------------------
     * If your organization's security policies require using different tokens
     * for each group or project for least privilege, you can customize the
     * array below to add the same GitLab instance multiple times with
     * different array keys and unique access tokens for each project using
     * Project Access Tokens or for each group of projects using Bot/Service
     * Accounts and Personal Access Tokens.
     */

    // 'project_alias1' => [
    //     'base_url' => env('GITLAB_PROJECT_ALIAS1_BASE_URL', 'https://gitlab.com'),
    //     'access_token' => env('GITLAB_PROJECT_ALIAS1_ACCESS_TOKEN'),
    // ],

    // 'project_alias2' => [
    //     'base_url' => env('GITLAB_PROJECT_ALIAS2_BASE_URL', 'https://gitlab.com'),
    //     'access_token' => env('GITLAB_PROJECT_ALIAS2_ACCESS_TOKEN'),
    // ],

    // 'group_alias1' => [
    //     'base_url' => env('GITLAB_GROUP_ALIAS1_BASE_URL', 'https://gitlab.com'),
    //     'access_token' => env('GITLAB_GROUP_ALIAS1_ACCESS_TOKEN'),
    // ],

    // 'group_alias2' => [
    //     'base_url' => env('GITLAB_GROUP_ALIAS2_BASE_URL', 'https://gitlab.com'),
    //     'access_token' => env('GITLAB_GROUP_ALIAS2_ACCESS_TOKEN'),
    // ],

];
