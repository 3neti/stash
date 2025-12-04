# Laravel Reverb - Real-Time Broadcasting

This project uses **Laravel Reverb** for real-time WebSocket communication, providing instant updates for KYC verification results and other real-time features.

## What is Reverb?

Laravel Reverb is a first-party WebSocket server for Laravel applications. Unlike third-party services like Pusher, Reverb:
- Runs entirely on your own infrastructure (perfect for air-gapped deployments)
- Has zero external dependencies
- Uses the Pusher protocol for compatibility
- Powered by ReactPHP (no additional extensions needed)

## Quick Start

### 1. Environment Configuration

Ensure your `.env` file has these settings:

```bash
# Broadcasting Configuration
BROADCAST_CONNECTION=reverb

# Reverb Server Configuration
REVERB_APP_ID=stash
REVERB_APP_KEY=stash-key
REVERB_APP_SECRET=stash-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite Configuration (for frontend)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**⚠️ CRITICAL**: `BROADCAST_CONNECTION` must be set to `reverb`, not `log` or `null`!

### 2. Starting Reverb Server

#### Option A: Using Composer Dev Script (Recommended)

```bash
composer run dev
```

This starts 5 concurrent services:
- Laravel server (port 8000)
- Horizon (queue worker)
- Pail (log viewer)
- **Reverb WebSocket server (port 8080)**
- Vite dev server (HMR)

#### Option B: Start Reverb Manually

For debugging or standalone testing:

```bash
php artisan reverb:start --debug
```

The `--debug` flag shows real-time connection logs:
- Connection established/closed events
- Message received/handled events
- Channel subscriptions
- Broadcast events

**Example debug output:**
```
INFO  Starting server on 0.0.0.0:8080 (localhost).

Connection Established ................................. 717870822.714370592
Message Received ....................................... 717870822.714370592

1▕ {
2▕     "event": "pusher:subscribe",
3▕     "data": {
4▕         "auth": "",
5▕         "channel": "kyc.EKYC-1764773764-3863"
6▕     }
7▕ }

Message Handled ........................................ 717870822.714370592
```

### 3. Testing Real-Time Features

1. **Start Reverb server** (see above)
2. **Open browser** to your application
3. **Trigger an event** (e.g., complete KYC verification)
4. **Watch console** for WebSocket messages

**Browser console logs (success):**
```
[KYC Complete] Listening for contact.ready on channel: kyc.EKYC-123456
[KYC Complete] Contact ready event received { contact: {...} }
```

**Browser console logs (failure):**
```
WebSocket connection to 'ws://localhost:8080/app/...' failed
```
👉 This means Reverb is not running or `BROADCAST_CONNECTION` is not set to `reverb`

## How It Works

### Backend: Broadcasting Events

Events that implement `ShouldBroadcastNow` are immediately broadcast via Reverb:

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\Channel;

class ContactReady implements ShouldBroadcastNow
{
    public bool $afterCommit = true;

    public function __construct(
        public readonly Contact $contact,
        public readonly string $transactionId,
    ) {}

    // Broadcast on public channel
    public function broadcastOn(): array
    {
        return [new Channel("kyc.{$this->transactionId}")];
    }

    // Custom event name
    public function broadcastAs(): string
    {
        return 'contact.ready';
    }

    // Data sent to clients
    public function broadcastWith(): array
    {
        return ['contact' => ContactData::fromContact($this->contact)];
    }
}
```

**Dispatch the event:**
```php
ContactReady::dispatch($contact, $transactionId);
```

### Frontend: Listening for Events

Configure Echo in `resources/js/app.ts`:

```typescript
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    enabledTransports: ['ws', 'wss'],
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
});
```

**Listen for events in Vue components:**

