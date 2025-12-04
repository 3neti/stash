<?php

declare(strict_types=1);

namespace App\Services\HyperVerge;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Data\Modules\IdCardModuleData;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;

/**
 * Extract personal data from HyperVerge KYC verification results.
 * 
 * Extracts structured data from Government ID (name, birth date, address, gender)
 * for storage in Contact model's schemaless meta field.
 */
class KycDataExtractor
{
    /**
     * Extract personal data from KYC result.
     * 
     * @param KYCResultData $result The KYC verification result from HyperVerge
     * @return array Associative array with keys: name, birth_date, address, gender (all nullable)
     */
    public static function extractPersonalData(KYCResultData $result): array
    {
        try {
            // Find ID Card module in results
            $idCardModule = self::findIdCardModule($result);
            
            if (!$idCardModule) {
                Log::warning('[KycDataExtractor] No ID card module found in KYC result');
                return self::emptyData();
            }
            
            // Extract fields from raw API response
            $fieldsExtracted = self::getFieldsExtracted($idCardModule);
            
            if (empty($fieldsExtracted)) {
                Log::warning('[KycDataExtractor] No fields extracted from ID card module');
                return self::emptyData();
            }
            
            // Extract and format each field
            $data = [
                'name' => self::extractName($fieldsExtracted),
                'birth_date' => self::extractBirthDate($fieldsExtracted),
                'address' => self::extractAddress($fieldsExtracted),
                'gender' => self::extractGender($fieldsExtracted),
            ];
            
            Log::info('[KycDataExtractor] Personal data extracted successfully', [
                'fields_present' => array_keys(array_filter($data, fn($v) => $v !== null)),
            ]);
            
            return $data;
            
        } catch (\Exception $e) {
            Log::error('[KycDataExtractor] Failed to extract personal data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return self::emptyData();
        }
    }
    
    /**
     * Find ID Card module from KYC result.
     */
    protected static function findIdCardModule(KYCResultData $result): ?IdCardModuleData
    {
        foreach ($result->modules as $module) {
            if ($module instanceof IdCardModuleData) {
                return $module;
            }
        }
        
        return null;
    }
    
    /**
     * Get fieldsExtracted array from raw API response.
     */
    protected static function getFieldsExtracted(IdCardModuleData $module): array
    {
        $raw = $module->raw ?? [];
        
        return $raw['apiResponse']['result']['details'][0]['fieldsExtracted'] ?? [];
    }
    
    /**
     * Extract full name from fields.
     */
    protected static function extractName(array $fields): ?string
    {
        $name = $fields['fullName']['value'] ?? null;
        
        return !empty($name) ? trim($name) : null;
    }
    
    /**
     * Extract birth date and convert to Y-m-d format.
     * 
     * HyperVerge returns dates in DD-MM-YYYY format (e.g., "21-04-1970").
     * Convert to Laravel/MySQL Y-m-d format (e.g., "1970-04-21").
     */
    protected static function extractBirthDate(array $fields): ?string
    {
        $dateString = $fields['dateOfBirth']['value'] ?? null;
        
        if (empty($dateString)) {
            return null;
        }
        
        try {
            // Parse DD-MM-YYYY format
            $date = Carbon::createFromFormat('d-m-Y', $dateString);
            
            // Return as Y-m-d for database storage
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('[KycDataExtractor] Failed to parse birth date', [
                'raw_value' => $dateString,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }
    
    /**
     * Extract address from fields.
     */
    protected static function extractAddress(array $fields): ?string
    {
        $address = $fields['address']['value'] ?? null;
        
        return !empty($address) ? trim($address) : null;
    }
    
    /**
     * Extract gender from fields.
     */
    protected static function extractGender(array $fields): ?string
    {
        $gender = $fields['gender']['value'] ?? null;
        
        return !empty($gender) ? strtoupper(trim($gender)) : null;
    }
    
    /**
     * Return empty data structure.
     */
    protected static function emptyData(): array
    {
        return [
            'name' => null,
            'birth_date' => null,
            'address' => null,
            'gender' => null,
        ];
    }
}
