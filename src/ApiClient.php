<?php

namespace Glamstack\Gitlab;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ApiClient
{
    private string $base_url;
    private string $access_token;

    public function __construct(string $instance_key = 'gitlab_com')
    {
        // Establish API connection
        $api_connection = $this->getApiConnectionVariables($instance_key);

        if($api_connection == false) {
            abort(501, 'The GitLab instance (' . $instance_key . ') is not defined ' .
                'in config/glamstack-gitlab.php. Without this configuration, ' .
                'there is no API base URL or API token to connect with.');
        }
    }

    /**
     * Set the URL and access token used for GitLab API calls.
     *
     * @param string $instance_key The key of the array in config/glamstack-gitlab.php
     *
     * @return bool
     */
    public function getApiConnectionVariables(string $instance_key): bool
    {
        // Get the instance configuration from config/glamstack-gitlab.php array
        /** @phpstan-ignore-next-line */
        if (!array_key_exists($instance_key, config('glamstack-gitlab'))) {
            Log::stack(config('glamstack-gitlab.log_channels'))->error('The GitLab instance key is ' .
                'not defined in config/glamstack-gitlab.php.',
                [
                    'log_event_type' => 'glamstack-missing-config',
                    'log_class' => get_class(),
                    'error_code' => '501',
                    'error_message' => 'The GitLab instance key is not defined ' .
                        'in config/glamstack-gitlab.php. Without this configuration, ' .
                        'there is no API base URL or API token to connect with.',
                    'error_reference' => $instance_key,
                ]
            );

            return false;
        }

        // Check if the Base URL has been configured in the instance_key array
        // in config/glamstack-gitlab.php and/or the .env file
        if (config('glamstack-gitlab.'.$instance_key.'.base_url') == null) {
            Log::channel(config('glamstack-gitlab.log_channels'))->error('The GitLab base URL for ' .
                'instance key is null. Without this configuration, there is ' .
                'no API base URLto connect with. You can configure the base ' .
                'URL in config/glamstack-gitlab.php or .env file.',
                [
                    'log_event_type' => 'glamstack-missing-config',
                    'log_class' => get_class(),
                    'error_code' => '501',
                    'error_message' => 'The GitLab base URL for instance key '.
                        'is null. Without this configuration, there is no API '.
                        'base URL to connect with. You can configure the base '.
                        'URL in config/glamstack-gitlab.php or .env file.',
                    'error_reference' => $instance_key,
                ]
            );

            return false;
        }

        // Check if the Access Token has been configured in the instance_key
        // array in config/glamstack-gitlab.php and/or the .env file
        if (config('glamstack-gitlab'.$instance_key.'access_token') == null) {
            Log::channel(config('glamstack-gitlab.log_channels'))->warning('The GitLab access token ' .
                'for instance key is null. Without this configuration, there ' .
                'is no API token to use for authenticated API requests. It is '.
                'still possible to perform API calls to public endpoints '.
                'without an access token, however you may see unexpected '.
                'errors based on permissions.',
                [
                    'log_event_type' => 'gitlab-api-response-error',
                    'log_class' => get_class(),
                    'error_code' => '501',
                    'error_message' =>  'The GitLab access token for instance '.
                        'key is null. Without this configuration, there is no ' .
                        'API token to use for authenticated API requests. It '.
                        'is still possible to perform API calls to public '.
                        'endpoints without an access token, however you may '.
                        'see unexpected errors based on permissions.',
                    'error_reference' => $instance_key,
                ]
            );

            return false;
        }

        // Set API Client properties to use in other methods
        $this->base_url = config('glamstack-gitlab.'.$instance_key.'.base_url') . '/api/v4';
        /** @phpstan-ignore-next-line */
        $this->access_token = config('glamstack-gitlab.'.$instance_key.'.access_token');

        return true;
    }

    /**
     * Use GitLab API to get a resource endpoint.
     *
     * @param string $endpoint URI with leading `/` for API resource
     * @param array $request_data Optional query data to apply to GET request
     *
     * @return object api_response object from BaseService class
     */
    public function get($endpoint, $request_data = []): object
    {
        return $this->apiGetRequest($endpoint, $request_data);
    }

    /**
     * Use GitLab API to create a new resource
     *
     * @param string $endpoint URI with leading `/` for API resource
     * @param array $request_data Optional request data to send with POST request
     *
     * @return object api_response object from BaseService class
     */
    public function post($endpoint, $request_data = []): object
    {
        return $this->apiPostRequest($endpoint, $request_data);
    }

    /**
     * Use GitLab API to update an resource with a PUT request.
     *
     * @param string $endpoint URI with leading `/` for API resource
     * @param array $request_data Optional request data to send with PUT request
     *
     * @return object api_response object from BaseService class
     */
    public function put($endpoint, $request_data = []): object
    {
        return $this->apiPutRequest($endpoint, $request_data);
    }

    /**
     * Use GitLab API to delete an resource
     *
     * @param string $endpoint URI with leading `/` for API resource
     *
     * @return object api_response object from BaseService class
     */
    public function delete($endpoint): object
    {
        return $this->apiDeleteRequest($endpoint);
    }
}
