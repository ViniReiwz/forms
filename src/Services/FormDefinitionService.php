<?php

namespace Uspdev\Forms\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Uspdev\Forms\Enums\FormDefinitionStatus;
use Uspdev\Forms\Models\FormDefinition;

class FormDefinitionService
{
    public function __construct(protected FormDefinitionSchemaValidator $validator)
    {
    }

    public function createFromRequest(Request $request): FormDefinition
    {
        return FormDefinition::create($this->validatedPayloadFromRequest($request));
    }

    public function definitions(): Collection
    {
        return FormDefinition::all();
    }

    public function updateFromRequest(Request $request, FormDefinition $definition): FormDefinition
    {
        $definition->update($this->validatedPayloadFromRequest($request, $definition));

        return $definition;
    }

    public function delete(FormDefinition $definition): bool
    {
        return (bool) $definition->delete();
    }

    public function purgeTrashedSubmissions(FormDefinition $definition): int
    {
        return $definition->formSubmissions()->onlyTrashed()->forceDelete();
    }

    protected function validatedPayloadFromRequest(Request $request, ?FormDefinition $definition = null): array
    {
        $payload = [
            'name' => $request->input('name'),
            'version' => $request->integer('version', 1),
            'status' => $request->input('status', FormDefinitionStatus::Active->value),
            'group' => $request->input('group'),
            'description' => $request->input('description'),
            'fields' => json_decode($request->input('fields'), true),
        ];

        $this->validator->validate($payload, $definition?->id);

        return $payload;
    }
}
