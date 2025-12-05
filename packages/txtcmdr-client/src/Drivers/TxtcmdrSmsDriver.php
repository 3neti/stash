<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Drivers;

use Illuminate\Support\Facades\Log;
use LBHurtado\DeadDrop\TxtcmdrClient\Client\TxtcmdrClient;
use LBHurtado\DeadDrop\TxtcmdrClient\Contracts\SmsDriverInterface;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsRequest;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsResponse;

/**
 * txtcmdr SMS driver implementation
 */
class TxtcmdrSmsDriver implements SmsDriverInterface
{
    /**
     * @param  string  $apiUrl  txtcmdr API URL
     * @param  string|null  $apiToken  Sanctum API token
     * @param  string|null  $defaultSenderId  Default sender ID
     * @param  int  $timeout  Request timeout in seconds
     * @param  bool  $verifySSL  Whether to verify SSL certificates
     */
    public function __construct(
        protected string $apiUrl,
        protected ?string $apiToken = null,
        protected ?string $defaultSenderId = null,
        protected int $timeout = 30,
        protected bool $verifySSL = true,
    ) {
    }

    /**
     * Send SMS to one or more recipients
     */
    public function send(array $recipients, string $message, ?string $senderId = null): SendSmsResponse
    {
        Log::info('[TxtcmdrSmsDriver] Preparing to send SMS', [
            'api_url' => $this->apiUrl,
            'has_api_token' => ! empty($this->apiToken),
            'recipients' => $recipients,
            'message_preview' => substr($message, 0, 50),
            'sender_id' => $senderId ?? $this->defaultSenderId,
            'timeout' => $this->timeout,
            'verify_ssl' => $this->verifySSL,
        ]);

        $client = new TxtcmdrClient(
            baseUrl: $this->apiUrl,
            apiToken: $this->apiToken,
            timeout: $this->timeout,
            verifySSL: $this->verifySSL
        );

        $request = new SendSmsRequest(
            recipients: $recipients,
            message: $message,
            senderId: $senderId ?? $this->defaultSenderId
        );

        Log::info('[TxtcmdrSmsDriver] Calling txtcmdr API');
        
        try {
            $response = $client->send($request);
            
            Log::info('[TxtcmdrSmsDriver] SMS sent successfully', [
                'success' => $response->success,
                'jobs_dispatched' => $response->jobsDispatched,
                'scheduled_message_id' => $response->scheduledMessageId,
                'message' => $response->message,
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('[TxtcmdrSmsDriver] Failed to send SMS', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            throw $e;
        }
    }
}
