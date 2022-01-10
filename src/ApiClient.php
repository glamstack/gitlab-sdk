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
        //Perform API call
        try {

            // Utilize HTTP to run a GET request against the base URL with the
            // URI supplied from the parameter appended to the end.
            $response = Http::withToken($this->access_token)
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

            return $parsed_api_response;
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            return $this->handleException($exception, get_class(), $uri);
        }
    }
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
