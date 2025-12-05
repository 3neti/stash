<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\ScheduleSmsRequest;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsRequest;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsResponse;
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\ApiRequestException;
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\AuthenticationException;
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\ConfigurationException;

/**
 * txtcmdr API client implementation
 */
class TxtcmdrClient implements TxtcmdrClientInterface
{
    protected Client $httpClient;

    /**
     * @param  string  $baseUrl  Base URL of txtcmdr API (e.g., https://txtcmdr.example.com)
     * @param  string  $apiToken  Sanctum API token
     * @param  int  $timeout  Request timeout in seconds
     * @param  bool  $verifySSL  Whether to verify SSL certificates
     */
    public function __construct(
        protected string $baseUrl,
        protected string $apiToken,
        protected int $timeout = 30,
        protected bool $verifySSL = true,
    ) {
        $this->validateConfig();
        $this->httpClient = $this->createHttpClient();
    }

    /**
     * Send SMS immediately
     */
    public function send(SendSmsRequest $request): SendSmsResponse
    {
        return $this->makeRequest('POST', '/api/send', $request->toArray());
    }

    /**
     * Schedule SMS for later
     */
    public function schedule(ScheduleSmsRequest $request): SendSmsResponse
    {
        return $this->makeRequest('POST', '/api/send/schedule', $request->toArray());
    }

    /**
     * Make HTTP request to txtcmdr API with retry logic
     *
     * @param  array<string, mixed>  $data
     */
    protected function makeRequest(string $method, string $endpoint, array $data): SendSmsResponse
    {
        $attempts = 0;
        $maxNetworkRetries = config('txtcmdr-client.retry.network_errors.attempts', 3);
        $maxServerRetries = config('txtcmdr-client.retry.server_errors.attempts', 2);

        while (true) {
            try {
                $response = $this->httpClient->request($method, $endpoint, [
                    'json' => $data,
                    'headers' => [
                        'Authorization' => "Bearer {$this->apiToken}",
                        'Accept' => 'application/json',
                    ],
                ]);

                $body = json_decode((string) $response->getBody(), true);

                if (! is_array($body)) {
                    throw ApiRequestException::invalidResponse('Response is not valid JSON');
                }

                return SendSmsResponse::fromArray($body);

            } catch (RequestException $e) {
                $statusCode = $e->getResponse()?->getStatusCode();

                // Authentication errors - don't retry
                if ($statusCode === 401) {
                    throw AuthenticationException::invalidToken();
                }

                // Validation errors (422) - don't retry
                if ($statusCode === 422) {
                    $body = (string) $e->getResponse()?->getBody();
                    throw ApiRequestException::invalidResponse($body);
                }

                // Server errors (5xx) - retry limited times
                if ($statusCode >= 500 && $attempts < $maxServerRetries) {
                    $attempts++;
                    $backoff = config('txtcmdr-client.retry.server_errors.backoff_ms', 2000);
                    usleep($backoff * 1000 * $attempts); // Exponential backoff

                    Log::warning('txtcmdr server error, retrying', [
                        'attempt' => $attempts,
                        'status' => $statusCode,
                    ]);

                    continue;
                }

                // Client errors (4xx) or exhausted retries
                $body = (string) $e->getResponse()?->getBody();
                throw ApiRequestException::serverError($statusCode ?? 0, $body);

            } catch (ConnectException $e) {
                // Network errors - retry with exponential backoff
                if ($attempts < $maxNetworkRetries) {
                    $attempts++;
                    $backoff = config('txtcmdr-client.retry.network_errors.backoff_ms', 1000);
                    usleep($backoff * 1000 * $attempts); // Exponential backoff

                    Log::warning('txtcmdr network error, retrying', [
                        'attempt' => $attempts,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                throw ApiRequestException::networkError($e->getMessage(), $e);

            } catch (GuzzleException $e) {
                throw ApiRequestException::networkError($e->getMessage(), $e);
            }
        }
    }

    /**
     * Validate configuration
     */
    protected function validateConfig(): void
    {
        if (empty($this->baseUrl)) {
            throw ConfigurationException::missingConfig('base_url');
        }

        if (! filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw ConfigurationException::invalidUrl($this->baseUrl);
        }

        if (empty($this->apiToken)) {
            throw AuthenticationException::missingToken();
        }
    }

    /**
     * Create Guzzle HTTP client
     */
    protected function createHttpClient(): Client
    {
        return new Client([
            'base_uri' => rtrim($this->baseUrl, '/'),
            'timeout' => $this->timeout,
            'verify' => $this->verifySSL,
            'http_errors' => true,
        ]);
    }
}
