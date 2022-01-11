<?php

namespace Glamstack\Gitlab;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiClient
{
    private ?string $access_token;
    private string $base_url;
    private ?string $error_message;
    private string $gitlab_version;
    private ?array $request_headers;

    public function __construct(string $instance_key = 'gitlab_com', string $access_token = null)
    {
        // Set access token property using custom access token or null value
        // If not null, this will override the config/glamstack-gitlab.php
        // and/or .env value for this instance base URL.
        $this->access_token = $access_token;

        // Set request headers
        $this->setRequestHeaders();

        // Establish API connection
        $api_connection = $this->setApiConnectionVariables($instance_key);

        if ($api_connection == false) {
            abort(501, $this->error_message);
        }

        // Test API Connection and set $gitlab_version property for logs
        $this->testConnection();
    }

    /**
     * Set the URL and access token used for GitLab API calls.
     *
     * @param string $instance_key The key of the array in config/glamstack-gitlab.php
     *
     * @return bool
     */
    public function setApiConnectionVariables(string $instance_key): bool
    {
        // Get the instance configuration from config/glamstack-gitlab.php array
        /** @phpstan-ignore-next-line */
        if (!array_key_exists($instance_key, config('glamstack-gitlab'))) {
            $this->error_message = 'The GitLab instance key is not defined in ' .
                'config/glamstack-gitlab.php. Without this array config, ' .
                'there is no API Base URL or API Access Token to connect with.';

            Log::stack(config('glamstack-gitlab.log_channels'))
                ->critical($this->error_message, [
                    'log_event_type' => 'gitlab-api-config-missing-error',
                    'log_class' => get_class(),
                    'error_code' => '501',
                    'error_message' => $this->error_message,
                    'error_reference' => $instance_key,
                ]);

            return false;
        }

        // Check if the Base URL has been configured in the instance_key array
        // in config/glamstack-gitlab.php and/or the .env file
        if (config('glamstack-gitlab.'.$instance_key.'.base_url') != null) {
            $this->base_url = config('glamstack-gitlab.'.$instance_key.'.base_url') . '/api/v4';
        } else {
            $this->error_message = 'The GitLab base URL for instance key is null. ' .
                'Without this configuration, there is no API base URL to ' .
                'connect with. You can configure the base URL in ' .
                'config/glamstack-gitlab.php or .env file.';

            Log::channel(config('glamstack-gitlab.log_channels'))
                ->critical($this->error_message, [
                    'log_event_type' => 'gitlab-api-config-missing-error',
                    'log_class' => get_class(),
                    'error_code' => '501',
                    'error_message' => $this->error_message,
                    'error_reference' => $instance_key,
                ]);

            return false;
        }

        // Check if the Access Token has been configured in the instance_key
        // array in config/glamstack-gitlab.php and/or the .env file. Another
        // option is for users to provide an access token in __construct().
        if ($this->access_token != null) {
            // No changes since access token has been set in construct method
        } elseif (config('glamstack-gitlab'.$instance_key.'access_token') != null) {
            $this->access_token = config('glamstack-gitlab.'.$instance_key.'.access_token');
        } else {
            $error_message = 'The GitLab access token for instance key is ' .
                'null. Without this configuration, there is no API token to ' .
                'use for authenticated API requests. It is still possible to ' .
                'perform API calls to public endpoints without an access ' .
                'token, however you may see unexpected permission errors.';

            Log::channel(config('glamstack-gitlab.log_channels'))
                ->warning($error_message, [
                    'log_event_type' => 'gitlab-api-config-missing-warning',
                    'log_class' => get_class(),
                    'error_code' => '501',
                    'error_message' =>  $error_message,
                    'error_reference' => $instance_key,
                ]);
        }

        return true;
    }


    /**
     * Test the connection to the GitLab instance and get the version
     *
     * @see https://docs.gitlab.com/ee/api/version.html
     *
     * @return void
     */
    public function testConnection()
    {
        $response = $this->get('/version')->object;

        $this->gitlab_version = $response->version;
    }

