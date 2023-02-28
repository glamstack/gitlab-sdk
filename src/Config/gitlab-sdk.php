<?php

return [

    /**
     * ------------------------------------------------------------------------
     * GitLab Auth Configuration
     * ------------------------------------------------------------------------
     *
     * @param string $default_connection
     *      The connection key (array key) of the connection that you want to
     *      use if not specified when instantiating the ApiClient.
     *
     *      This allows you to globally switch between `saas` and any
     *      other connections that you have configured.
     *
     * @param array $log_channels
     *      The Laravel log channels to send all related info and error logs to
     *      for authentication config validation. If you leave this at the value
     *      of `['single']`, all API call logs will be sent to the default log
     *      file for Laravel that you have configured in `config/logging.php`
     *      which is usually `storage/logs/laravel.log`.
     *
     *      If you would like to see GitLab API logs in a separate log file that
     *      is easier to triage without unrelated log messages, you can create
     *      a custom log channel and add the channel name to the array. It is
     *      recommended creating a custom channel (ex. `gitlab-sdk` or chosoe
     *      any name you would like.
     *      Ex. ['single', 'gitlab-sdk']
     *
     *      You can also add additional channels that logs should be sent to.
     *      Ex. ['single', 'gitlab-sdk', 'slack']
     *
     *      @see https://laravel.com/docs/9.x/logging
     */
    'auth' => [
        'default_connection' => env('GITLAB_DEFAULT_CONNECTION', 'saas'),
        'log_channels' => ['single'],
    ],

    /**
     * ------------------------------------------------------------------------
     * Connections Configuration
     * ------------------------------------------------------------------------
     *
     * To allow for least privilege access and multiple API tokens, the SDK uses
     * this configuration section for configuring each of the API tokens that
     * you use and configuring the different Base URLs for each token.
     *
     * Each connection has an array key that we refer to as the "connection
     * key" that contains a array of configuration values that is used when
     * the ApiClient is instantiated.
     *
     * If you have additional GitLab self managed instances that you connect to
     * beyond what is pre-configured below, you can add an additional connection
     * keys below with the name of your choice and create new variables for the
     * Base URL and API token using the other instances as examples.
     *
     * ```php
     * $gitlab_api = new \GitlabIt\Gitlab\ApiClient('saas');
     * ```
     *
     * You can add the `GITLAB_DEFAULT_CONNECTION` variable in your .env file so
     * you don't need to pass the connection key into the ApiClient. The
     * `saas` connection key is used if the `.env` variable is not set.
     *
     * ```php
     * $gitlab_api = new \GitlabIt\Gitlab\ApiClient();
     * ```
     *
     * @param string $base_url
     *      The URL to to use for the ApiClient connection. This should usually
     *      use an `.env` variable, however can be  statically  set in the
     *      configuration array below if desired.
     *
     *      If you use GitLab.com (SaaS instance), this is already configured in
     *      the `saas` configuration key.
     *
     *      If you have a self-managed GitLab instance, this is the same URL
     *      that you use to log in and access your repositories.
     *      Ex. `https://gitlab.mycompany.com`
     *
     * @param string $access_token
     *      The API access token for the respective connection.
     *
     *      Security Warning: It is important that you don't add your API token
     *      to this file to avoid committing to your repository (secret leak).
     *      All API tokens should be defined in the `.env` file which is
     *      included in `.gitignore` and not committed to your repository.
     *
     *      If you use a Personal Access Token, the API has access to the same
     *      groups and projects that the user account has access to. It is a
     *      best practice to create a service account (bot) user for production
     *      application use cases with access to specific groups and projects.
     *      The username of the Personal Access Token is used when creating any
     *      resources (ex. projects, issues, MRs, comments, etc.)
     *
     *      @see https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html#personal-access-tokens
     *
     *      If you use a Group Access Token, the API has access to that group
     *      and any child groups and projects with the access level specified
     *      when creating the token. This is a reasonable level of permissions
     *      for most use cases.
     *
     *      @see https://docs.gitlab.com/ee/user/group/settings/group_access_tokens.html#group-access-tokens
     *
     *      If you use a Project Access Token, the API only has access to that
     *      project with the access level specified when creating the token.
     *
     *      You can create a connection for each project with the same Base URL
     *      and respective Project Access Token.
     *
     *      @see https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html
     *
     * @param array $log_channels
     *      The Laravel log channels to send all related info and error logs to
     *      for for this GitLab instance (connection).
     *
     *      If you leave this at the value of `['single']`, all API call logs
     *      will be sent to the default log file for Laravel that you have
     *      configured in `config/logging.php` which is usually
     *      `storage/logs/laravel.log`.
     *
     *      If you would like to see GitLab API logs in a separate log file that
     *      is easier to triage without unrelated log messages, you can create
     *      a custom log channel and add the channel name to the array. We
     *      recommend creating a custom channel (ex. `gitlab-sdk` for all
     *      connections or `gitlab-sdk-gitlab-com` for a specific connection),
     *      however you can choose any name you would like.
     *      Ex. ['single', 'gitlab-sdk']
     *
     *      You can also add additional channels that logs should be sent to.
     *      Ex. ['single', 'gitlab-sdk', 'slack']
     *
     *      @see https://laravel.com/docs/9.x/logging
     */
    'connections' => [

        // GitLab SaaS (GitLab.com)
        'saas' => [
            'base_url' => env('GITLAB_SAAS_BASE_URL', 'https://gitlab.com'),
            'access_token' => env('GITLAB_SAAS_ACCESS_TOKEN'),
            'log_channels' => ['single'],
        ],

        // Development and Testing
        'dev' => [
            'base_url' => env('GITLAB_DEV_BASE_URL', 'https://gitlab.com'),
            'access_token' => env('GITLAB_DEV_ACCESS_TOKEN'),
            'log_channels' => ['single'],
        ],

        // GitLab Self Managed
        'self_managed' => [
            'base_url' => env('GITLAB_SELF_MANAGED_BASE_URL'),
            'access_token' => env('GITLAB_SELF_MANAGED_ACCESS_TOKEN'),
            'log_channels' => ['single'],
        ],

        // Add additional self-managed instances or group/project token specific
        // connections with a snakecase alias referred to as a connection key

        // 'example' => [
        //     'base_url' => env('GITLAB_EXAMPLE_BASE_URL', 'https://gitlab.example.com'),
        //     'access_token' => env('GITLAB_EXAMPLE_ACCESS_TOKEN'),
        //     'log_channels' => ['single'],
        // ],

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

    ],

];
