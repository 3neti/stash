<?php

namespace Database\Seeders;

use App\Models\Credential;
use Illuminate\Database\Seeder;

class CredentialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $credentials = [
            [
                'credentialable_type' => null,
                'credentialable_id' => null,
                'key' => 'openai_api_key',
                'value' => config('services.openai.api_key') ?? env('OPENAI_API_KEY') ?? 'demo-openai-key-'.bin2hex(random_bytes(16)),
                'provider' => 'openai',
                'metadata' => [
                    'description' => 'OpenAI API key for GPT models',
                    'usage' => 'OCR, Classification, Extraction',
                ],
            ],
            [
                'credentialable_type' => null,
                'credentialable_id' => null,
                'key' => 'anthropic_api_key',
                'value' => config('services.anthropic.api_key') ?? 'demo-anthropic-key-'.bin2hex(random_bytes(16)),
                'provider' => 'anthropic',
                'metadata' => [
                    'description' => 'Anthropic API key for Claude models',
                    'usage' => 'Document analysis, Extraction',
                ],
            ],
            [
                'credentialable_type' => null,
                'credentialable_id' => null,
                'key' => 'aws_access_key',
                'value' => config('filesystems.disks.s3.key') ?? 'demo-aws-access-key',
                'provider' => 'aws',
                'metadata' => [
                    'description' => 'AWS access key for S3 storage',
                    'usage' => 'Document storage',
                ],
            ],
            [
                'credentialable_type' => null,
                'credentialable_id' => null,
                'key' => 'aws_secret_key',
                'value' => config('filesystems.disks.s3.secret') ?? 'demo-aws-secret-key-'.bin2hex(random_bytes(32)),
                'provider' => 'aws',
                'metadata' => [
                    'description' => 'AWS secret key for S3 storage',
                    'usage' => 'Document storage',
                ],
            ],
        ];

        foreach ($credentials as $credentialData) {
            Credential::updateOrCreate(
                [
                    'credentialable_type' => $credentialData['credentialable_type'],
                    'credentialable_id' => $credentialData['credentialable_id'],
                    'key' => $credentialData['key'],
                ],
                $credentialData
            );
        }

        $this->command->info('Seeded '.count($credentials).' system credentials');
    }
}