    /**
     * GitLab API Get Request
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \Glamstack\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->get('/groups/'.$id);
     * ```
     * @param string $uri The URI with leading slash after `/api/v4`
     *
     * @param array $request_data Optional query data to apply to GET request
     *
     * @return object|string See parseApiResponse() method
     *
     * Example Response for /groups/{id}
     * {
     *  +"headers": {
     *    +"Date": "Wed, 10 Nov 2021 15:05:28 GMT"
     *    +"Content-Type": "application/json"
     *    +"Content-Length": "1107"
     *    +"Connection": "keep-alive"
     *    (truncated)
     *    +"Server": "cloudflare"
     *    +"CF-RAY": ""
     *  }
     *  +"json": (truncated)
     *  +"object": {
     *    +"id": 12345678
     *    +"web_url": <web_url>
     *    +"name": <name>
     *    +"path": <path>
     *    +"description": <description>
     *    +"visibility": "public"
     *    +"share_with_group_lock": false
     *    +"require_two_factor_authentication": false
     *    +"two_factor_grace_period": 48
     *    +"project_creation_level": "maintainer"
     *    +"auto_devops_enabled": null
     *    +"subgroup_creation_level": "maintainer"
     *    +"emails_disabled": false
     *    +"mentions_disabled": false
     *    +"lfs_enabled": true
     *    +"default_branch_protection": 2
     *    +"avatar_url": <avatar_url>
     *    +"request_access_enabled": true
     *    +"full_name": <full_name>
     *    +"full_path": <full_path>
     *    +"created_at": <created_at>
     *    +"parent_id": <parent_id>
     *    +"ldap_cn": null
     *    +"ldap_access": null
     *    +"marked_for_deletion_on": null
     *    +"shared_with_groups": []
     *    +"runners_token": <runners_token>
     *    +"projects": []
     *    +"shared_projects": []
     *    +"shared_runners_minutes_limit": null
     *    +"extra_shared_runners_minutes_limit": null
     *    +"prevent_forking_outside_group": false
     *  }
     *  +"status": {
     *    +"code": 200
     *    +"ok": true
     *    +"successful": true
     *    +"failed": false
     *    +"serverError": false
     *    +"clientError": false
     *  }
     * }
     */
    public function get(string $uri, array $request_data = []): object|string
    {
        try {

            // Utilize HTTP to run a GET request against the base URL with the
            // URI supplied from the parameter appended to the end.
            $response = Http::withToken($this->access_token)
                ->withHeaders($this->request_headers)
                ->get($this->base_url . $uri, $request_data);

            // If the response is a paginated response
            if ($this->checkForPagination($response) == true) {

                // Resupply the url for the request to the getPaginatedResults
                // helper function.
                $paginated_results = $this->getPaginatedResults($this->base_url . $uri, $request_data);

                // The $paginated_results will be returned as an object of objects
                // which needs to be converted to a flat object for standardizing
                // the response returned. This needs to be a separate function
                // instead of casting to an object due to return body complexities
                // with nested array and object mixed notation.
                /** @phpstan-ignore-next-line */
                $response->paginated_results = $this->convertPaginatedResponseToObject($paginated_results);

                // Unset property for body and json
                unset($response->body);
                unset($response->json);
            }
            // Parse API Response and convert to returnable object with expected format
            // The checkForPagination method will return a boolean that is passed.
            /** @phpstan-ignore-next-line */
            $parsed_api_response = $this->parseApiResponse($response, $this->checkForPagination($response));

            $this->logInfo('get', $this->base_url . $uri, $parsed_api_response->status->code);

            return $parsed_api_response;
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            return $this->handleException($exception, get_class(), $uri);
        }
    }

