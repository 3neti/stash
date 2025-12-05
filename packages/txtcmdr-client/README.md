# txtcmdr SMS Client Package

API client for txtcmdr SMS server - enables tenant-specific SMS sending via txtcmdr with injected credentials.

## Overview

This package provides a Laravel-friendly HTTP client for txtcmdr, allowing Stash campaigns to send SMS via txtcmdr API server. Each tenant can use their own EngageSPARK credentials by configuring them in txtcmdr and passing the API token to Stash.

**Key Features**:
- 🔐 Token-based authentication (Sanctum)
- 🔄 Automatic retry with exponential backoff
- 📦 Type-safe DTOs for requests/responses
- ⚡ Immediate and scheduled SMS support
- 🚨 Comprehensive exception hierarchy
- 🧪 Fully testable with mocks

## Architecture

```
Stash Campaign (Tenant A)
  → TxtcmdrClient::send($token, $recipients, $message)
    → HTTP POST https://txtcmdr.example.com/api/send
      → txtcmdr validates token → retrieves User A's EngageSPARK config
        → SendSMSJob → EngageSPARK API

Stash Campaign (Tenant B)
  → TxtcmdrClient::send($token, $recipients, $message)
    → HTTP POST https://txtcmdr.example.com/api/send
      → txtcmdr validates token → retrieves User B's EngageSPARK config
        → SendSMSJob → EngageSPARK API
```

## Installation

Package is installed as part of Stash mono-repo. No separate installation needed.

```bash
composer dump-autoload
```

## Configuration

Publish configuration (optional):

```bash
php artisan vendor:publish --tag=txtcmdr-client-config
```

### Environment Variables

```env
# Global txtcmdr instance (fallback)
TXTCMDR_API_URL=https://txtcmdr.example.com
TXTCMDR_TIMEOUT=30
TXTCMDR_VERIFY_SSL=true
```

### Per-Campaign Configuration

Store in `campaigns.txtcmdr_config` JSON column:

```php
'txtcmdr_config' => [
    'enabled' => true,
    'api_url' => 'https://txtcmdr.example.com',
    'api_token' => '1|abc123...', // Sanctum token from txtcmdr
    'sender_id' => 'STASH',
    'timeout' => 30,
]
```

## Usage

### Basic Sending

```php
use LBHurtado\DeadDrop\TxtcmdrClient\Client\TxtcmdrClient;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsRequest;

$client = new TxtcmdrClient(
    baseUrl: 'https://txtcmdr.example.com',
    apiToken: '1|abc123...',
    timeout: 30
);

// Send immediate SMS
$request = new SendSmsRequest(
    recipients: ['+639171234567', '+639178251991'],
    message: 'Hello from Stash!',
    senderId: 'STASH'
);

$response = $client->send($request);
// Returns: SendSmsResponse { success: true, jobsDispatched: 2 }
```

### Scheduled Sending

```php
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\ScheduleSmsRequest;
use Carbon\Carbon;

$scheduleRequest = new ScheduleSmsRequest(
    recipients: ['+639171234567'],
    message: 'Reminder: Document ready for pickup',
    scheduledAt: Carbon::now()->addHours(2),
    senderId: 'STASH'
);

$response = $client->schedule($scheduleRequest);
// Returns: SendSmsResponse { success: true, scheduledMessageId: 123 }
```

### Using with Campaign Model

```php
class Campaign extends Model
{
    protected $casts = [
        'txtcmdr_config' => 'encrypted:array',
    ];
    
    public function getTxtcmdrClient(): TxtcmdrClient
    {
        $config = $this->txtcmdr_config;
        
        return new TxtcmdrClient(
            baseUrl: $config['api_url'] ?? config('txtcmdr-client.default_url'),
            apiToken: $config['api_token'],
            timeout: $config['timeout'] ?? config('txtcmdr-client.timeout')
        );
    }
    
    public function sendSms(array $recipients, string $message): SendSmsResponse
    {
        $client = $this->getTxtcmdrClient();
        
        $request = new SendSmsRequest(
            recipients: $recipients,
            message: $message,
            senderId: $this->txtcmdr_config['sender_id'] ?? 'STASH'
        );
        
        return $client->send($request);
    }
}
```

