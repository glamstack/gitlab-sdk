<?php

namespace GitlabIt\Gitlab;

use Carbon\Carbon;
use GitlabIt\Gitlab\Exceptions\BadRequestException;
use GitlabIt\Gitlab\Exceptions\ConfigurationException;
use GitlabIt\Gitlab\Exceptions\ForbiddenException;
use GitlabIt\Gitlab\Exceptions\NotFoundException;
use GitlabIt\Gitlab\Exceptions\PreconditionFailedException;
use GitlabIt\Gitlab\Exceptions\RateLimitException;
use GitlabIt\Gitlab\Exceptions\ServerErrorException;
use GitlabIt\Gitlab\Exceptions\UnauthorizedException;
use GitlabIt\Gitlab\Exceptions\UnprocessableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiClient
{
    public const API_VERSION = 4;
    public const PER_PAGE = 100;
    public const REQUIRED_CONFIG_PARAMETERS = ['base_url', 'access_token', 'log_channels'];

    private string $access_token;
    private string $base_url;
    private array $connection_config;
    private string $connection_key;
    private ?string $gitlab_version = null;
    private array $request_headers;

    /**
     * Standard initialization construct method.
     *
     * @param string|null $connection_key
     *      The connection key to use for initialization
     *
     * @param array $connection_config
     *      Customizable connection configuration array
     *
     * @throws ConfigurationException
     *      Thrown if there is a problem with the initialization configuration
     */
    public function __construct(
        string $connection_key = null,
        array $connection_config = []
    ) {
        if (empty($connection_config)) {
            $this->setConnectionKeyConfiguration($connection_key);
        } else {
            $this->setCustomConfiguration($connection_config);
        }

        // Set the class access_token variable
        $this->setAccessToken();

        // Set the class base_url variable
        $this->setBaseUrl();

        // Set request headers
        $this->setRequestHeaders();

        // Test API Connection
        $this->testConnection();
    }

    /**
     * Set the configuration utilizing the `connection_key`
     *
     * This method will utilize the `connection_key` provided in the construct method. The `connection_key` will
     * correspond to a `connection` in the configuration file.
     *
     * @param ?string $connection_key
     *      The connection key to use for configuration.
     */
    protected function setConnectionKeyConfiguration(?string $connection_key): void
    {
        $this->setConnectionKey($connection_key);
        $this->setConnectionConfig();
    }

    /**
     * Set the configuration utilizing the `connection_config`
     *
     * This method will utilize the `connection_config` array provided in the construct method. The `connection_config`
     * array keys will have to match the `REQUIRED_CONFIG_PARAMETERS` array. The connection key will be set to custom
     * and ignored for the remainder of the SDK usage.
     *
     * @param array $connection_config
     *      Array that contains the required parameters for the connection configuration
     */
    protected function setCustomConfiguration(array $connection_config): void
    {
        $this->validateConnectionConfigArray($connection_config);
        $this->setConnectionKey('custom');
        $this->setConnectionConfig($connection_config);
    }

    /**
     * Validate that array keys in `REQUIRED_CONFIG_PARAMETERS` exists in the `connection_config`
     *
     * Loop through each of the required parameters in `REQUIRED_CONFIG_PARAMETERS` and verify that each of them are
     * contained in the provided `connection_config` array. If there is a key missing an error will be logged.
     *
     * @param array $connection_config
     *      The connection configuration array provided to the `construct` method.
     */
    protected function validateConnectionConfigArray(array $connection_config): void
    {
        foreach (self::REQUIRED_CONFIG_PARAMETERS as $parameter) {
            if (!array_key_exists($parameter, $connection_config)) {
                $error_message = 'The GitLab ' . $parameter . ' is not defined in the ApiClient construct ' .
                    'connection_config array provided. This is a required parameter to be passed in not using the ' .
                    'configuration file and connection_key initialization method.';
            } else {
                $error_message = 'The GitLab SDK connection_config array provided in the ApiClient construct ' .
                    'connection_config array size should be ' . count(self::REQUIRED_CONFIG_PARAMETERS) . 'but ' .
                    count($connection_config) . ' array keys were provided.';
            }

            Log::stack((array) config('gitlab-sdk.auth.log_channels'))->critical(
                $error_message,
                [
                    'event_type' => 'gitlab-api-config-missing-error',
                    'class' => get_class(),
                    'status_code' => '501',
                    'connection_url' => $connection_config['base_url'],
                ]
            );

            throw new ConfigurationException($error_message, 501);
        }
    }

    /**
     * Set the connection_key class property variable
     *
     * @param ?string $connection_key (Optional)
     *      The connection key to use from config/gitlab-sdk.php. If not set, it will use the default connection set in
     *      the GITLAB_DEFAULT_CONNECTION `.env` variable or config/gitlab-sdk.php if not set.
     */
    protected function setConnectionKey(string $connection_key = null): void
    {
        if ($connection_key == null) {
            $this->connection_key = config('gitlab-sdk.auth.default_connection');
        } else {
            $this->connection_key = $connection_key;
        }
    }

    /**
     * Set the connection_config class property array
     *
     * Define an array in the class using the connection configuration in the gitlab-sdk.php connections array. If
     * connection key is not specified, an error log will be created and a 501 exception error will be thrown.
     *
     * @param array $custom_configuration
     *      Custom configuration array for SDK initialization
     */
    protected function setConnectionConfig(array $custom_configuration = []): void
    {
        if (array_key_exists($this->connection_key, config('gitlab-sdk.connections')) && empty($custom_configuration)) {
            $this->connection_config = config('gitlab-sdk.connections.' . $this->connection_key);
        } elseif ($custom_configuration) {
            $this->connection_config = $custom_configuration;
        } else {
            $error_message = 'The `' . $this->connection_key . '` connection key is not defined in ' .
                '`config/gitlab-sdk.php` connections array.';

            Log::stack((array) config('gitlab-sdk.auth.log_channels'))->critical(
                $error_message,
                [
                    'event_type' => 'gitlab-api-config-missing-key-error',
                    'class' => get_class(),
                    'status_code' => '501',
                    'connection_key' => $this->connection_key,
                ]
            );

            throw new ConfigurationException($error_message, 501);
        }
    }

    /**
     * Set the base_url class property variable
     *
     * The base_url variable is defined in `.env` variable `GITLAB_{CONNECTION_KEY}_BASE_URL` or config/gitlab-sdk.php
     */
    protected function setBaseUrl(): void
    {
        if ($this->connection_config['base_url'] != null) {
            $this->base_url = $this->connection_config['base_url'] . '/api/v' . self::API_VERSION;
        } else {
            $error_message = 'You need to add the `GITLAB_' . Str::upper($this->connection_key) . '_BASE_URL` ' .
                'variable in your `.env` file (ex. `https://gitlab.com` or `https://gitlab.example.com`).';

            Log::stack((array) config('gitlab-sdk.auth.log_channels'))->critical(
                $error_message,
                [
                    'event_type' => 'gitlab-api-config-missing-url-error',
                    'class' => get_class(),
                    'status_code' => '501',
                    'connection_key' => $this->connection_key,
                ]
            );

            throw new ConfigurationException($error_message, 501);
        }
    }

    /**
     * Set the access_token class property variable
     *
     * The access_token variable is defined in `.env` variable `GITLAB_{CONNECTION_KEY}_ACCESS_TOKEN` and is associated
     * with a connection config defined in config/gitlab-sdk.php.
     */
    protected function setAccessToken(): void
    {
        if ($this->connection_config['access_token'] != null) {
            $this->access_token = $this->connection_config['access_token'];
        } else {
            $error_message = 'You need to add the `GITLAB_' . Str::upper($this->connection_key) . '_ACCESS_TOKEN` ' .
                'variable in your `.env` file so you can perform authenticated API calls.';

            Log::stack((array) config('gitlab-sdk.auth.log_channels'))->critical(
                $error_message,
                [
                    'event_type' => 'gitlab-api-config-missing-token-error',
                    'class' => get_class(),
                    'status_code' => '501',
                    'connection_key' => $this->connection_key,
                ]
            );

            throw new ConfigurationException($error_message, 501);
        }
    }

    /**
     * Set the request headers for the GitLab API request
     */
    protected function setRequestHeaders(): void
    {
        // Get Laravel and PHP Version
        $laravel = 'Laravel/' . app()->version();
        $php = 'PHP/' . phpversion();

        // Decode the composer.lock file
        $composer_lock_json = json_decode((string) file_get_contents(base_path('composer.lock')), true);

        // Use Laravel collection to search for the package. We will use the array to get the package name (in case it
        // changes with a fork) and return the version key. For production, this will show a release number. In
        // development, this will show the branch name.
        $composer_package = collect($composer_lock_json['packages'])->where('name', 'gitlab-it/gitlab-sdk')->first();

        // Reformat `gitlab-it/gitlab-sdk` as `GitLabIT-Gitlab-Sdk`
        $composer_package_formatted = Str::title(Str::replace('/', '-', $composer_package['name']));
        $package = $composer_package_formatted . '/' . $composer_package['version'];

        // Define request headers
        $this->request_headers = [
            'User-Agent' => $package . ' ' . $laravel . ' ' . $php
        ];
    }

    /**
     * Test the connection to the GitLab API
     *
     * @see https://docs.gitlab.com/ee/api/version.html
     */
    public function testConnection(): void
    {
        // API call to get version from GitLab instance (a simple API endpoint). Logging for is handled by get() method.
        $response = $this->get('/version');

        switch ($response->status->code) {
            case 200:
                $this->gitlab_version = $response->object->version;
                break;
            case 401:
                $error_message = 'The `GITLAB_' . Str::upper($this->connection_key) . '_ACCESS_TOKEN` has been ' .
                    'configured but is invalid (does not exist or has expired). Please generate a new Access Token ' .
                    'and update the variable in your `.env` file.';

                Log::stack((array) config('gitlab-sdk.auth.log_channels'))->critical(
                    $error_message,
                    [
                        'event_type' => 'gitlab-api-config-invalid-error',
                        'class' => get_class(),
                        'status_code' => '401',
                        'connection_key' => $this->connection_key,
                    ]
                );

                throw new ConfigurationException(
                    $error_message,
                    $response->status->code
                );
            default:
                throw new ConfigurationException(
                    'The GitLab API connection test failed for an unknown reason. See logs for details.',
                    $response->status->code
                );
        }
    }

    /**
     * GitLab API Get Request
     *
     * This method is called from other services to perform a POST request and return a structured object.
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \GitlabIt\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->get('/groups/' . $id);
     * ```
     * @param string $uri
     *      The URI with leading slash after `/api/v4`
     *
     * @param array $request_data
     *      Optional query data to apply to GET request
     *
     * @return object
     *      See parseApiResponse() method. The content and schema of the object and json arrays can be found in the REST
     *      API documentation for the specific endpoint.
     */
    public function get(string $uri, array $request_data = []): object
    {
        $request = Http::withToken($this->access_token)
            ->withHeaders($this->request_headers)
            ->get($this->base_url . $uri, $request_data);

        $response = $this->parseApiResponse($request);

        $query_string = !empty($request_data) ? '?' . http_build_query($request_data) : '';
        $this->logResponse('get', $this->base_url . $uri . $query_string, $response);
        $this->throwExceptionIfEnabled('get', $this->base_url . $uri . $query_string, $response);

        if ($this->checkForPagination($response->headers) == true) {
            $request->paginated_results = $this->getPaginatedResults($this->base_url . $uri, $request_data);

            // Override single page response with paginated results
            $response = $this->parseApiResponse($request);
        }

        return $response;
    }

    /**
     * GitLab API POST Request
     *
     * This method is called from other services to perform a POST request and return a structured object.
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \GitlabIt\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->post('/projects', [
     *      'name' => 'My Cool Project',
     *      'path' => 'my-cool-project'
     * ]);
     * ```
     *
     * @param string $uri The URI with leading slash after `/api/v4`
     *
     * @param array $request_data Optional Post Body array
     */
    public function post(string $uri, array $request_data = []): object
    {
        $request = Http::withToken($this->access_token)
            ->withHeaders($this->request_headers)
            ->post($this->base_url . $uri, $request_data);

        $response = $this->parseApiResponse($request);

        $this->logResponse('post', $this->base_url . $uri, $response);
        $this->throwExceptionIfEnabled('post', $this->base_url . $uri, $response);

        return $response;
    }

    /**
     * GitLab API PUT Request
     *
     * This method is called from other services to perform a PUT request and
     * return a structured object.
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \GitlabIt\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->put('/projects/12345678', [
     *      'description' => 'This is an updated project description'
     * ]);
     * ```
     *
     * @param string $uri The URI with leading slash after `/api/v4`
     *
     * @param array $request_data Optional request data to send with PUT request
     */
    public function put(string $uri, array $request_data = []): object
    {
        $request = Http::withToken($this->access_token)
            ->withHeaders($this->request_headers)
            ->put($this->base_url . $uri, $request_data);

        $response = $this->parseApiResponse($request);

        $this->logResponse('put', $this->base_url . $uri, $response);
        $this->throwExceptionIfEnabled('put', $this->base_url . $uri, $response);

        return $response;
    }

    /**
     * GitLab API DELETE Request
     *
     * This method is called from other services to perform a DELETE request and return a structured object.
     *
     * Example Usage:
     * ```php
     * $gitlab_api = new \GitlabIt\Gitlab\ApiClient('gitlab_com');
     * return $gitlab_api->delete('/user/'.$id);
     * ```
     *
     * @param string $uri The URI with leading slash after `/api/v4`
     *
     * @param array $request_data Optional request data to send with DELETE request
     */
    public function delete(string $uri, array $request_data = []): object
    {
        $request = Http::withToken($this->access_token)
            ->withHeaders($this->request_headers)
            ->delete($this->base_url . $uri, $request_data);

        $response = $this->parseApiResponse($request);

        $this->logResponse('delete', $this->base_url . $uri, $response);
        $this->throwExceptionIfEnabled('delete', $this->base_url . $uri, $response);

        return $response;
    }

    /**
     * Convert API Response Headers to Array
     *
     * This method is called from the parseApiResponse method to prettify the Guzzle Headers that are an array with
     * nested array for each value, and converts the single array values into strings and converts to an object for
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
     * @return array
     *  {
     *      "Date" => "Tue, 02 Nov 2021 16:28:37 GMT",
     *      "Content-Type" => "application/json",
     *      "Transfer-Encoding" => "chunked",
     *      "Connection" => "keep-alive",
     *      "Cache-Control" => "max-age=0, private, must-revalidate",
     *      "Etag" => "W/"534830b145cda36bcd6bcd91c3ed3742"",
     *      "Link": (truncated),
     *      "Vary" => "Origin",
     *      "X-Content-Type-Options" => "nosniff",
     *      "X-Frame-Options" => "SAMEORIGIN",
     *      "X-Next-Page" => "",
     *      "X-Page" => "1",
     *      "X-Per-Page" => "20",
     *      "X-Prev-Page" => "",
     *      "X-Request-Id" => "01FKGQPA4V7TPC70J60J72GJ30",
     *      "X-Runtime" => "0.148641",
     *      "X-Total" => "1",
     *      "X-Total-Pages" => "1",
     *      "RateLimit-Observed" => "2",
     *      "RateLimit-Remaining" => "1998",
     *      "RateLimit-Reset" => "1635870577",
     *      "RateLimit-ResetTime" => "Tue, 02 Nov 2021 16:29:37 GMT",
     *      "RateLimit-Limit" => "2000",
     *      "GitLab-LB" => "fe-14-lb-gprd",
     *      "GitLab-SV" => "localhost",
     *      "CF-Cache-Status" => "DYNAMIC",
     *      "Expect-CT" => "max-age=604800, report-uri="https://report-uri.cloudflare.com/cdn-cgi/beacon/expect-ct"",
     *      "Strict-Transport-Security" => "max-age=31536000",
     *      "Server" => "cloudflare",
     *      "CF-RAY" => "6a7ebcad3ce908db-SEA",
     *  }
     */
    protected function convertHeadersToArray(array $header_response): array
    {
        $headers = [];

        foreach ($header_response as $header_key => $header_value) {
            // If array has multiple keys, leave as array
            if (count($header_value) > 1) {
                $headers[$header_key] = $header_value;
            } else {
                $headers[$header_key] = $header_value[0];
            }
        }

        return $headers;
    }

    /**
     * Check if the responses uses pagination and contains multiple pages
     *
     * @see https://docs.gitlab.com/ee/api/rest/index.html#other-pagination-headers
     *
     * @param array $headers
     *      API response headers from API request or parsed response.
     *
     * @return bool
     *      True if the response requires multiple pages
     *      False if response is a single page
     */
    protected function checkForPagination(array $headers): bool
    {
        return (array_key_exists('X-Next-Page', $headers));
    }

    /**
     * Parse the header array for the paginated URL that contains `next`.
     *
     * @see https://docs.gitlab.com/ee/api/rest/index.html#pagination-link-header
     *
     * @param array $headers
     *      API response headers from GitLab request or parsed response.
     *
     * @return ?string
     *      https://gitlab.example.com/api/v4/projects/8/issues/8/notes?page=3&per_page=3
     */
    protected function generateNextPaginatedResultUrl(array $headers): ?string
    {
        if (array_key_exists('Link', $headers)) {
            $links = explode(', ', $headers['Link']);
            foreach ($links as $link_key => $link_url) {
                if (Str::contains($link_url, 'next')) {
                    // Remove the '<' and '>; rel="next"' that is around the next api_url
                    // Before: <https://gitlab.com/api/v4/projects/8/issues/8/notes?page=3&per_page=3>; rel="next"
                    // After: https://gitlab.com/api/v4/projects/8/issues/8/notes?page=3&per_page=3
                    $url = Str::remove('<', $links[$link_key]);
                    $url = Str::remove('>; rel="next"', $url);
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * Helper function used to get GitLab API results that require pagination.
     *
     * @see https://docs.gitlab.com/ee/api/rest/index.html#pagination
     *
     * Example Usage:
     * ```php
     * $this->getPaginatedResults('/users');
     * ```
     *
     * @param string $paginated_url
     *      The concatenated Base URL and Endpoint. This variable will be overriden in the do/while loop.
     *
     * @param array $request_data
     *      Optional query data to apply to GET request
     *
     * @return object
     *      An array of the response objects for each page combined casted as an object.
     */
    protected function getPaginatedResults(string $paginated_url, array $request_data = []): object
    {
        // Define empty array for adding API results to
        $records = [];

        // Set per_page from default 20 to max 100 defined in class constant.
        // This can be overriden by passing it into the request_data array for a specific GET API call.
        if (!array_key_exists('per_page', $request_data)) {
            $request_data['per_page'] = self::PER_PAGE;
        }

        do {
            // Get the results from the API. This ensures that the request data doesn't overwrite the pagination cursor.
            if ($request_data != []) {
                $request = Http::withToken($this->access_token)
                    ->withHeaders($this->request_headers)
                    ->get($paginated_url, $request_data);
            } else {
                $request = Http::withToken($this->access_token)
                    ->withHeaders($this->request_headers)
                    ->get($paginated_url);
            }

            $response = $this->parseApiResponse($request);

            $this->logResponse('get', $paginated_url, $response);
            $this->throwExceptionIfEnabled('get', $paginated_url, $response);

            // Loop through each object from the response and add it to the $records array
            foreach ($response->object as $api_record) {
                $records[] = $api_record;
            }

            // Get next page of results by parsing link and updating URL
            if ($this->checkForPagination($response->headers)) {
                $paginated_url = $this->generateNextPaginatedResultUrl($response->headers);
            } else {
                $paginated_url = null;
            }

            // Set request data to null after first iteration since it is now embedded in the response header URL
            $request_data = [];
        } while ($paginated_url != null);

        return (object) $records;
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
    protected function parseApiResponse(object $response): object
    {
        if (property_exists($response, 'paginated_results')) {
            $json_output = json_encode($response->paginated_results);
            $object_output = (object) $response->paginated_results;
        } else {
            $json_output = json_encode($response->json());
            $object_output = $response->object();
        }

        return (object) [
            'headers' => $this->convertHeadersToArray($response->headers()),
            'json' => $json_output,
            'object' => $object_output,
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
     * Create a log entry for an API call
     *
     * This method is called from other methods and create log entry and throw exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     */
    protected function logResponse(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        $log_context = [
            'api_endpoint' => $url,
            'api_method' => Str::upper($method),
            'class' => get_class(),
            'connection_key' => $this->connection_key,
            'event_type' => null,
            'gitlab_version' => $this->gitlab_version,
            'status_code' => $response->status->code,
        ];

        $log_context['event_type'] = match ($response->status->code) {
            200 => 'gitlab-api-response-info',
            201 => 'gitlab-api-response-created',
            202 => 'gitlab-api-response-accepted',
            204 => 'gitlab-api-response-deleted',
            400 => 'gitlab-api-response-error-bad-request',
            401 => 'gitlab-api-response-error-unauthorized',
            403 => 'gitlab-api-response-error-forbidden',
            404 => 'gitlab-api-response-error-not-found',
            405 => 'gitlab-api-response-error-method-not-allowed',
            412 => 'gitlab-api-response-error-precondition-failed',
            422 => 'gitlab-api-response-error-unprocessable',
            429 => 'gitlab-api-response-error-rate-limit',
            500 => 'gitlab-api-response-error-server'
        };

        // dd($response->object);
        if (is_object($response->object) && property_exists($response->object, 'error')) {
            $log_context['reason'] = $response->object->error;
        } elseif (!$response->status->successful && isset($response->object->message)) {
            $log_context['reason'] = $response->json;
        } elseif (!$response->status->successful) {
            $log_context['reason'] = null;
        }

        switch ($response->status->code) {
            case 200:
                Log::stack((array) $this->connection_config['log_channels'])->info($message, $log_context);
                break;
            case 201:
                Log::stack((array) $this->connection_config['log_channels'])->info($message, $log_context);
                break;
            case 202:
                Log::stack((array) $this->connection_config['log_channels'])->info($message, $log_context);
                break;
            case 204:
                Log::stack((array) $this->connection_config['log_channels'])->info($message, $log_context);
                break;
            case 400:
                Log::stack((array) $this->connection_config['log_channels'])->warning($message, $log_context);
                break;
            case 401:
                $message = 'The `GITLAB_' . Str::upper($this->connection_key) . '_ACCESS_TOKEN` has been ' .
                    'configured but is invalid (does not exist or has expired). Please generate a new Access Token ' .
                    'and update the variable in your `.env` file.';
                Log::stack((array) $this->connection_config['log_channels'])->error($message, $log_context);
                break;
            case 403:
                Log::stack((array) $this->connection_config['log_channels'])->error($message, $log_context);
                break;
            case 404:
                Log::stack((array) $this->connection_config['log_channels'])->warning($message, $log_context);
                break;
            case 412:
                Log::stack((array) $this->connection_config['log_channels'])->error($message, $log_context);
                break;
            case 422:
                Log::stack((array) $this->connection_config['log_channels'])->error($message, $log_context);
                break;
            case 429:
                $log_context['rate_limit_limit'] = $response->headers['RateLimit-Limit'] ?? null;
                $log_context['rate_limit_observed'] = $response->headers['RateLimit-Observed'] ?? null;
                $log_context['rate_limit_remaining'] = $response->headers['RateLimit-Remaining'] ?? null;
                $log_context['rate_limit_reset_timestamp'] = $response->headers['RateLimit-Reset'] ?? null;
                $log_context['rate_limit_reset_datetime'] = $response->headers['RateLimit-ResetTime'] ?? null;

                if (isset($response->headers['RateLimit-Reset'])) {
                    $time_remaining = Carbon::parse($response->headers['RateLimit-Reset'])->diffInSeconds();
                    $log_context['rate_limit_reset_secs_remaining'] = $time_remaining;
                    $message = 'Rate Limit Exceeded. Please try again in ' . $time_remaining . ' seconds';
                } else {
                    $log_context['rate_limit_reset_secs_remaining'] = null;
                }

                Log::stack((array) $this->connection_config['log_channels'])->warning($message, $log_context);
                break;
            case 500:
                Log::stack((array) $this->connection_config['log_channels'])->error($message, $log_context);
                break;
            default:
                Log::stack((array) $this->connection_config['log_channels'])->error($message, $log_context);
                break;
        }
    }

    /**
     * Throw an exception for a 4xx or 5xx response for an API call
     *
     * This method checks whether the .env variable or config value for `GITLAB_{CONNECTION_KEY}_EXCEPTIONS=true`
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     */
    protected function throwExceptionIfEnabled(string $method, string $url, object $response): void
    {
        if (config('gitlab-sdk.connections.' . $this->connection_key . '.exceptions') == true) {
            $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

            // If API error includes a message, append friendly message to existing request string
            if (property_exists($response->object, 'message')) {
                $message .= ' - ' . $response->object->message;
            }

            switch ($response->status->code) {
                case 400:
                    throw new BadRequestException($response->json);
                case 401:
                    $message = 'The `GITLAB_' . Str::upper($this->connection_key) . '_ACCESS_TOKEN` has been ' .
                        'configured but is invalid (does not exist or has expired). Please generate a new Access ' .
                        'Token and update the variable in your `.env` file.';
                    throw new UnauthorizedException($message);
                case 403:
                    throw new ForbiddenException($message);
                case 404:
                    throw new NotFoundException($message);
                case 412:
                    throw new PreconditionFailedException($message);
                case 422:
                    throw new UnprocessableException($message);
                case 429:
                    throw new RateLimitException($message);
                case 500:
                    throw new ServerErrorException($response->json);
            }
        }
    }
}
