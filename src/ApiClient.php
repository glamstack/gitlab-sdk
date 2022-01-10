<?php

namespace Glamstack\Gitlab;

class ApiClient extends Connection
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

{
    public function __construct($instance_key = 'gitlab_com')
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
