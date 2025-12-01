<?php

declare(strict_types=1);

namespace App\Services\Validation;

use JsonSchema\Validator;

/**
 * JsonSchemaValidator
 *
 * Validates data against JSON schemas.
 */
class JsonSchemaValidator
{
    /**
     * Validate data against a JSON schema.
     *
     * @param  array  $data The data to validate
     * @param  array  $schema The JSON schema
     * @return array An array with 'valid' boolean and 'errors' array
     */
    public function validate(array $data, array $schema): array
    {
        $validator = new Validator();
        $dataObj = (object) $data;
        $schemaObj = (object) $schema;

        try {
            $validator->validate($dataObj, $schemaObj);

            if ($validator->isValid()) {
                return [
                    'valid' => true,
                    'errors' => [],
                ];
            } else {
                $errors = [];
                foreach ($validator->getErrors() as $error) {
                    $errors[] = [
                        'property' => $error['property'] ?? '',
                        'message' => $error['message'] ?? 'Validation failed',
                    ];
                }

                return [
                    'valid' => false,
                    'errors' => $errors,
                ];
            }
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => [
                    [
                        'property' => '',
                        'message' => 'Schema validation error: '.$e->getMessage(),
                    ],
                ],
            ];
        }
    }

    /**
     * Check if data is valid against a schema (returns boolean).
     *
     * @param  array  $data
     * @param  array  $schema
     * @return bool
     */
    public function isValid(array $data, array $schema): bool
    {
        $result = $this->validate($data, $schema);
        return $result['valid'];
    }
}
