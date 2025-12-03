<?php

use App\Services\Processors\PortPHP\Transformations\RegexTransformer;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->transformer = new RegexTransformer();
    // Mock Log facade to prevent facade errors
    Log::partialMock();
});

test('it extracts using regex pattern', function () {
    // Extract area code from phone number
    $result = $this->transformer->extract('+639171234567', '/^(\+63|0)?(9\d{2})\d{7}$/', 2);
    expect($result)->toBe('917');

    // Extract domain from email
    $result = $this->transformer->extract('john@company.com', '/@(.+)$/', 1);
    expect($result)->toBe('company.com');

    // No match returns null
    $result = $this->transformer->extract('invalid-phone', '/^(\+63|0)?(9\d{2})\d{7}$/', 2);
    expect($result)->toBeNull();
});

test('it extracts all matches using regex', function () {
    // Extract all hashtags
    $result = $this->transformer->extractAll('Hello #world #php #laravel', '/#(\w+)/', 1);
    expect($result)->toBe(['world', 'php', 'laravel']);

    // Extract all phone numbers
    $result = $this->transformer->extractAll('Phones: 09171234567, 09181234567', '/09(\d{9})/', 1);
    expect($result)->toBe(['171234567', '181234567']);

    // No matches returns empty array
    $result = $this->transformer->extractAll('no hashtags here', '/#(\w+)/', 1);
    expect($result)->toBe([]);
});

test('it replaces using regex pattern', function () {
    // Reformat date from MM/DD/YYYY to YYYY-MM-DD
    $result = $this->transformer->replace('12/25/2024', '/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$1-$2');
    expect($result)->toBe('2024-12-25');

    // Remove prefix
    $result = $this->transformer->replace('EMP-001234', '/^EMP-/', '');
    expect($result)->toBe('001234');

    // Normalize whitespace
    $result = $this->transformer->replace('  hello  world  ', '/\s+/', ' ');
    expect($result)->toBe(' hello world ');
});

test('it splits using regex pattern', function () {
    // Split name on whitespace
    $result = $this->transformer->split('Juan Dela Cruz', '/\s+/');
    expect($result)->toBe(['Juan', 'Dela', 'Cruz']);

    // Split on multiple delimiters
    $result = $this->transformer->split('apple,banana;orange|grape', '/[,;|]/');
    expect($result)->toBe(['apple', 'banana', 'orange', 'grape']);

    // Split with limit
    $result = $this->transformer->split('John Doe Smith', '/\s+/', 2);
    expect($result)->toBe(['John', 'Doe Smith']);
});

test('it transforms using config', function () {
    // Extract transformation
    $result = $this->transformer->transform('+639171234567', [
        'type' => 'extract',
        'pattern' => '/^(\+63|0)?(9\d{2})\d{7}$/',
        'group' => 2,
    ]);
    expect($result)->toBe('917');

    // Replace transformation
    $result = $this->transformer->transform('12/25/2024', [
        'type' => 'replace',
        'pattern' => '/^(\d{2})\/(\d{2})\/(\d{4})$/',
        'replacement' => '$3-$1-$2',
    ]);
    expect($result)->toBe('2024-12-25');

    // Extract_all transformation
    $result = $this->transformer->transform('Hello #php #laravel', [
        'type' => 'extract_all',
        'pattern' => '/#(\w+)/',
        'group' => 1,
    ]);
    expect($result)->toBe(['php', 'laravel']);

    // Split transformation
    $result = $this->transformer->transform('Juan Dela Cruz', [
        'type' => 'split',
        'pattern' => '/\s+/',
    ]);
    expect($result)->toBe(['Juan', 'Dela', 'Cruz']);
});