    /**
     * GitLab API POST Request
     * This method is called from other services to perform a POST request and
     * return a structured object.
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \Glamstack\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->post('/projects', [
     *      'name' => 'My Cool Project',
     *      'path' => 'my-cool-project'
     * ]);
     * ```
     *
     * @param string $uri The URI with leading slash after `/api/v4`
     *
     * @param array $request_data Optional Post Body array
     *
     * @return object|string
     */
    public function post(string $uri, array $request_data = []): object|string
    {
        try {
            $request = Http::withToken($this->access_token)
                ->withHeaders($this->request_headers)
                ->post($this->base_url . $uri, $request_data);

            $response = $this->parseApiResponse($request);

            $this->logInfo('post', $this->base_url . $uri, $response->status->code);

            return $response;
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            return $this->handleException($exception, get_class(), $uri);
        }
    }

    /**
     * GitLab API PUT Request
     * This method is called from other services to perform a PUT request and
     * return a structured object.
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \Glamstack\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->put('/projects/12345678', [
     *      'description' => 'This is an updated project description'
     * ]);
     * ```
     *
     * @param string $uri The URI with leading slash after `/api/v4`
     *
     * @param array $request_data Optional request data to send with PUT request
     *
     * @return object|string
     */
    public function put(string $uri, array $request_data = []): object|string
    {
        try {
            $request = Http::withToken($this->access_token)
                ->withHeaders($this->request_headers)
                ->put($this->base_url . $uri, $request_data);

            $response = $this->parseApiResponse($request);

            $this->logInfo('put', $this->base_url . $uri, $response->status->code);

            return $response;
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            return $this->handleException($exception, get_class(), $uri);
        }
    }

    /**
     * GitLab API DELETE Request
     * This method is called from other services to perform a DELETE request and return a structured object.
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \Glamstack\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->delete('/user/'.$id);
     * ```
     *
     * @param string $uri The URI with leading slash after `/api/v4`
     *
     * @param array $request_data Optional request data to send with DELETE request
     *
     * @return object|string
     */
    public function delete(string $uri, array $request_data = []): object|string
    {
        try {
            $request = Http::withToken($this->access_token)
                ->withHeaders($this->request_headers)
                ->delete($this->base_url . $uri, $request_data);

            $response = $this->parseApiResponse($request);

            $this->logInfo('delete', $this->base_url . $uri, $response->status->code);

            return $response;
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            return $this->handleException($exception, get_class(), $uri);
        }
    }

