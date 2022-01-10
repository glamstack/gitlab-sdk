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
    {
        // Call BaseService methods to establish API connection
        $this->getApiConnectionVariables($instance_key);
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
