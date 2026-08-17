<?php

namespace Uspdev\Forms\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Uspdev\Forms\Enums\FormDefinitionStatus;

class FormDefinitionSchemaValidator
{
    protected const SUPPORTED_TYPES = [
        'checkbox',
        'date',
        'disciplina-usp',
        'email',
        'file',
        'hidden',
        'local-usp',
        'number',
        'patrimonio-usp',
        'pessoa-usp',
        'select',
        'separator',
        'textarea',
        'text',
        'time',
        'url',
    ];

    public function validate(array $definition, ?int $ignoreId = null): array
    {
        unset($definition['flat_fields']);
        $definition['fields'] = $definition['fields'] ?? null;
        $flatFields = $this->flattenFields($definition['fields']);
        $validationData = $definition;
        $validationData['flat_fields'] = $flatFields;

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('form_definitions', 'name')
                    ->where(fn ($query) => $query->where('version', $definition['version'] ?? null))
                    ->ignore($ignoreId),
            ],
            'version' => 'required|integer|min:1',
            'status' => ['required', 'string', Rule::enum(FormDefinitionStatus::class)],
            'group' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'fields' => 'required|array',
            'flat_fields' => 'required|array|min:1',
            'flat_fields.*.name' => 'nullable|string',
            'flat_fields.*.type' => 'required|string|in:' . implode(',', self::SUPPORTED_TYPES),
            'flat_fields.*.label' => 'nullable|string|max:255',
            'flat_fields.*.required' => 'sometimes|boolean',
            'flat_fields.*.validation_rule' => 'sometimes|nullable|string',
            'flat_fields.*.options' => 'sometimes|array',
            'flat_fields.*.width' => 'sometimes|integer|min:1|max:12',
            'flat_fields.*.accept' => 'sometimes|nullable|string',
        ];

        $messages = [
            'name.unique' => 'Já existe um formulário com este nome e esta versão.',
            'flat_fields.*.type.in' => 'Tipo de campo nao suportado.',
            'flat_fields.*.width.min' => 'A largura do campo deve estar entre 1 e 12.',
            'flat_fields.*.width.max' => 'A largura do campo deve estar entre 1 e 12.',
        ];

        $validator = Validator::make($validationData, $rules, $messages);

        $validator->after(function ($validator) use ($definition, $flatFields) {
            $this->validateRows($validator, $definition['fields']);
            $this->validateFieldNames($validator, $flatFields);
            $this->validateFieldSpecificRules($validator, $flatFields);
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $definition;
    }

    public function flattenFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $flattened = [];
        foreach ($fields as $field) {
            if (is_array($field) && array_is_list($field)) {
                foreach ($field as $nestedField) {
                    if (is_array($nestedField)) {
                        $flattened[] = $nestedField;
                    }
                }
                continue;
            }

            if (is_array($field)) {
                $flattened[] = $field;
            }
        }

        return $flattened;
    }

    protected function validateRows($validator, mixed $fields): void
    {
        if (!is_array($fields)) {
            return;
        }

        foreach ($fields as $rowIndex => $field) {
            if (is_array($field) && array_is_list($field)) {
                if ($field === []) {
                    $validator->errors()->add("fields.{$rowIndex}", 'A linha de campos nao pode estar vazia.');
                    continue;
                }

                foreach ($field as $fieldIndex => $nestedField) {
                    if (!is_array($nestedField) || array_is_list($nestedField)) {
                        $validator->errors()->add(
                            "fields.{$rowIndex}.{$fieldIndex}",
                            'Cada item de uma linha deve ser um campo.'
                        );
                    }
                }

                continue;
            }

            if (!is_array($field) || array_is_list($field)) {
                $validator->errors()->add("fields.{$rowIndex}", 'Cada item de fields deve ser um campo ou uma linha de campos.');
            }
        }
    }

    protected function validateFieldSpecificRules($validator, array $fields): void
    {
        foreach ($fields as $index => $field) {
            $type = $field['type'] ?? null;

            if ($type === 'select' && empty($field['options'])) {
                $validator->errors()->add("flat_fields.{$index}.options", 'Campos select precisam declarar options.');
            }

            if ($type === 'file' && isset($field['options'])) {
                $validator->errors()->add("flat_fields.{$index}.options", 'Campos file nao usam options.');
            }

            if (($field['name'] ?? null) === 'file') {
                $validator->errors()->add("flat_fields.{$index}.name", "O nome 'file' e reservado para uploads.");
            }
        }
    }

    protected function validateFieldNames($validator, array $fields): void
    {
        $names = [];

        foreach ($fields as $index => $field) {
            $type = $field['type'] ?? null;
            $name = $field['name'] ?? null;

            if ($type !== 'separator' && blank($name)) {
                $validator->errors()->add("flat_fields.{$index}.name", 'O nome de cada campo e obrigatorio.');
                continue;
            }

            if (blank($name)) {
                continue;
            }

            if (in_array($name, $names, true)) {
                $validator->errors()->add("flat_fields.{$index}.name", 'Os nomes dos campos devem ser unicos.');
                continue;
            }

            $names[] = $name;
        }
    }
}
