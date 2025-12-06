<?php

declare(strict_types=1);

use App\Services\HyperVerge\KycDataExtractor;
use LBHurtado\HyperVerge\Data\Modules\IdCardModuleData;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;

uses(Tests\TestCase::class);

function createMockResult(array $fieldsExtracted): KYCResultData
{
    $idCardModule = new IdCardModuleData(
        module: 'ID Card Validation front',
        status: 'auto_approved',
        moduleId: 'module_test_id',
        details: [],
        raw: [
            'apiResponse' => [
                'result' => [
                    'details' => [
                        [
                            'fieldsExtracted' => $fieldsExtracted,
                        ],
                    ],
                ],
            ],
        ],
        countrySelected: 'phl',
        documentSelected: 'dl',
        imageUrl: null,
        croppedImageUrl: null,
        attempts: 1
    );

    return new KYCResultData(
        status: 'success',
        statusCode: "200",
        requestId: 'test-123',
        transactionId: 'test-transaction',
        applicationStatus: 'auto_approved',
        modules: [$idCardModule],
        raw: []
    );
}

test('extracts all personal data fields', function () {
    $result = createMockResult([
        'fullName' => ['value' => 'HURTADO, LESTER BIADORA', 'conf' => 'high'],
        'dateOfBirth' => ['value' => '21-04-1970', 'conf' => 'high'],
        'address' => ['value' => '8 WEST MAYA DRIVE PHILAM HOMES QUEZON CITY', 'conf' => 'high'],
        'gender' => ['value' => 'M', 'conf' => 'high'],
    ]);

    $data = KycDataExtractor::extractPersonalData($result);

    expect($data['name'])->toBe('HURTADO, LESTER BIADORA');
    expect($data['birth_date'])->toBe('1970-04-21');
    expect($data['address'])->toBe('8 WEST MAYA DRIVE PHILAM HOMES QUEZON CITY');
    expect($data['gender'])->toBe('M');
});

test('returns null for missing fields', function () {
    $result = createMockResult([
        'fullName' => ['value' => 'JOHN DOE', 'conf' => 'high'],
    ]);

    $data = KycDataExtractor::extractPersonalData($result);

    expect($data['name'])->toBe('JOHN DOE');
    expect($data['birth_date'])->toBeNull();
    expect($data['address'])->toBeNull();
    expect($data['gender'])->toBeNull();
});

test('converts birth date from DD-MM-YYYY to Y-m-d', function () {
    $result = createMockResult([
        'dateOfBirth' => ['value' => '15-08-1985', 'conf' => 'high'],
    ]);

    $data = KycDataExtractor::extractPersonalData($result);

    expect($data['birth_date'])->toBe('1985-08-15');
});

test('returns null for invalid birth date format', function () {
    $result = createMockResult([
        'dateOfBirth' => ['value' => 'invalid-date', 'conf' => 'low'],
    ]);

    $data = KycDataExtractor::extractPersonalData($result);

    expect($data['birth_date'])->toBeNull();
});

test('uppercases gender value', function () {
    $result = createMockResult([
        'gender' => ['value' => 'f', 'conf' => 'high'],
    ]);

    $data = KycDataExtractor::extractPersonalData($result);

    expect($data['gender'])->toBe('F');
});
