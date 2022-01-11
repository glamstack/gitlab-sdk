# GitLab SDK

## Overview

The GitLab SDK is an open source [Composer](https://getcomposer.org/) package created by [GitLab IT Engineering](https://about.gitlab.com/handbook/business-technology/engineering/) for use in the [GitLab Access Manager](https://gitlab.com/gitlab-com/business-technology/engineering/access-manager) Laravel application for connecting to multiple GitLab instances for provisioning and deprovisioning of users, groups, group members, projects, and other related functionality.

> **Disclaimer:** This is not an official package maintained by the GitLab product and development teams. This is an internal tool that we use in the IT department that we have open sourced as part of our company values.
>
> Please use at your own risk and create issues for any bugs that you encounter.
>
> We do not maintain a roadmap of community feature requests, however we invite you to contribute and we will gladly review your merge requests.

### Maintainers

| Name | GitLab Handle |
|------|---------------|
| [Dillon Wheeler](https://about.gitlab.com/company/team/#dillonwheeler) | [@dillonwheeler](https://gitlab.com/dillonwheeler) |
| [Jeff Martin](https://about.gitlab.com/company/team/#jeffersonmartin) | [@jeffersonmartin](https://gitlab.com/jeffersonmartin) |

### How It Works

The URL of your GitLab instance (SaaS or self-managed) and API Access Token is specified in `config/glamstack-gitlab.php` using variables inherited from your `.env` file.

The package is not intended to provide functions for every endpoint in the GitLab API.

We have taken a simpler approach by providing a universal `ApiClient` that can perform `GET`, `POST`, `PUT`, and `DELETE` requests to any endpoint that you find in the GitLab API documentation and handles the API response, error handling, and pagination for you.

This builds upon the simplicity of the [Laravel HTTP Client](https://laravel.com/docs/8.x/http-client) that is powered by the [Guzzle HTTP client](http://docs.guzzlephp.org/en/stable/) to provide "last lines of code parsing" for GitLab API responses to improve the developer experience.

We have additional classes and methods for the endpoints that GitLab Access Manager uses frequently that we will [iterate](https://about.gitlab.com/handbook/values/#iteration) upon over time.

```php
// Initialize the SDK
$gitlab_api = new \Glamstack\Gitlab\ApiClient('gitlab_com');

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
$record = $gitlab_api->put('/projects/'.$project_id, [
    'description' => 'This is a cool project that we created for a demo.'
]);

// Delete a project
// https://docs.gitlab.com/ee/api/projects.html#delete-project
$project_id = '123456789';
$record = $gitlab_api->delete('/projects/'.$project_id);
```

## Installation

### Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | >=8.0   |
| Laravel     | >=8.0   |

### Add Composer Package

```bash
composer require glamstack/gitlab-sdk
```

> If you are contributing to this package, see `CONTRIBUTING.md` for instructions on configuring a local composer package with symlinks.

### Custom Logging Configuration

By default, we use the `single` channel for all logs that is configured in your application's `config/logging.php` file. This sends all GitLab API log messages to the `storage/logs/laravel.log` file.

If you would like to see GitLab API logs in a separate log file that is easier to triage without unrelated log messages, you can create a custom log channel. For example, we recommend using the value of `glamstack-gitlab`, however you can choose any name you would like.

Add the custom log channel to `config/logging.php`.

```php
    'channels' => [

        // Add anywhere in the `channels` array

        'glamstack-gitlab' => [
            'name' => 'glamstack-gitlab',
            'driver' => 'single',
            'level' => 'debug',
            'path' => storage_path('logs/glamstack-gitlab.log'),
        ],
    ],
```

Update the `channels.stack.channels` array to include the array key (ex. `glamstack-gitlab`) of your custom channel. Be sure to add `glamstack-gitlab` to the existing array values and not replace the existing values.

```php
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single','slack', 'glamstack-gitlab'],
            'ignore_exceptions' => false,
        ],
    ],
```

## Access Tokens

You need to generate an access token on your GitLab instance and update the appropriate `GITLAB_*_ACCESS_TOKEN` variable in your `.env` file.

### Environment Configuration

#### GitLab.com (SaaS)

If you use GitLab.com (SaaS instance), add the following variable to your `.env` file.

```bash
GITLAB_COM_ACCESS_TOKEN=""
```

#### GitLab Self-Managed (Private)

If you use a self-managed (private) GitLab instance, add the following variables to your `.env` file.

```bash
GITLAB_PRIVATE_BASE_URL=""
GITLAB_PRIVATE_ACCESS_TOKEN=""
```

#### Custom Configuration

If you want to change the variable name from `GITLAB_PRIVATE`, add multiple instances that you can connect to, or configure least privilege access tokens for specific projects or groups of projects, you can publish the configuration file to `config/glamstack-gitlab.php` file which has customization instructions inside.

```bash
php artisan vendor:publish --tag=glamstack-gitlab
```

### Personal Access Tokens

A Personal Access Token provides access to all of the groups and projects that your user account has access to. This is recommended for use cases that handle provisioning-related tasks. Most use cases require the `api` scope and some repository file tasks will need the `write_repository` scope.

[https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html](https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html)

### Project Access Tokens

A Project Access Token only provides access to the specific GitLab project that it is created for and is associated with a bot user based on the name of the API key that you create. Unless you are only using this SDK for performing operations in a single project, it is recommended to use a Personal Access Token or read more about how we support [Least Privilege](#least-privilege).

[https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html](https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html)

### Security Best Practices

#### No Shared Tokens

Don't use an access token that you have already created for another purpose. You should generate a new Access Token for each use case.

This is helpful during security incidents when a key needs to be revoked on a compromised system and you don't want other systems that use the same user or service account to be affected since they use a different key that wasn't revoked.

#### Access Token Storage

Don't add your access tokens to the `.env.example` or `config/glamstack-gitlab.php` files to avoid committing your credentials to your repository (secret leak). All access tokens should be defined in the `.env` file which is included in `.gitignore` and not committed to your repository.

It is a recommended to store a copy of each access token in your preferred password manager (ex. 1Password, LastPass, etc.) and/or secrets vault (ex. HashiCorp Vault, Ansible, etc.).

#### Bot and Service Accounts

You can optionally create a "bot"/"service account" user that has explicitly granted access to groups and projects that you specify in the GitLab UI.

This is useful if you do not want API calls performed on behalf of a specific human user. You will need to create a Personal Access Token while signed into GitLab as the service account user.

#### Least Privilege

If you need to use different tokens for each group or project for least privilege security reasons, you can customize `config/glamstack-gitlab.php` to add the same GitLab instance multiple times with different instance keys (ex. `project_alias1`, `project_alias2`, `group_alias1`, `group_alias2`.

This allows you to have a unique service account user and respective [Personal Access Tokens](#personal-access-tokens) for each group of projects, or a [Project Access Tokens](#project-access-tokens) for each project.

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

You simply need to provide the instance key when invoking the SDK, and you may need to store the instance keys in your application's database for dynamically rendered pages.

```php
$gitlab_api = new \Glamstack\Gitlab\ApiClient('project_alias1');
$project = $gitlab_api->get('/projects/123456789')->object();
```

Alternatively, you can provide a different API key when initializing the service using the second argument. The API token from `config/glamstack-gitlab.php` is used if the second argument is not provided. This is helpful if your GitLab Access Tokens are stored in your database and are not hard coded into your `.env` file.

```php
// Get the access token from a model in your application.
// Disclaimer: This is an example and is not a feature of the SDK.
$demo_project = App\Models\DemoProject::where('id', $id)->firstOrFail();
$access_token = decrypt($demo_project->gitlab_access_token);

// Use the SDK to connect using your access token.
$gitlab_api = new \Glamstack\Gitlab\ApiClient('gitlab_com', $access_token);
$project = $gitlab_api->get('/projects/123456789')->object();
```

## API Requests

You can make an API request to any of the resource endpoints in the [GitLab REST API Documentation](https://docs.gitlab.com/ee/api/api_resources.html).

#### Inline Usage

```php
// Initialize the SDK
$gitlab_api = new \Glamstack\Gitlab\ApiClient('gitlab_com');
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
$record = $gitlab_api->get('/projects/'.$gitlab_project_id);
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
$record = $gitlab_api->put('/projects/'.$project_id, [
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
$record = $gitlab_api->delete('/projects/'.$project_id);
```

### Class Methods

The examples above show basic inline usage that is suitable for most use cases. If you prefer to use classes and constructors, the example below will provide a helpful example.

```php
<?php

use Glamstack\Gitlab\ApiClient;

class GitlabProjectService
{
    protected $gitlab_api;

    public function __construct($instance_key = 'gitlab_com')
    {
        $this->gitlab_api = new \Glamstack\Gitlab\ApiClient($instance_key);
    }

    public function listProjects($query = [])
    {
        $projects = $this->gitlab_api->get('/projects', $query);

        return $projects->object;
    }

    public function getProject($id, $query = [])
    {
        $project = $this->gitlab_api->get('/projects/'.$id, $query);

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
        $project = $this->gitlab_api->put('/projects/'.$id, $request_data);

        // To return an object with the updated created project
        return $project->object;

        // To return a bool with the status of the form request
        // return $project->status->successful;
    }

    public function deleteProject($id)
    {
        $project = $this->gitlab_api->delete('/projects/'.$id);

        return $project->status->successful;
    }
}
```

## API Responses

This SDK uses the GLAM Stack standards for API response formatting.

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
"{"id":32589035,"description":"","name":"gitlab-sdk","name_with_namespace":"GitLab.com \/ Business Technology \/ IT Engineering \/ GitLab Access Manager \/ packages \/ composer \/ gitlab-sdk","path":"gitlab-sdk","path_with_namespace":"gitlab-com\/business-technology\/engineering\/access-manager\/packages\/composer\/gitlab-sdk"}"
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
  +"name_with_namespace": "GitLab.com / Business Technology / IT Engineering / GitLab Access Manager / packages / composer / gitlab-sdk"
  +"path": "gitlab-sdk"
  +"path_with_namespace": "gitlab-com/business-technology/engineering/access-manager/packages/composer/gitlab-sdk"
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

## Issue Tracking and Bug Reports

Please visit our [issue tracker](https://gitlab.com/gitlab-com/business-technology/engineering/access-manager/packages/composer/gitlab-sdk/-/issues) and create an issue or comment on an existing issue.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) to learn more about how to contribute.