```vue
<script setup lang="ts">
import { useEchoPublic } from '@laravel/echo-vue';
import { onMounted, onBeforeUnmount } from 'vue';

const channelName = `kyc.${transactionId}`;

const { listen, stopListening, leaveChannel } = useEchoPublic(
    channelName,
    '.contact.ready',
    (payload) => {
        console.log('Contact ready:', payload.contact);
        // Update UI with payload.contact
    }
);

onMounted(() => listen());
onBeforeUnmount(() => {
    stopListening();
    leaveChannel(true);
});
</script>
```

## Channel Types

### Public Channels
No authentication required. Perfect for public data with security via unique identifiers:

```php
return [new Channel("kyc.{$transactionId}")];
```

```typescript
useEchoPublic('kyc.123', '.contact.ready', callback)
```

### Private Channels
Require authentication. For user-specific data:

```php
return [new PrivateChannel("user.{$userId}")];
```

```typescript
useEchoPrivate('user.123', '.notification', callback)
```

### Presence Channels
Track who's online in a channel:

```php
return [new PresenceChannel("chat.{$roomId}")];
```

## Troubleshooting

### Issue: WebSocket connection fails

**Symptoms:**
```
WebSocket connection to 'ws://localhost:8080/...' failed
```

**Solutions:**
1. Check Reverb is running: `php artisan reverb:start --debug`
2. Verify `.env` has `BROADCAST_CONNECTION=reverb`
3. Restart Laravel server after changing `.env`
4. Check port 8080 is not blocked by firewall

### Issue: Events broadcast but not received

**Symptoms:**
- Laravel logs show "Broadcasting [event] on channels [...]"
- Browser console shows "Listening for event on channel: ..."
- But no event received

**Solutions:**
1. Check `BROADCAST_CONNECTION=reverb` (not `log`)
2. Verify Reverb server is running
3. Check channel name matches exactly
4. Check event name includes `.` prefix: `.contact.ready`

### Issue: Connection keeps closing/reconnecting

**Symptoms:**
```
Connection Established ...
Connection Closed ...
Connection Established ...
```

**Solutions:**
1. Check browser is connecting to correct host/port
2. Verify `wsHost` and `wsPort` in Echo configuration
3. Check for CORS issues in production

## Production Deployment

### Using Supervisor

Create `/etc/supervisor/conf.d/reverb.conf`:

```ini
[program:reverb]
command=/usr/bin/php /path/to/stash/artisan reverb:start
directory=/path/to/stash
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/reverb.log
```

Reload supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

### Environment Variables

For production, update these in `.env`:

```bash
BROADCAST_CONNECTION=reverb
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https

# Generate secure credentials
REVERB_APP_ID=production-app-id
REVERB_APP_KEY=$(openssl rand -hex 32)
REVERB_APP_SECRET=$(openssl rand -hex 32)
```

### SSL/TLS Configuration

For secure WebSocket connections (wss://), configure Reverb TLS in `config/reverb.php`:

```php
'options' => [
    'tls' => [
        'local_cert' => '/path/to/cert.pem',
        'local_pk' => '/path/to/key.pem',
        'verify_peer' => false,
    ],
],
```

Or use a reverse proxy (Nginx/Apache) to handle SSL termination.

## Current Implementation

### KYC Real-Time Updates

**Event:** `ContactReady`
**Channel:** `kyc.{transactionId}` (public)
**Trigger:** After Contact created and media copied in `FetchKycDataFromCallback` job
**UI:** `Kyc/Complete.vue` - Automatically displays Contact data when ready

**Flow:**
1. User completes KYC verification in HyperVerge
2. Callback redirects to `/kyc/callback`
3. `FetchKycDataFromCallback` job processes data
4. `ContactReady` event broadcasts via Reverb
5. Browser receives event and updates UI instantly
6. No manual refresh needed!

## References

- [Laravel Reverb Documentation](https://laravel.com/docs/12.x/reverb)
- [Laravel Broadcasting Documentation](https://laravel.com/docs/12.x/broadcasting)
- [Laravel Echo Documentation](https://github.com/laravel/echo)
- [Pusher Protocol Documentation](https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/)