## Exception Handling

```php
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\*;

try {
    $response = $client->send($request);
} catch (AuthenticationException $e) {
    // Invalid or expired token
    Log::error('txtcmdr auth failed', ['error' => $e->getMessage()]);
} catch (InvalidRecipientException $e) {
    // Invalid phone numbers
    Log::error('Invalid recipients', ['error' => $e->getMessage()]);
} catch (ApiRequestException $e) {
    // Network or server error
    Log::error('txtcmdr API error', ['error' => $e->getMessage()]);
} catch (ConfigurationException $e) {
    // Configuration error
    Log::error('txtcmdr config error', ['error' => $e->getMessage()]);
}
```

### Exception Hierarchy

```
TxtcmdrException (base)
├── AuthenticationException - Invalid token, expired token
├── InvalidRecipientException - Invalid phone format, empty recipients
├── ApiRequestException - Network errors, timeout, 500 errors
└── ConfigurationException - Missing config, invalid URL
```

## Retry Strategy

The client automatically retries failed requests:

- **Network errors**: 3 retries with exponential backoff (1s, 2s, 3s)
- **Server errors (5xx)**: 2 retries with exponential backoff (2s, 4s)
- **Authentication errors (401)**: No retry, throw immediately
- **Validation errors (422)**: No retry, throw immediately

Configure retry behavior in `config/txtcmdr-client.php`:

```php
'retry' => [
    'network_errors' => [
        'attempts' => 3,
        'backoff_ms' => 1000, // 1s starting backoff
    ],
    'server_errors' => [
        'attempts' => 2,
        'backoff_ms' => 2000, // 2s starting backoff
    ],
],
```

## Tenant Setup Workflow

### 1. Register in txtcmdr

Tenant creates account in txtcmdr:
- Email: tenant@example.com
- Configure SMS credentials (Settings → SMS)
- Enter EngageSPARK API key and Org ID

### 2. Generate API Token

In txtcmdr, generate Sanctum token:

```bash
php artisan tinker
$user = User::where('email', 'tenant@example.com')->first();
$token = $user->createToken('stash-campaign-invoice')->plainTextToken;
# Output: 1|abc123xyz456...
```

### 3. Configure Campaign in Stash

Add txtcmdr config to campaign:

```php
$campaign->txtcmdr_config = [
    'enabled' => true,
    'api_url' => 'https://txtcmdr.example.com',
    'api_token' => '1|abc123xyz456...', // From step 2
    'sender_id' => 'INVOICE',
];
$campaign->save();
```

### 4. Test SMS Sending

```php
$campaign->sendSms(
    recipients: ['+639171234567'],
    message: 'Test message from Stash'
);
```

## Testing

```bash
# Run package tests
php artisan test packages/txtcmdr-client/tests

# Or using Pest directly
./vendor/bin/pest packages/txtcmdr-client/tests
```

### Mocking in Tests

```php
use LBHurtado\DeadDrop\TxtcmdrClient\Client\TxtcmdrClientInterface;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsResponse;
use Mockery;

public function test_sending_sms(): void
{
    $mockClient = Mockery::mock(TxtcmdrClientInterface::class);
    $mockClient->shouldReceive('send')
        ->once()
        ->andReturn(new SendSmsResponse(
            success: true,
            jobsDispatched: 1
        ));
    
    $this->app->instance(TxtcmdrClientInterface::class, $mockClient);
    
    // Test code here
}
```

## Security

- **Token Storage**: Always use encrypted cast for `txtcmdr_config`
- **HTTPS Only**: Enforce HTTPS for txtcmdr API URLs
- **Token Rotation**: Implement token refresh mechanism
- **Tenant Isolation**: Each campaign uses separate txtcmdr user token

## License

MIT License
