<?php

namespace GitlabIt\Gitlab\Traits;

use Carbon\Carbon;
use GitlabIt\Gitlab\Exceptions\ApiResponseException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ResponseLog
{
    /**
     * Create a log entry for an API call
     *
     * This method is called from other methods and will call specific methods
     * depending on the log severity level.
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function logResponse(string $method, string $url, object $response): void
    {
        if ($response->status->successful == true) {
            $this->logInfo($method, $url, $response);
        } else {
            switch ($response->status->code) {
                case 400:
                    $this->handleBadRequestException($method, $url, $response);
                    break;
                case 401:
                    $this->handleUnauthorizedException($method, $url, $response);
                    break;
                case 403:
                    $this->handleForbiddenException($method, $url, $response);
                    break;
                case 404:
                    $this->handleNotFoundException($method, $url, $response);
                    break;
                case 429:
                    $this->handleRateLimitException($method, $url, $response);
                    break;
                case 500:
                    $this->handleServerErrorException($method, $url, $response);
                    break;
                default:
                    $this->handleUnknownErrorException($method, $url, $response);
                    break;
            }
        }
    }

    /**
     * Create an info log entry for an API call
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function logInfo(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        Log::stack((array) $this->connection_config['log_channels'])
            ->info($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-response-info',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'status_code' => $response->status->code,
            ]);
    }

    /**
     * Handle a 400 Bad Request API Response with an error log and exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function handleBadRequestException(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        // If error exists in response, append to message
        if (property_exists($response->object, 'error')) {
            $message .= ' - ' . $response->object->error;
        }

        Log::stack((array) $this->connection_config['log_channels'])
            ->warning($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-bad-request-error',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'status_code' => $response->status->code
            ]);

        throw new ApiResponseException($message, 400);
    }

    /**
     * Handle a 401 Unauthorized API Response with an error log and exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function handleUnauthorizedException(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        Log::stack((array) $this->connection_config['log_channels'])
            ->critical($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-unauthorized-error',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'status_code' => $response->status->code,
            ]);

        throw new ApiResponseException($message, 401);
    }

    /**
     * Handle a 403 Forbidden API Response with an error log and exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function handleForbiddenException(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        Log::stack((array) $this->connection_config['log_channels'])
            ->critical($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-forbidden-error',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'status_code' => $response->status->code,
            ]);

        throw new ApiResponseException($message, 403);
    }

    /**
     * Handle a 404 Not Found API Response with an error log and exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function handleNotFoundException(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        Log::stack((array) $this->connection_config['log_channels'])
            ->warning($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-not-found-error',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'status_code' => $response->status->code,
            ]);

        throw new ApiResponseException($message, 404);
    }

    /**
     * Handle a 429 Rate Limit API Response with an error log and exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    private function handleRateLimitException(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        // Calculate time remaining
        if (isset($response->headers['RateLimit-Reset'])) {
            $time_remaining = Carbon::parse($response->headers['RateLimit-Reset'])->diffInSeconds();
        } else {
            $time_remaining = null;
        }

        Log::stack((array) $this->connection_config['log_channels'])
            ->error($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-rate-limit-error',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'rate_limit_observed' => $response->headers['RateLimit-Observed'] ?? null,
                'rate_limit_remaining' => $response->headers['RateLimit-Remaining'] ?? null,
                'rate_limit_reset_timestamp' => $response->headers['RateLimit-Reset'] ?? null,
                'rate_limit_reset_datetime' => $response->headers['RateLimit-ResetTime'] ?? null,
                'rate_limit_reset_secs_remaining' => $time_remaining,
                'rate_limit_limit' => $response->headers['RateLimit-Limit'] ?? null,
                'status_code' => $response->status->code,
            ]);

        throw new ApiResponseException($message, 429);
    }

    /**
     * Handle a 5xx API Response with an error log and exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function handleServerErrorException(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        Log::stack((array) $this->connection_config['log_channels'])
            ->critical($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-response-server-error',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'status_code' => $response->status->code,
            ]);

        throw new ApiResponseException($message, $response->object->status);
    }

    /**
     * Handle an unknown API Response with an error log and exception
     *
     * @param string $method
     *      The lowercase name of the method that calls this function (ex. `get`)
     *
     * @param string $url
     *      The URL of the API call including the concatenated base URL and URI
     *
     * @param object $response
     *      The HTTP response formatted with $this->parseApiResponse()
     *
     * @return void
     */
    public function handleUnknownErrorException(string $method, string $url, object $response): void
    {
        $message = Str::upper($method) . ' ' . $response->status->code . ' ' . $url;

        Log::stack((array) $this->connection_config['log_channels'])
            ->critical($message, [
                'api_endpoint' => $url,
                'api_method' => Str::upper($method),
                'class' => get_class(),
                'connection_key' => $this->connection_key,
                'event_type' => 'gitlab-api-response-unknown-error',
                'gitlab_version' => $this->gitlab_version,
                'message' => $message,
                'status_code' => $response->status->code,
            ]);

        throw new ApiResponseException($message, $response->object->status);
    }
}
