<?php

/**
 * Tiny: PHP Framework
 * https://github.com/ranaroussi/tiny
 *
 * Copyright 2013-2024 Ran Aroussi (@aroussi)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

declare(strict_types=1);


class TinyModel
{
    private const KNOWN_TYPES = [
        'string' => 'is_string',
        'int' => 'is_int',
        'array' => 'is_array',
        'bool' => 'is_bool',
        'float' => 'is_float',
        'double' => 'is_float',
        'object' => 'is_object',
        'callable' => 'is_callable',
        'resource' => 'is_resource',
        'datetime' => 'strtotime'
    ];

    public array $validationErrors = [];

    /**
     * Converts validation errors to an AlpineJS-compatible string.
     *
     * @return string A string of AlpineJS expressions setting invalid properties to true
     */
    public function validationErrorsToAlpineJs(): string
    {
        return 'invalid.' . implode('=true;invalid.', array_keys($this->validationErrors)) . '=true;';
    }

    /**
     * Validates data against a given schema.
     *
     * @param object|array $data The data to validate
     * @param array $schema The schema to validate against
     * @return bool True if the data is valid, false otherwise
     * @throws Exception If a required key is missing in the data
     */
    public function isValid(object|array $data, array $schema): bool
    {
        if (is_object($data)) {
            $data = (array)$data;
        }
        foreach ($schema as $item => $type) {
            if (is_array($type)) {
                if (!isset($data[$item])) {
                    throw new \Exception("Key $item is missing", 1);
                }
                $this->isValid($data[$item], $type);
            } else {
                $this->validateField($item, $data[$item] ?? null, $type);
            }
        }
        return empty($this->validationErrors);
    }

    /**
     * Validates a single field against its type definition.
     *
     * @param string $item The name of the field
     * @param mixed $value The value of the field
     * @param string $type The type definition for the field
     */
    private function validateField(string $item, mixed $value, string $type): void
    {
        $types = explode('|', $type);
        $isValid = false;

        foreach ($types as $singleType) {
            if (!preg_match('/^(\[)?(\w+)(\((\d+)\))?\]?$/', trim($singleType), $matches)) {
                $this->validationErrors[$item] = "Invalid type definition for `$item`";
                return;
            }

            $isOptional = $matches[1] === '[';
            $dataType = $matches[2];
            $length = $matches[4] ?? null;

            if ($isOptional && $value === null) {
                $isValid = true;
                break;
            }

            if ($this->validateType($item, $value, $dataType, $length)) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            $this->validationErrors[$item] = "`$item` does not match any of the allowed types: $type";
        }
    }

    /**
     * Validates a value against a specific type and length.
     *
     * @param string $item The name of the field (unused in this method, but kept for potential future use)
     * @param mixed $value The value to validate
     * @param string $dataType The expected data type
     * @param string|null $length The maximum length (if applicable)
     * @return bool True if the value is valid, false otherwise
     */
    private function validateType(string $item, mixed $value, string $dataType, ?string $length): bool
    {
        if (isset(self::KNOWN_TYPES[$dataType])) {
            $validationFunction = self::KNOWN_TYPES[$dataType];
            if (!$validationFunction($value)) {
                return false;
            }
        } elseif (!$this->isSpecificObject($value, $dataType)) {
            return false;
        }

        if ($length !== null) {
            if (is_array($value) && count($value) > (int)$length) {
                return false;
            }
            if (is_string($value) && mb_strlen($value) > (int)$length) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a value is an instance of a specific object or enum.
     *
     * @param mixed $value The value to check
     * @param string $object The name of the expected object or enum
     * @return bool True if the value is an instance of the specified object or enum, false otherwise
     */
    private function isSpecificObject(mixed $value, string $object): bool
    {
        try {
            $actualObject = new \ReflectionClass($object);
            if ($actualObject->isEnum()) {
                return in_array($value, array_map(fn($case) => $case->value ?? $case->name, $object::cases()), true);
            }
            return $value instanceof $object;
        } catch (\ReflectionException) {
            return false;
        }
    }
}
