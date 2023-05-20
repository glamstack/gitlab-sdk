# GitLab SDK

## Overview

The GitLab SDK is an open source [Composer](https://getcomposer.org/) package created by [GitLab IT Engineering](https://about.gitlab.com/handbook/it/engineering/dev/) for use in internal Laravel applications for connecting to GitLab SaaS or self-managed instances for provisioning and deprovisioning of users, groups, projects, and other related functionality.

> **Disclaimer:** This is not an official package maintained by the GitLab product and development teams. This is an internal tool that we use in the GitLab IT department that we have open sourced as part of our company values.
>
> Please use at your own risk and create merge requests for any bugs that you encounter.
>
> We do not maintain a roadmap of community feature requests, however we invite you to contribute and we will gladly review your merge requests.

### v2 to v3 Upgrade Guide

There are several breaking changes with v3.0. See the [v3.0 changelog](https://gitlab.com/gitlab-it/gitlab-sdk/-/blob/main/changelog/3.0.md) to learn more about what's changed and migration steps.

### Maintainers

| Name | GitLab Handle |
|------|---------------|
| [Dillon Wheeler](https://about.gitlab.com/company/team/#dillonwheeler) | [@dillonwheeler](https://gitlab.com/dillonwheeler) |
| [Jeff Martin](https://about.gitlab.com/company/team/#jeffersonmartin) | [@jeffersonmartin](https://gitlab.com/jeffersonmartin) |

### How It Works

The URL of your GitLab instance (SaaS or self-managed) and API Access Token is specified in `config/gitlab-sdk.php` using variables inherited from your `.env` file.

If your connection configuration is stored in your database and needs to be provided dynamically, the `config/gitlab-sdk.php` configuration file will be ignored when you pass an array to the `connection_config` parameter during initialization of the SDK. See [Dynamic Variable Connection per API Call](#dynamic-variable-connection-per-api-call) to learn more.

Instead of providing a method for every endpoint in the API documentation, we have taken a simpler approach by providing a universal `ApiClient` that can perform `GET`, `POST`, `PUT`, and `DELETE` requests to any endpoint that you find in the [GitLab API](https://docs.gitlab.com/ee/api/api_resources.html) documentation and handles the API response, error handling, and pagination for you.

This builds upon the simplicity of the [Laravel HTTP Client](https://laravel.com/docs/8.x/http-client) that is powered by the [Guzzle HTTP client](http://docs.guzzlephp.org/en/stable/) to provide "last lines of code parsing" for GitLab API responses to improve the developer experience.

The examples below are a getting started guide. See the [API Requests](#api-requests) and [API Responses](#api-responses) section below for more details.

```php
// Option 1. Initialize the SDK using the default connection
$gitlab_api = new \GitlabIt\Gitlab\ApiClient();

// Option 2. Initialize the SDK using a specific hard coded connection
$gitlab_api = new \GitlabIt\Gitlab\ApiClient('self_managed');

// Get a list of records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$records = $gitlab_api->get('/projects');

// Search for records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$records = $gitlab_api->get('/projects', [
    'search' => 'my-project-name',
    'membership' => true
]);

// Get a specific record
// https://docs.gitlab.com/ee/api/projects.html#get-single-project
$record = $gitlab_api->get('/projects/123456789');

// Create a project
// https://docs.gitlab.com/ee/api/projects.html#create-project
$record = $gitlab_api->post('/projects', [
    'name' => 'My Cool Project',
    'path' => 'my-cool-project'
]);

// Update a project
// https://docs.gitlab.com/ee/api/projects.html#edit-project
$project_id = '123456789';
$record = $gitlab_api->put('/projects/' . $project_id, [
    'description' => 'This is a cool project that we created for a demo.'
]);

// Delete a project
// https://docs.gitlab.com/ee/api/projects.html#delete-project
$project_id = '123456789';
$record = $gitlab_api->delete('/projects/' . $project_id);
```

## Installation

### Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | ^8.0, ^8.1, ^8.2 |
| Laravel     | ^8.0, ^9.0, ^10.0 |

### Add Composer Package

> Still using `glamstack/gitlab-sdk` (v2.x)? See the [v3.0 Upgrade Guide](https://gitlab.com/gitlab-it/gitlab-sdk/-/blob/main/changelog/3.0.md) for instructions to upgrade to `gitlab-it/gitlab-sdk:^3.0`.

```bash
composer require gitlab-it/gitlab-sdk
```

If you are contributing to this package, see [CONTRIBUTING.md](CONTRIBUTING.md) for instructions on configuring a local composer package with symlinks.

#### Publish the configuration file

The configuration file is used for pre-defining all of your connection keys and the `.env` variables that will be used for storing your credentials.

```bash
php artisan vendor:publish --tag=gitlab-sdk
```

## Environment Configuration

### Environment Variables

To get started, add the following variables to your `.env` file. You can add these anywhere in the file on a new line, or at the bottom of the file (your choice).

```
# .env

GITLAB_DEFAULT_CONNECTION="dev"
GITLAB_SAAS_BASE_URL="https://gitlab.com"
GITLAB_SAAS_ACCESS_TOKEN=""
GITLAB_DEV_BASE_URL="https://gitlab.com"
GITLAB_DEV_ACCESS_TOKEN=""
GITLAB_SELF_MANAGED_BASE_URL=""
GITLAB_SELF_MANAGED_ACCESS_TOKEN=""
```

### Connection Keys

We use the concept of **_connection keys_** (a.k.a. instance keys) that refer to a configuration array in `config/gitlab-sdk.php` that allows you to configure the Base URL, Access Token, exception handling, and log channels for each connection to the GitLab API and provide a unique name for that connection.

Each connection has a different Base URL and Access Token associated with it.

We have pre-configured the `saas` and `self_managed` keys. We have also pre-configured a `dev` key that can be used at your discretion with non-production API credentials for testing.

```
# config/gitlab-sdk.php

'connections' => [

    // GitLab SaaS (GitLab.com)
    'saas' => [
        'base_url' => env('GITLAB_SAAS_BASE_URL', 'https://gitlab.com'),
        'access_token' => env('GITLAB_SAAS_ACCESS_TOKEN'),
        'exceptions' => env('GITLAB_SAAS_EXCEPTIONS', false),
        'log_channels' => ['single'],
    ],

    // Development and Testing
    'dev' => [
        'base_url' => env('GITLAB_DEV_BASE_URL', 'https://gitlab.com'),
        'access_token' => env('GITLAB_DEV_ACCESS_TOKEN'),
        'exceptions' => env('GITLAB_DEV_EXCEPTIONS', false),
        'log_channels' => ['single'],
    ],

    // GitLab Self Managed
    'self_managed' => [
        'base_url' => env('GITLAB_SELF_MANAGED_BASE_URL'),
        'access_token' => env('GITLAB_SELF_MANAGED_ACCESS_TOKEN'),
        'exceptions' => env('GITLAB_SELF_MANAGED_EXCEPTIONS', false),
        'log_channels' => ['single'],
    ],
]
```

If you have the advanced use case of connecting to additional GitLab instances or [least privilege](#least-privilege) service accounts beyond what is pre-configured (ex. [Project Access Tokens](https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html) or [Group Access Tokens](https://docs.gitlab.com/ee/user/group/settings/group_access_tokens.html)), you can add additional connection keys in `config/gitlab-sdk.php` with the name of your choice and create new variables for the Base URl and API token using the other connections as examples.

### Default Global Connection

By default, the SDK will use the `dev` connection key for all API calls across your application unless you override the default connection with a different connection key using the `GITLAB_DEFAULT_CONNECTION` variable.

If you're just getting started, it is recommended to leave this at `dev`. You can change this to `saas` or `self_managed` later when deploying your application to staging or production environments.

```
GITLAB_DEFAULT_CONNECTION=dev
```

### Base URL

Most users are using GitLab SaaS (not hosting your own GitLab instance). This is the default value for the `saas` and `dev` connection keys and does not need to be customized or defined in your `.env` file.

```
https://gitlab.com
```

If you are hosting your own GitLab instance (ex. Omnibus), this is referred to as a self managed instance and you need to provide the URL that you use to sign in to the GitLab instance.

```
https://gitlab.example.com
```

The `/api/v4` URI path is automatically added to each API request and is not included in the Base URL. All API requests will start with a forward slash and the endpoint (ex. `/groups`) as it is displayed on the GitLab API documentation page for each respective endpoint.

### Access Tokens

You need to generate an access token on your GitLab instance and update the appropriate `GITLAB_{CONNECTION}_ACCESS_TOKEN` variable in your `.env` file.

**We recommend reading more about [Personal Access Tokens](#personal-access-tokens), [Group Access Tokens](#group-access-tokens), [Project Access Tokens](#project-access-tokens), and [Security Best Practices](#security-best-practices) before creating an API token for your application.**

All API endpoints require the `api` or `read_api` scope. If you are not performing any read-write operations, it is recommended to use the `read_api` scope as a proactive security measure.

```
GITLAB_DEFAULT_CONNECTION="dev"
GITLAB_SAAS_BASE_URL="https://gitlab.com"
GITLAB_SAAS_ACCESS_TOKEN=""
GITLAB_DEV_BASE_URL="https://gitlab.com"
GITLAB_DEV_ACCESS_TOKEN=""
GITLAB_SELF_MANAGED_BASE_URL=""
GITLAB_SELF_MANAGED_ACCESS_TOKEN=""
```

#### Personal Access Tokens

A Personal Access Token provides access to all of the groups and projects that your user account has access to. **This is a widely permissive token and should be used carefully.** This is only recommended for use cases that perform admin-level API calls or need access to multiple groups that cannot be performed with a [Group Access Token](#group-access-tokens).

[https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html](https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html)

#### Group Access Tokens

A Group Access Token only provides access to the specific GitLab group and any child groups and GitLab projects. **This is the recommended type of token for most use cases.**

[https://docs.gitlab.com/ee/user/group/settings/group_access_tokens.html](https://docs.gitlab.com/ee/user/group/settings/group_access_tokens.html)

#### Project Access Tokens

A Project Access Token only provides access to the specific GitLab project that it is created for and is associated with a bot user based on the name of the API key that you create. Unless you are only using this SDK for performing operations in a single project, it is recommended to use a Group Access Token or Personal Access Token or read more about how we support [Least Privilege](#least-privilege).

[https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html](https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html)

### Security Best Practices

#### GitLab Project Permissions

Each API call maps to a specific permission that is allowed for one or more roles.

You should not configure `Owner` or `Maintainer` over-permissive roles for a Group Access Token or Project Access Token unless you have API calls that specifically require this permission level.

[https://docs.gitlab.com/ee/user/permissions.html#project-members-permissions](https://docs.gitlab.com/ee/user/permissions.html#project-members-permissions)

#### No Shared Tokens

Don't use an access token that you have already created for another purpose. You should generate a new Access Token for each use case.

This is helpful during security incidents when a key needs to be revoked on a compromised system and you don't want other systems that use the same user or service account to be affected since they use a different key that wasn't revoked.

#### Access Token Storage

Don't add your access tokens to the `.env.example` or `config/gitlab-sdk.php` files to avoid committing your credentials to your repository (secret leak). All access tokens should be defined in the `.env` file which is included in `.gitignore` and not committed to your repository.

It is recommended to store a copy of each access token in your preferred password manager (ex. 1Password, LastPass, etc.) and/or secrets vault (ex. HashiCorp Vault, Ansible, etc.).

#### Bot and Service Accounts

You can optionally create a "bot"/"service account" user that has explicitly granted access to groups and projects that you specify in the GitLab UI.

This is useful if you do not want API calls performed on behalf of a specific human user. You will need to create a Personal Access Token while signed into GitLab as the service account user.

### Least Privilege

If you need to use different tokens for each group or project for least privilege security reasons, you can customize `config/gitlab-sdk.php` to add the same GitLab instance multiple times with different instance keys (ex. `project_alias1`, `project_alias2`, `group_alias1`, `group_alias2`.

This allows you to use the respective [Personal Access Token](#personal-access-tokens), [Group Access Token](#group-access-tokens), or a [Project Access Token](#project-access-tokens).

```php
'project_alias1' => [
    'base_url' => env('GITLAB_PROJECT_ALIAS1_BASE_URL', 'https://gitlab.com'),
    'access_token' => env('GITLAB_PROJECT_ALIAS1_ACCESS_TOKEN'),
],

'project_alias2' => [
    'base_url' => env('GITLAB_PROJECT_ALIAS2_BASE_URL', 'https://gitlab.com'),
    'access_token' => env('GITLAB_PROJECT_ALIAS2_ACCESS_TOKEN'),
],
```

You simply need to provide the instance key when invoking the SDK.

```php
$gitlab_api = new \GitlabIt\Gitlab\ApiClient('project_alias1');
$project = $gitlab_api->get('/projects/123456789')->object();
```

Alternatively, you can provide a different API key when initializing the service using the second argument. The API token from `config/gitlab-sdk.php` is used if the second argument is not provided. This is helpful if your GitLab Access Tokens are stored in your database and are not hard coded into your `.env` file.

```php
// Get the access token from a model in your application.
// Disclaimer: This is an example and is not a feature of the SDK.
$demo_project = App\Models\DemoProject::where('id', $id)->firstOrFail();
$access_token = decrypt($demo_project->gitlab_access_token);

// Use the SDK to connect using your access token.
$gitlab_api = new \GitlabIt\Gitlab\ApiClient('gitlab_com', $access_token);
$project = $gitlab_api->get('/projects/123456789')->object();
```

### Logging Configuration

By default, we use the `single` channel for all logs that is configured in your application's `config/logging.php` file. This sends all GitLab API log messages to the `storage/logs/laravel.log` file.

If you would like to see GitLab API logs in a separate log file that is easier to triage without unrelated log messages, you can create a custom log channel. For example, we recommend using the value of `gitlab-sdk`, however you can choose any name you would like.

Add the custom log channel to `config/logging.php`.

```php
    'channels' => [

        // Add anywhere in the `channels` array

        'gitlab-sdk' => [
            'name' => 'gitlab-sdk',
            'driver' => 'single',
            'level' => 'debug',
            'path' => storage_path('logs/gitlab-sdk.log'),
        ],
    ],
```

Update the `channels.stack.channels` array to include the array key (ex. `gitlab-sdk`) of your custom channel. Be sure to add `gitlab-sdk` to the existing array values and not replace the existing values.

```php
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single','slack', 'gitlab-sdk'],
            'ignore_exceptions' => false,
        ],
    ],
```

## Initializing the API Connection

To use the default connection, you do **_not_** need to provide the **_connection key_** to the `ApiClient`. This allows you to build your application without hard coding a connection key and simply update the `.env` variable.

```php
// Initialize the SDK
$gitlab_api = new \GitlabIt\Gitlab\ApiClient();

// Get a list of records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$projects = $gitlab_api->get('/projects');
```

#### Using a Specific Connection per API Call

If you want to use a specific **_connection key_** when using the `ApiClient` that is different from the `GITLAB_DEFAULT_CONNECTION` `.env` variable, you can pass any **_connection key_** that has been configured in `config/okta-sdk.php` as the first construct argument for the `ApiClient`.

```php
// Initialize the SDK
$gitlab_api = new \GitlabIt\Gitlab\ApiClient('self_managed');

// Get a list of records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$projects = $gitlab_api->get('/projects');
```

> If you encounter errors, ensure that the API token has been added to your `.env` file in the `GITLAB_{CONNECTION_KEY}_ACCESS_TOKEN` variable.

### Dynamic Variable Connection per API Call

If not utilizing a connection key in the `config/gitlab-sdk.php` configuration file, you can pass an array as the second argument with a custom connection configuration.

```php
// Initialize the SDK
$gitlab_api = new \GitlabIt\Gitlab\ApiClient(null, [
    'base_url' => 'https://gitlab.com',
    'access_token' => 'glpat-A1B2C3D4E5F6G7h8i9j0k',
    'exceptions' => false,
    'log_channels' => ['single', 'gitlab-sdk']
]);
```

> **Security Warning:** Do not commit a hard coded API token into your code base. This should only be used when using dynamic variables that are stored in your database.

Here is an example of how you can use your own Eloquent model to store your GitLab instances and provide them to the SDK. You can choose whether to provide dynamic log channels as part of your application logic or hard code the channels that you have configured in your application that uses the SDK.

```php
// The $gitlab_instance_id is provided dynamically in the controller or service request

// Get GitLab Instance
// Disclaimer: This is an example and is not a feature of the SDK.
$gitlab_instance = \App\Models\GitlabInstance::query()
    ->where('id', $gitlab_instance_id)
    ->firstOrFail();

// Initialize the SDK
$okta_api = new \GitlabIt\Okta\ApiClient(null, [
    'base_url' => $gitlab_instance->api_base_url,
    'access_token' => decrypt($gitlab_instance->access_token),
    'exceptions' => false,
    'log_channels' => ['single', 'gitlab-sdk']
]);
```

## API Requests

You can make an API request to any of the resource endpoints in the [GitLab REST API Documentation](https://docs.gitlab.com/ee/api/api_resources.html).

#### Inline Usage

```php
// Initialize the SDK
$gitlab_api = new \GitlabIt\Gitlab\ApiClient('gitlab_com');
```

### GET Requests

The endpoint starts with a leading `/` after `/api/v4`. The GitLab API documentation provides the endpoint verbatim. We have aligned our methods with the API documentation for your convenience.

For example, the [List all projects](https://docs.gitlab.com/ee/api/projects.html#list-all-projects) API documentation shows the endpoint.

```
GET /projects
```

With the SDK, you use the `get()` method with the endpoint `/projects` as the first argument.

```php
$gitlab_api->get('/projects');
```

You can also use variables or database models to get data for constructing your endpoints.

```php
$endpoint = '/projects';
$records = $gitlab_api->get($endpoint);
```

Here are some more examples of using endpoints.

```php
// Get a list of records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$records = $gitlab_api->get('/projects');

// Get a specific record
// https://docs.gitlab.com/ee/api/projects.html#get-single-project
$record = $gitlab_api->get('/projects/123456789');

// Get a specific record using a variable
$demo_project = App\Models\DemoProject::where('id', $id)->firstOrFail();
$gitlab_project_id = $demo_project->gitlab_project_id;
$record = $gitlab_api->get('/projects/' . $gitlab_project_id);
```

### GET Requests with Query String Parameters

The second argument of a `get()` method is an optional array of parameters that is parsed by the SDK and the [Laravel HTTP Client](https://laravel.com/docs/8.x/http-client#get-request-query-parameters) and rendered as a query string with the `?` and `&` added automatically.

```php
// Search for records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$records = $gitlab_api->get('/projects', [
    'search' => 'my-project-name',
    'membership' => true
]);

// This will parse the array and render the query string
// https://gitlab.com/api/v4/projects?search=my-project-name&membership=true
```

### POST Requests

The `post()` method works almost identically to a `get()` request with an array of parameters, however the parameters are passed as form data using the `application/json` content type rather than in the URL as a query string. This is industry standard and not specific to the SDK.

You can learn more about request data in the [Laravel HTTP Client documentation](https://laravel.com/docs/8.x/http-client#request-data).

```php
// Create a project
// https://docs.gitlab.com/ee/api/projects.html#create-project
$record = $gitlab_api->post('/projects', [
    'name' => 'My Cool Project',
    'path' => 'my-cool-project'
]);
```

### PUT Requests

The `put()` method is used for updating an existing record (similar to `PATCH` requests). You need to ensure that the ID of the record that you want to update is provided in the first argument (URI).

In most applications, this will be a variable that you get from your database or another location and won't be hard-coded.

```php
// Update a project
// https://docs.gitlab.com/ee/api/projects.html#edit-project
$project_id = '123456789';
$record = $gitlab_api->put('/projects/' . $project_id, [
    'description' => 'This is a cool project that we created for a demo.'
]);
```

### DELETE Requests

The `delete()` method is used for methods that will destroy the resource based on the ID that you provide.

Keep in mind that `delete()` methods will return different status codes depending on the vendor (ex. 200, 201, 202, 204, etc). GitLab's API will return a `204` status code for successfully deleted resources.

```php
// Delete a project
// https://docs.gitlab.com/ee/api/projects.html#delete-project
$project_id = '123456789';
$record = $gitlab_api->delete('/projects/' . $project_id);
```

### Class Methods

The examples above show basic inline usage that is suitable for most use cases. If you prefer to use classes and constructors, the example below will provide a helpful example.

```php
<?php

use GitlabIt\Gitlab\ApiClient;

class GitlabProjectService
{
    protected $gitlab_api;

    public function __construct($connection_key = null)
    {
        $connection = $connection_key ?? config('gitlab-sdk.auth.default_connection');

        $this->gitlab_api = new \GitlabIt\Gitlab\ApiClient($connection);
    }

    public function listProjects($query = [])
    {
        $projects = $this->gitlab_api->get('/projects', $query);

        return $projects->object;
    }

    public function getProject($id, $query = [])
    {
        $project = $this->gitlab_api->get('/projects/' . $id, $query);

        return $project->object;
    }

    public function storeProject($request_data)
    {
        $project = $this->gitlab_api->post('/projects', $request_data);

        // To return an object with the newly created project
        return $project->object;

        // To return the ID of the newly created project
        // return $project->object->id;

        // To return the status code of the form request
        // return $project->status->code;

        // To return a bool with the status of the form request
        // return $project->status->successful;

        // To return the entire API response with the object, json, headers, and status
        // return $project;
    }

    public function updateProject($id, $request_data)
    {
        $project = $this->gitlab_api->put('/projects/' . $id, $request_data);

        // To return an object with the updated created project
        return $project->object;

        // To return a bool with the status of the form request
        // return $project->status->successful;
    }

    public function deleteProject($id)
    {
        $project = $this->gitlab_api->delete('/projects/' . $id);

        return $project->status->successful;
    }
}
```

## API Responses

This SDK uses the GitLab IT SDK standards for API response formatting.

```php
// API Request
$project = $gitlab_api->get('/projects/32589035');

// API Response
$project->headers; // object
$project->json; // json
$project->object; // object
$project->status; // object
$project->status->code; // int (ex. 200)
$project->status->ok; // bool
$project->status->successful; // bool
$project->status->failed; // bool
$project->status->serverError; // bool
$project->status->clientError; // bool
```

#### API Response Headers

```php
$project = $gitlab_api->get('/projects/32589035');
$project->headers;
```

```json
{
    +"Date": "Thu, 06 Jan 2022 21:40:18 GMT",
    +"Content-Type": "application/json",
    +"Transfer-Encoding": "chunked",
    +"Connection": "keep-alive",
    +"Cache-Control": "max-age=0, private, must-revalidate",
    +"Etag": "W/"ed65096017d349b25371385b9b96d102"",
    +"Vary": "Origin",
    +"X-Content-Type-Options": "nosniff",
    +"X-Frame-Options": "SAMEORIGIN",
    +"X-Request-Id": "01FRRNBQCPG9XKBY8211NEH285",
    +"X-Runtime": "0.100822",
    +"Strict-Transport-Security": "max-age=31536000",
    +"Referrer-Policy": "strict-origin-when-cross-origin",
    +"RateLimit-Observed": "6",
    +"RateLimit-Remaining": "1994",
    +"RateLimit-Reset": "1641505278",
    +"RateLimit-ResetTime": "Thu, 06 Jan 2022 21:41:18 GMT",
    +"RateLimit-Limit": "2000",
    +"GitLab-LB": "fe-12-lb-gprd",
    +"GitLab-SV": "localhost",
    +"CF-Cache-Status": "DYNAMIC",
    +"Expect-CT": "max-age=604800, report-uri="https://report-uri.cloudflare.com/cdn-cgi/beacon/expect-ct"",
    +"Report-To": "{"endpoints":[{"url":"https:\/\/a.nel.cloudflare.com\/report\/v3?s=LWJRP1mJdxCzclW3zKzqg40CbYJeUJ2mf2aRLBRfzxWvAgh15LrCQwpmqtk%2B4cJoDWsX3bx1yAkEB9HuokEMgKg%2FMkFXLoy2N8oE09KfHIH%2B8YWjBmX%2BdUD4hkg%3D"}],"group":"cf-nel","max_age":604800}",
    +"NEL": "{"success_fraction":0.01,"report_to":"cf-nel","max_age":604800}",
    +"Server": "cloudflare",
    +"CF-RAY": "6c981a9ba95639a7-SEA",
}
```

#### API Response Specific Header

```php
$headers = (array) $project->headers;
$content_type = $headers['Content-Type'];
```

```bash
application/json
```

#### API Response JSON

```php
$project = $gitlab_api->get('/projects/32589035');
$project->json;
```

```json
"{"id":32589035,"description":"","name":"gitlab-sdk","name_with_namespace":"gitlab-it \/ gitlab-sdk","path":"gitlab-sdk","path_with_namespace":"gitlab-it\/gitlab-sdk"}"
```

#### API Response Object

```php
$project = $gitlab_api->get('/projects/32589035');
$project->object;
```

```php
{
  +"id": 32589035
  +"description": ""
  +"name": "gitlab-sdk"
  +"name_with_namespace": "gitlab-it / gitlab-sdk"
  +"path": "gitlab-sdk"
  +"path_with_namespace": "gitlab-it/gitlab-sdk"
}
```

#### API Response Status

See the [Laravel HTTP Client documentation](https://laravel.com/docs/8.x/http-client#error-handling) to learn more about the different status booleans.

```php
$project = $gitlab_api->get('/projects/32589035');
$project->status;
```

```php
{
  +"code": 200
  +"ok": true
  +"successful": true
  +"failed": false
  +"serverError": false
  +"clientError": false
}
```

#### API Response Status Code

```php
$project = $gitlab_api->get('/projects/32589035');
$project->status->code;
```

```bash
200
```

## Error Handling

You can choose to throw exceptions or parse the `$response->status` object.

### Throwable Exceptions

#### Configuration Errors

A `\GitlabIt\Gitlab\Exceptions\ConfigurationException` will always be thrown with a 501 error if you have not configured the `.env` variables for a connection key. All messages are descriptive with instructions to correct the error.

| Event Type | Message |
|------------|---------|
| `gitlab-api-config-missing-error` | The GitLab `$parameter` is not defined in the ApiClient construct connection_config array provided. This is a required parameter to be passed in not using the configuration file and connection_key initialization method. |
| `gitlab-api-config-missing-error` | The GitLab SDK connection_config array provided in the ApiClient construct connection_config array size should be 3 but `count($connection_config)` array keys were provided. |
| `gitlab-api-config-missing-key-error` | The `$this->connection_key` connection key is not defined in `config/gitlab-sdk.php` connections array. |
| `gitlab-api-config-missing-url-error` | You need to add the `GITLAB_{CONNECTION}_BASE_URL` variable in your `.env` file (ex. `https://gitlab.com` or `https://gitlab.example.com`). |
| `gitlab-api-config-missing-token-error` | You need to add the `GITLAB_{CONNECTION}_ACCESS_TOKEN` variable in your `.env` file so you can perform authenticated API calls. |
| `gitlab-api-config-invalid-error` | The `GITLAB_{CONNECTION}_ACCESS_TOKEN` has been configured but is invalid (does not exist or has expired). Please generate a new Access Token and update the variable in your `.env` file. |

#### HTTP Exceptions

See the [GitLab Rest API Documentation](https://docs.gitlab.com/ee/api/#status-codes) to learn more about the status codes that can be returned. More information on each resource endpoint can be found on the respective [API documentation page](https://docs.gitlab.com/ee/api/api_resources.html).

**All HTTP response throwable exceptions are disabled by default unless enabled in your `.env` file.** You can enable exceptions on each connection key by setting `GITLAB_{CONNECTION}_EXCEPTIONS=true` in your `.env` file.

Since exceptions cause 500 errors for clients even for 4xx responses, they should only be used when you have expected behavior that you want to catch any errors for.

The exception message by default is `{HTTP_METHOD} {STATUS_CODE} {URL}`. If the API response includes an `$response->object->message`, then that message is appended to the end of the exception message.

| Status Code | Exception                                           |
|-------------|-----------------------------------------------------|
| 400 | `\GitlabIt\Gitlab\Exceptions\BadRequestException`           |
| 401 | `\GitlabIt\Gitlab\Exceptions\UnauthorizedException`         |
| 403 | `\GitlabIt\Gitlab\Exceptions\ForbiddenException`            |
| 404 | `\GitlabIt\Gitlab\Exceptions\NotFoundException`             |
| 405 | No exception is thrown (should only happen in development). |
| 412 | `\GitlabIt\Gitlab\Exceptions\PreconditionFailedException`   |
| 422 | `\GitlabIt\Gitlab\Exceptions\UnprocessableException`        |
| 429 | `\GitlabIt\Gitlab\Exceptions\RateLimitException`            |
| 500 | `\GitlabIt\Gitlab\Exceptions\ServerErrorException`          |

#### Catching Exceptions

You can catch an exception and handle the error gracefully.

```php
try {
    $group = $gitlab_api->get('/groups/' . $group_id);
} catch (\GitlabIt\Gitlab\Exceptions\NotFoundException $e) {
    return redirect()->route('reports.gitlab.groups.index')
        ->with('error', 'The group could not be found.');
}
```

You can chain multiple catches together if needed.

```php
try {
    $group = $gitlab_api->get('/groups/' . $group_id);
} catch (\GitlabIt\Gitlab\Exceptions\NotFoundException $e) {
    return redirect()->route('reports.gitlab.groups.index')
        ->with('error', 'The group could not be found.');
} catch (\GitlabIt\Gitlab\Exceptions\RateLimitException $e) {
    return redirect()->route('reports.gitlab.groups.index')
        ->with('error', $e->getMessage());
}
```

Any uncaught exceptions are passed on to the user in your application or returned as a 500 error to the user and the exception is sent to your bug reporting service (ex. Bugsnag, Sentry, etc.) based on your `config/logging.php` configuration.

#### Catching Errors

You can catch errors using the [API Response Status](#api-response-status) if exceptions are disabled.

```php
$group = $gitlab_api->get('/groups/' . $validated['group_id']);

switch ($group->status->code) {
    case 404:
        return redirect()->route('reports.gitlab.groups.index')
            ->with('error', $group->object->message);
    case 429:
        return redirect()->route('reports.gitlab.groups.index')
            ->with('error', $group->object->message);
}
```

### Log Outputs

When the `ApiClient` class is invoked for the first time, an API connection test is performed to the `/version` endpoint of the GitLab instance. This endpoint requires authentication, so this validates whether the Access Token is valid. If successful, the GitLab version number is then included in `gitlab_version` in the log entry.

```
[2023-05-17 21:32:31] local.INFO: GET 200 https://gitlab.com/api/v4/version {"api_endpoint":"https://gitlab.com/api/v4/version","api_method":"GET","class":"GitlabIt\\Gitlab\\ApiClient","connection_key":"dev","gitlab_version":null,"status_code":200,"event_type":"gitlab-api-response-info"}
```

Every log entry has a timestamp and message. There is an additional log context array with standardized outputs to help you debug.

```
[2023-05-17 21:32:32] local.WARNING: GET 404 https://gitlab.com/api/v4/groups/123 {"api_endpoint":"https://gitlab.com/api/v4/groups/123","api_method":"GET","class":"GitlabIt\\Gitlab\\ApiClient","connection_key":"dev","gitlab_version":"16.0.0-pre","status_code":404,"event_type":"gitlab-api-response-error-not-found","reason":"{\"message\":\"404 Group Not Found\"}"}
```

The HTTP status code for the API response is included in each log entry in the message and in the JSON `status_code`.

#### Event Types

The `event_type` log context key corresponds with the HTTP status code and is designed for human readable log filtering.

| Status Code | Event Type                              |
|-------------|-----------------------------------------|
| 200 | `gitlab-api-response-info`                      |
| 201 | `gitlab-api-response-created`                   |
| 202 | `gitlab-api-response-accepted`                  |
| 204 | `gitlab-api-response-deleted`                   |
| 400 | `gitlab-api-response-error-bad-request`         |
| 401 | `gitlab-api-response-error-unauthorized`        |
| 403 | `gitlab-api-response-error-forbidden`           |
| 404 | `gitlab-api-response-error-not-found`           |
| 405 | `gitlab-api-response-error-method-not-allowed`  |
| 412 | `gitlab-api-response-error-precondition-failed` |
| 422 | `gitlab-api-response-error-unprocessable`       |
| 429 | `gitlab-api-response-error-rate-limit`          |
| 500 | `gitlab-api-response-error-server`              |

#### Reasons

All messages related to SDK errors are descriptive with instructions to correct the error.

If an API call returns an error message in `$response->object->error`, it will be added to the `reason` log context key.

## Issue Tracking and Bug Reports

> Disclaimer: This is not an official package maintained by the GitLab product and development teams. This is an internal tool that we use in the GitLab IT department that we have open sourced as part of our company values.
>
> Please use at your own risk and create merge requests for any bugs that you encounter.
>
> We do not maintain a roadmap of community feature requests, however we invite you to contribute and we will gladly review your merge requests.

For GitLab team members, please create an issue in [gitlab-it/gitlab-sdk](https://gitlab.com/gitlab-it/gitlab-sdk/-/issues) (public) or [gitlab-com/it/dev/issue-tracker](https://gitlab.com/gitlab-com/it/dev/issue-tracker/-/issues) (confidential).

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) to learn more about how to contribute.
