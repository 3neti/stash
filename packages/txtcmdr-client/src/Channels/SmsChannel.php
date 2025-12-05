<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use LBHurtado\DeadDrop\TxtcmdrClient\Contracts\SmsDriverInterface;
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\ConfigurationException;
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\TxtcmdrException;

/**
 * SMS notification channel with pluggable drivers
 *
 * Similar to Laravel's mail system, supports multiple SMS providers:
 * - txtcmdr (default)
 * - twilio (future)
 * - etc.
 */
class SmsChannel
{
    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        Log::info('[SmsChannel] Starting SMS send', [
            'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
            'notification' => get_class($notification),
        ]);

        // Check if notification supports SMS
        if (! method_exists($notification, 'toSms')) {
            Log::warning('[SmsChannel] Notification does not have toSms() method', [
                'notification' => get_class($notification),
            ]);
            return;
        }

        // Get SMS message data
        $message = $notification->toSms($notifiable);
        Log::info('[SmsChannel] Message generated', [
            'message_type' => gettype($message),
            'message_length' => is_string($message) ? strlen($message) : null,
            'message_preview' => is_string($message) ? substr($message, 0, 50) : null,
        ]);

        if (empty($message)) {
            Log::warning('[SmsChannel] Empty message, skipping');
            return;
        }

        // Get recipient mobile number
        $mobile = $this->getRecipientMobile($notifiable);
        Log::info('[SmsChannel] Recipient mobile resolved', [
            'mobile' => $mobile,
            'mobile_type' => gettype($mobile),
        ]);

        if (empty($mobile)) {
            Log::warning('[SmsChannel] SMS notification skipped: no mobile number', [
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
                'notifiable_id' => is_object($notifiable) && method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'notification' => get_class($notification),
            ]);

            return;
        }

        try {
            // Get SMS driver (txtcmdr, twilio, etc.)
            $driver = $this->getDriver($notification);
            Log::info('[SmsChannel] SMS driver resolved', [
                'driver' => get_class($driver),
            ]);

            // Send SMS via driver
            $recipients = is_array($mobile) ? $mobile : [$mobile];
            $messageText = is_array($message) ? ($message['message'] ?? $message['content']) : $message;
            $senderId = is_array($message) ? ($message['sender_id'] ?? null) : null;

            Log::info('[SmsChannel] Calling driver->send()', [
                'recipients' => $recipients,
                'message_preview' => substr($messageText, 0, 50),
                'sender_id' => $senderId,
            ]);

            $driver->send(
                recipients: $recipients,
                message: $messageText,
                senderId: $senderId
            );

            Log::info('[SmsChannel] SMS notification sent successfully', [
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
                'notifiable_id' => is_object($notifiable) && method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'notification' => get_class($notification),
                'driver' => get_class($driver),
                'recipients' => $recipients,
            ]);

        } catch (TxtcmdrException $e) {
            Log::error('[SmsChannel] SMS notification failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
                'notification' => get_class($notification),
            ]);

            throw $e;
        }
    }

    /**
     * Get recipient mobile number from notifiable
     */
    protected function getRecipientMobile(mixed $notifiable): string|array|null
    {
        Log::debug('[SmsChannel] Resolving recipient mobile', [
            'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
            'is_array' => is_array($notifiable),
            'is_object' => is_object($notifiable),
        ]);

        // Handle anonymous notifications (Notification::route('sms', 'mobile'))
        if (is_array($notifiable) || $notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            // For anonymous notifications, Laravel passes routes as array
            if (is_array($notifiable) && isset($notifiable['sms'])) {
                Log::debug('[SmsChannel] Found mobile in array notifiable', ['mobile' => $notifiable['sms']]);
                return $notifiable['sms'];
            }

            // For AnonymousNotifiable, check routes property
            if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
                // Laravel's AnonymousNotifiable has public $routes property
                $routes = $notifiable->routes;
                Log::debug('[SmsChannel] AnonymousNotifiable routes', ['routes' => $routes, 'has_sms' => isset($routes['sms'])]);
                
                if (isset($routes['sms'])) {
                    Log::debug('[SmsChannel] Found mobile in AnonymousNotifiable routes', ['mobile' => $routes['sms']]);
                    return $routes['sms'];
                }
            }
        }

        // Handle model notifiables
        if (is_object($notifiable)) {
            // Try model-channel package first (if available)
            if (method_exists($notifiable, 'channels')) {
                $mobileChannel = $notifiable->channels()->where('name', 'mobile')->first();
                if ($mobileChannel) {
                    Log::debug('[SmsChannel] Found mobile via channels()', ['mobile' => $mobileChannel->value]);
                    return $mobileChannel->value;
                }
            }

            // Try routeNotificationForSms() method
            if (method_exists($notifiable, 'routeNotificationForSms')) {
                $mobile = $notifiable->routeNotificationForSms();
                if ($mobile) {
                    Log::debug('[SmsChannel] Found mobile via routeNotificationForSms()', ['mobile' => $mobile]);
                    return $mobile;
                }
            }

            // Try direct mobile attribute
            if (isset($notifiable->mobile)) {
                Log::debug('[SmsChannel] Found mobile via ->mobile attribute', ['mobile' => $notifiable->mobile]);
                return $notifiable->mobile;
            }

            // Try phone attribute as fallback
            if (isset($notifiable->phone)) {
                Log::debug('[SmsChannel] Found mobile via ->phone attribute', ['mobile' => $notifiable->phone]);
                return $notifiable->phone;
            }
        }

        Log::warning('[SmsChannel] No mobile number found for notifiable');
        return null;
    }

    /**
     * Get SMS driver based on notification configuration
     */
    protected function getDriver(Notification $notification): SmsDriverInterface
    {
        // Get driver name from notification or config
        $driverName = $this->getDriverName($notification);

        // Resolve driver from container
        $driver = app("sms.driver.{$driverName}");

        if (! $driver instanceof SmsDriverInterface) {
            throw ConfigurationException::missingConfig("SMS driver '{$driverName}' must implement SmsDriverInterface");
        }

        return $driver;
    }

    /**
     * Get driver name from notification or config
     */
    protected function getDriverName(Notification $notification): string
    {
        // Check if notification specifies driver
        if (method_exists($notification, 'smsDriver')) {
            return $notification->smsDriver();
        }

        // Check campaign-level configuration (if notification has campaign)
        if (isset($notification->campaign)) {
            $smsProvider = $notification->campaign->notification_settings['sms_provider'] ?? null;
            if ($smsProvider) {
                return $smsProvider;
            }
        }

        // Default to txtcmdr
        return config('txtcmdr-client.default_driver', 'txtcmdr');
    }
}