    /**
     * Check if pagination is used in the response, and it contains multiple pages
     *
     * @param Response $response API response from GitLab.
     *
     * @return bool True if the response requires multiple pages | False if response is a single page
     */
    public function checkForPagination(Response $response): bool
    {
        $headers = $this->convertHeadersToObject($response->headers());

        // Check if X-Total-Pages property exist and if it does the page count is greater than 1.
        if (property_exists($headers, 'X-Total-Pages') && $headers->{'X-Total-Pages'} > 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Convert API Response Headers to Object
     * This method is called from the parseApiResponse method to prettify the
     * Guzzle Headers that are an array with nested array for each value, and
     * converts the single array values into strings and converts to an object for
     * easier and consistent accessibility with the parseApiResponse format.
     *
     * @param array $header_response
     * [
     *    "Date" => [
     *      "Tue, 02 Nov 2021 16:00:30 GMT",
     *    ],
     *    "Content-Type" => [
     *      "application/json",
     *    ],
     *    "Transfer-Encoding" => [
     *      "chunked",
     *    ],
     *    "Connection" => [
     *      "keep-alive",
     *    ],
     *    "Cache-Control" => [
     *      "max-age=0, private, must-revalidate",
     *    ],
     *    "Etag" => [
     *      "W/"ef80161dad0045459a87879e4d6b0769"",
     *    ],
     *    ...(truncated)
     * ]
     *
     * @return object
     *  {
     *      +"Date": "Tue, 02 Nov 2021 16:28:37 GMT",
     *      +"Content-Type": "application/json",
     *      +"Transfer-Encoding": "chunked",
     *      +"Connection": "keep-alive",
     *      +"Cache-Control": "max-age=0, private, must-revalidate",
     *      +"Etag": "W/"534830b145cda36bcd6bcd91c3ed3742"",
     *      +"Link": (truncated),
     *      +"Vary": "Origin",
     *      +"X-Content-Type-Options": "nosniff",
     *      +"X-Frame-Options": "SAMEORIGIN",
     *      +"X-Next-Page": "",
     *      +"X-Page": "1",
     *      +"X-Per-Page": "20",
     *      +"X-Prev-Page": "",
     *      +"X-Request-Id": "01FKGQPA4V7TPC70J60J72GJ30",
     *      +"X-Runtime": "0.148641",
     *      +"X-Total": "1",
     *      +"X-Total-Pages": "1",
     *      +"RateLimit-Observed": "2",
     *      +"RateLimit-Remaining": "1998",
     *      +"RateLimit-Reset": "1635870577",
     *      +"RateLimit-ResetTime": "Tue, 02 Nov 2021 16:29:37 GMT",
     *      +"RateLimit-Limit": "2000",
     *      +"GitLab-LB": "fe-14-lb-gprd",
     *      +"GitLab-SV": "localhost",
     *      +"CF-Cache-Status": "DYNAMIC",
     *      +"Expect-CT": "max-age=604800, report-uri="https://report-uri.cloudflare.com/cdn-cgi/beacon/expect-ct"",
     *      +"Strict-Transport-Security": "max-age=31536000",
     *      +"Server": "cloudflare",
     *      +"CF-RAY": "6a7ebcad3ce908db-SEA",
     *  }
     */
    public function convertHeadersToObject(array $header_response): object
    {
        $headers = [];

        foreach ($header_response as $header_key => $header_value) {
            $headers[$header_key] = implode(" ", $header_value);
        }

        return (object) $headers;
    }

    /**
     * Helper method for getting results requiring pagination.
     *
     * @see https://docs.gitlab.com/ee/api/#pagination
     *
     * @param string $endpoint URL endpoint for the GitLab API
     *
     * @param array $request_data Optional request data to send with GET request
     *
     * @return array Array of the response objects for each page combined.
     */
    public function getPaginatedResults(string $endpoint, array $request_data = []): array
    {
        // Create initial page array to load with the request data
        $initial_page = [
            'page' => '1'
        ];

        // Merge the request_data and initial_page variable to allow for getting
        // the first page of data
        $request_body = array_merge($request_data, $initial_page);

        // Get a list of records
        $records = Http::withToken($this->access_token)
            ->withHeaders($this->request_headers)
            ->get($endpoint, $request_body);

        // Get total page count from header array
        $total_pages = $records->headers()['X-Total-Pages'][0];

        // Define empty array to add API results to
        $records_array = [];

        // Loop through pages
        for ($page = 1; $page <= $total_pages; $page++) {

            // Create new array with the current page number. Allowing for
            // looping through the response with the optional query parameters.
            $new_page = [
                'page' => $page
            ];

            // Merge the initial request_data with the new_page array
            $request_body = array_merge($request_data, $new_page);

            // Get list of records for current page
            $records_page = Http::withToken($this->access_token)
                ->withHeaders($this->request_headers)
                ->get($endpoint, $request_body);

            // Add API data to array with final result
            $records_array = array_merge($records_array, (array) $records_page->object());
        }

        return $records_array;
    }

    /**
     * Convert paginated API response array into an object
     *
     * @param array $paginatedResponse Combined object returns from multiple pages of
     * API responses.
     *
     * @return object Object of the API responses combined.
     */
    public function convertPaginatedResponseToObject(array $paginatedResponse): object
    {
        $results = [];

        foreach ($paginatedResponse as $response_key => $response_value) {
            $results[$response_key] = $response_value;
        }
        return (object) $results;
    }

    /**
     * Parse the API response and return custom formatted response for consistency
     *
     * @see https://laravel.com/docs/8.x/http-client#making-requests
     *
     * @param object $response Response object from API results
     *
     * @param false $paginated If the response is paginated or not
     *
     * @return object Custom response returned for consistency
     *  {
     *    +"headers": {
     *      +"Date": "Fri, 12 Nov 2021 20:13:55 GMT"
     *      +"Content-Type": "application/json"
     *      +"Content-Length": "1623"
     *      +"Connection": "keep-alive"
     *    }
     *    +"json": "{"id":12345678,"name":"Dade Murphy","username":"z3r0c00l","state":"active"}"
     *    +"object": {
     *      +"id": 12345678
     *      +"name": "Dade Murphy"
     *      +"username": "z3r0c00l"
     *      +"state": "active"
     *    }
     *    +"status": {
     *      +"code": 200
     *      +"ok": true
     *      +"successful": true
     *      +"failed": false
     *      +"serverError": false
     *      +"clientError": false
     *   }
     * }
     */
    public function parseApiResponse(object $response, bool $paginated = false): object
    {
        return (object) [
            'headers' => $this->convertHeadersToObject($response->headers()),
            'json' => $paginated == true ? json_encode($response->paginated_results) : json_encode($response->json()),
            'object' => $paginated == true ? (object) $response->paginated_results : $response->object(),
            'status' => (object) [
                'code' => $response->status(),
                'ok' => $response->ok(),
                'successful' => $response->successful(),
                'failed' => $response->failed(),
                'serverError' => $response->serverError(),
                'clientError' => $response->clientError(),
            ],
        ];
    }

    /**
     * Create an info log entry for an API call
     *
     * @param \Illuminate\Http\Client\RequestException $exception An instance of the exception
     *
     * @param string $method The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $endpoint The URL of the API call including the concatenated base URL and URI
     *
     * @param string $status_code The HTTP response status code (ex. `200`)
     *
     * @return void
     */
    public function logInfo($method, $endpoint, $status_code) : void
    {
        $info_message = Str::upper($method).' '.$status_code.' '.$endpoint;

        Log::channel(config('glamstack-gitlab.log_channels'))
            ->info($info_message, [
                'log_event_type' => 'gitlab-api-response-info',
                'log_class' => get_class(),
                'info_code' => $status_code,
                'info_method' => Str::upper($method),
                'info_endpoint' => $endpoint,
                'gitlab_version' => $this->gitlab_version,
            ]);
    }

    /**
     * Handle GitLab API Exception
     *
     * @param \Illuminate\Http\Client\RequestException $exception An instance of the exception
     *
     * @param string $log_class get_class()
     *
     * @param string $reference Reference slug or identifier
     *
     * @return string Error message
     */
    public function handleException($exception, $log_class, $reference)
    {
        Log::channel(config('glamstack-gitlab.log_channels'))
            ->error($exception->getMessage(), [
                'log_event_type' => 'gitlab-api-response-error',
                'log_class' => $log_class,
                'error_code' => $exception->getCode(),
                'error_message' => $exception->getMessage(),
                'error_reference' => $reference,
                'gitlab_version' => $this->gitlab_version,
            ]);

        return $exception->getMessage();
    }

    /**
     * Set the request headers for the GitLab API request
     *
     * @return void
     */
    public function setRequestHeaders() : void
    {
        // Get Laravel and PHP Version
        $laravel = 'laravel/'.app()->version();
        $php = 'php/'.phpversion();

        // Decode the composer.lock file
        $composer_lock_json = json_decode(file_get_contents(base_path('composer.lock')), true);

        // Use Laravel collection to search for the package. We will use the
        // array to get the package name (in case it changes with a fork) and
        // return the version key. For production, this will show a release
        // number. In development, this will show the branch name.
        $composer_package = collect($composer_lock_json['packages'])
            ->where('name', 'glamstack/gitlab-sdk')
            ->first();
        $package = $composer_package['name'].'/'.$composer_package['version'];

        // Define request headers
        $this->request_headers = [
            'User-Agent' => $package.' '.$laravel.' '.$php
        ];
    }
}