test('it transforms row with multiple fields', function () {
    $row = [
        'phone' => '+639171234567',
        'registration_date' => '12/25/2024',
        'email' => 'john@company.com',
        'full_name' => 'Juan Dela Cruz',
        'bio' => 'Hello #php #laravel #vue',
        'employee_id' => 'EMP-001234',
    ];

    $transformations = [
        'phone' => [
            'type' => 'extract',
            'pattern' => '/^(\+63|0)?(9\d{2})\d{7}$/',
            'group' => 2,
        ],
        'registration_date' => [
            'type' => 'replace',
            'pattern' => '/^(\d{2})\/(\d{2})\/(\d{4})$/',
            'replacement' => '$3-$1-$2',
        ],
        'full_name' => [
            'type' => 'split',
            'pattern' => '/\s+/',
            'output_fields' => ['first_name', 'last_name'],
        ],
        'bio' => [
            'type' => 'extract_all',
            'pattern' => '/#(\w+)/',
            'group' => 1,
            'output' => 'comma_separated',
        ],
        'employee_id' => [
            'type' => 'replace',
            'pattern' => '/^EMP-/',
            'replacement' => '',
        ],
    ];

    $result = $this->transformer->transformRow($row, $transformations);

    // Check transformed values
    expect($result['phone'])->toBe('917');
    expect($result['registration_date'])->toBe('2024-12-25');
    expect($result['bio'])->toBe('php,laravel,vue');
    expect($result['employee_id'])->toBe('001234');

    // Check split created new fields
    expect($result['first_name'])->toBe('Juan');
    expect($result['last_name'])->toContain('Dela');
});

test('it handles extract_all output formats', function () {
    // Array output (default)
    $result = $this->transformer->transformRow(['tags' => 'Hello #php #laravel #vue'], [
        'tags' => [
            'type' => 'extract_all',
            'pattern' => '/#(\w+)/',
            'group' => 1,
            'output' => 'array',
        ],
    ]);
    expect($result['tags'])->toBe(['php', 'laravel', 'vue']);

    // Comma-separated output
    $result = $this->transformer->transformRow(['tags' => 'Hello #php #laravel #vue'], [
        'tags' => [
            'type' => 'extract_all',
            'pattern' => '/#(\w+)/',
            'group' => 1,
            'output' => 'comma_separated',
        ],
    ]);
    expect($result['tags'])->toBe('php,laravel,vue');

    // JSON output
    $result = $this->transformer->transformRow(['tags' => 'Hello #php #laravel #vue'], [
        'tags' => [
            'type' => 'extract_all',
            'pattern' => '/#(\w+)/',
            'group' => 1,
            'output' => 'json',
        ],
    ]);
    expect($result['tags'])->toBe('["php","laravel","vue"]');
});

test('it handles split with remove_original flag', function () {
    // Keep original field
    $result = $this->transformer->transformRow(['full_name' => 'Juan Dela Cruz'], [
        'full_name' => [
            'type' => 'split',
            'pattern' => '/\s+/',
            'output_fields' => ['first_name', 'last_name'],
            'remove_original' => false,
        ],
    ]);
    expect($result)->toHaveKey('full_name');
    expect($result['first_name'])->toBe('Juan');

    // Remove original field
    $result = $this->transformer->transformRow(['full_name' => 'Juan Dela Cruz'], [
        'full_name' => [
            'type' => 'split',
            'pattern' => '/\s+/',
            'output_fields' => ['first_name', 'last_name'],
            'remove_original' => true,
        ],
    ]);
    expect($result)->not()->toHaveKey('full_name');
    expect($result['first_name'])->toBe('Juan');
});

test('it handles invalid regex patterns gracefully', function () {
    // Mock Log to capture error logging
    Log::shouldReceive('error')->twice();

    // Invalid pattern should not throw exception
    $result = $this->transformer->extract('test', '/[invalid/', 1);
    expect($result)->toBeNull();

    $result = $this->transformer->replace('test', '/[invalid/', 'replacement');
    expect($result)->toBe('test'); // Returns original value
});

test('it handles non-string values', function () {
    // Should return value unchanged if not a string
    $result = $this->transformer->transform(12345, [
        'type' => 'extract',
        'pattern' => '/\d+/',
    ]);
    expect($result)->toBe(12345);

    $result = $this->transformer->transform(null, [
        'type' => 'replace',
        'pattern' => '/test/',
        'replacement' => 'replacement',
    ]);
    expect($result)->toBeNull();
});

test('it handles missing pattern config', function () {
    // Mock Log to capture warning
    Log::shouldReceive('warning')->once();

    // Should return value unchanged if pattern is missing
    $result = $this->transformer->transform('test', [
        'type' => 'extract',
        // missing 'pattern'
    ]);
    expect($result)->toBe('test');
});
