<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use LBHurtado\DeadDrop\TxtcmdrClient\Client\TxtcmdrClient;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\ScheduleSmsRequest;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsRequest;
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\TxtcmdrException;

class TestTxtcmdrClient extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'txtcmdr:test
                            {--url= : txtcmdr API URL (e.g., http://txtcmdr.test:8000)}
                            {--token= : Sanctum API token}
                            {--mobile= : Test mobile number (e.g., +639171234567)}
                            {--message= : Custom message to send}
                            {--sender-id= : Sender ID (default: STASH)}
                            {--scheduled : Test scheduled SMS instead of immediate}
                            {--minutes=1 : Minutes to schedule from now (default: 1)}';

    /**
     * The console command description.
     */
    protected $description = 'Test txtcmdr SMS client integration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Testing txtcmdr SMS Client');
        $this->newLine();

        // Get credentials
        $url = $this->option('url') ?: $this->ask('txtcmdr API URL', 'http://txtcmdr.test:8000');
        $token = $this->option('token') ?: $this->secret('Sanctum API Token');
        $mobile = $this->option('mobile') ?: $this->ask('Test Mobile Number', '+639171234567');

        if (empty($token)) {
            $this->error('❌ API token is required');
            $this->newLine();
            $this->comment('Generate token in txtcmdr:');
            $this->line('  php artisan tinker');
            $this->line('  $user = User::first();');
            $this->line('  $token = $user->createToken(\'stash-test\')->plainTextToken;');

            return self::FAILURE;
        }

        // Display configuration
        $this->table(
            ['Setting', 'Value'],
            [
                ['API URL', $url],
                ['Token', substr($token, 0, 20).'...'],
                ['Mobile', $mobile],
                ['SSL Verify', config('txtcmdr-client.verify_ssl') ? 'Yes' : 'No'],
                ['Timeout', config('txtcmdr-client.timeout').'s'],
            ]
        );
        $this->newLine();

        try {
            // Initialize client
            $this->info('📡 Initializing txtcmdr client...');
            $client = new TxtcmdrClient(
                baseUrl: $url,
                apiToken: $token,
                timeout: config('txtcmdr-client.timeout', 30),
                verifySSL: config('txtcmdr-client.verify_ssl', true)
            );
            $this->info('✓ Client initialized');
            $this->newLine();

            // Test immediate or scheduled send
            if ($this->option('scheduled')) {
                $this->testScheduledSend($client, $mobile);
            } else {
                $this->testImmediateSend($client, $mobile);
            }

            $this->newLine();
            $this->info('✅ Test completed successfully!');

            return self::SUCCESS;

        } catch (TxtcmdrException $e) {
            $this->newLine();
            $this->error('❌ txtcmdr Error: '.$e->getMessage());
            $this->newLine();

            // Provide helpful hints based on exception type
            $exceptionClass = class_basename($e);
            match ($exceptionClass) {
                'AuthenticationException' => $this->comment('💡 Check your API token is valid and not expired'),
                'InvalidRecipientException' => $this->comment('💡 Check mobile number format (E.164 or local format)'),
                'ApiRequestException' => $this->comment('💡 Check txtcmdr server is running and accessible'),
                'ConfigurationException' => $this->comment('💡 Check API URL is valid (include http:// or https://)'),
                default => null,
            };

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Unexpected Error: '.$e->getMessage());

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Test immediate SMS send
     */
    protected function testImmediateSend(TxtcmdrClient $client, string $mobile): void
    {
        $this->info('📤 Testing immediate SMS send...');

        $message = $this->option('message') ?: '🧪 Test message from Stash txtcmdr client at '.now()->format('H:i:s');
        $senderId = $this->option('sender-id') ?: 'STASH';

        $request = new SendSmsRequest(
            recipients: [$mobile],
            message: $message,
            senderId: $senderId
        );

        $this->line('  Recipients: '.$mobile);
        $this->line('  Message: '.$request->message);
        $this->line('  Sender ID: '.$request->senderId);
        $this->newLine();

        $bar = $this->output->createProgressBar(1);
        $bar->setFormat('  Sending... %bar% %message%');
        $bar->setMessage('Contacting txtcmdr API');
        $bar->start();

        $response = $client->send($request);

        $bar->setMessage('Done');
        $bar->finish();
        $this->newLine(2);

        // Display response
        if ($response->success) {
            $this->info('✓ SMS sent successfully');
            $this->line('  Jobs Dispatched: '.$response->jobsDispatched);
            $this->newLine();
            $this->comment('📱 Check your mobile for the SMS message');
        } else {
            $this->warn('⚠ SMS request failed');
            if ($response->message) {
                $this->line('  Message: '.$response->message);
            }
        }
    }

    /**
     * Test scheduled SMS send
     */
    protected function testScheduledSend(TxtcmdrClient $client, string $mobile): void
    {
        $minutes = (int) $this->option('minutes');
        $scheduledAt = Carbon::now()->addMinutes($minutes);

        $this->info('📅 Testing scheduled SMS send...');

        $message = $this->option('message') ?: '🧪 Scheduled test from Stash at '.now()->format('H:i:s');
        $senderId = $this->option('sender-id') ?: 'STASH';

        $request = new ScheduleSmsRequest(
            recipients: [$mobile],
            message: $message,
            scheduledAt: $scheduledAt,
            senderId: $senderId
        );

        $this->line('  Recipients: '.$mobile);
        $this->line('  Message: '.$request->message);
        $this->line('  Scheduled At: '.$scheduledAt->format('Y-m-d H:i:s').' ('.$scheduledAt->diffForHumans().')');
        $this->line('  Sender ID: '.$request->senderId);
        $this->newLine();

        $bar = $this->output->createProgressBar(1);
        $bar->setFormat('  Scheduling... %bar% %message%');
        $bar->setMessage('Contacting txtcmdr API');
        $bar->start();

        $response = $client->schedule($request);

        $bar->setMessage('Done');
        $bar->finish();
        $this->newLine(2);

        // Display response
        if ($response->success) {
            $this->info('✓ SMS scheduled successfully');
            $this->line('  Scheduled Message ID: '.$response->scheduledMessageId);
            $this->newLine();
            $this->comment('📱 Message will be sent in '.$minutes.' minute(s)');
        } else {
            $this->warn('⚠ SMS scheduling failed');
            if ($response->message) {
                $this->line('  Message: '.$response->message);
            }
        }
    }
}
