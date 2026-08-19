<?php

namespace Uspdev\Forms\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Illuminate\Support\Str;
use Uspdev\Forms\Form;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;

class FormSubmissionService
{
    public function validateData(Request $request, FormDefinition $definition): array
    {
        $rules = Form::getValidationRules($definition);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = [];
        foreach ($definition->flattenFields() as $field) {
            if (($field['type'] ?? null) === 'file') {
                continue;
            }

            $fieldName = $field['name'] ?? null;
            if ($fieldName && $request->has($fieldName)) {
                $data[$fieldName] = $request->input($fieldName);
            }
        }

        return $data;
    }

    public function submit(Request $request, FormDefinition $definition): FormSubmission
    {
        $data = $this->validateData($request, $definition);

        $submission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'key' => $request->input('form_key', config('uspdev-forms.defaultKey')),
            'data' => [],
        ]);

        $submission->data = $this->mergeFiles($request, $submission, $data);
        $submission->save();

        return $submission;
    }

    public function update(Request $request, FormSubmission $submission): FormSubmission
    {
        $definition = $submission->formDefinition;

        if (!$definition) {
            throw new \InvalidArgumentException('A submissao nao possui definicao relacionada.');
        }

        $data = array_merge($submission->data ?? [], $this->validateData($request, $definition));
        $submission->data = $this->mergeFiles($request, $submission, $data);
        $submission->save();

        return $submission;
    }

    protected function mergeFiles(Request $request, FormSubmission $submission, array $data): array
    {
        if ($request->has('remover')) {
            foreach ((array) $request->input('remover') as $fieldName) {
                if (isset($submission->data[$fieldName]['stored_path'])) {
                    Storage::disk('local')->delete($submission->data[$fieldName]['stored_path']);
                    unset($data[$fieldName]);
                }
            }
        }

        if ($request->hasFile('file')) {
            foreach ($request->file('file') as $fieldName => $file) {
                if (isset($data[$fieldName]['stored_path'])) {
                    Storage::disk('local')->delete($data[$fieldName]['stored_path']);
                    unset($data[$fieldName]);
                }

                $fileHash = md5_file($file->path());
                $extension = $file->getClientOriginalExtension();
                $name = $file->getClientOriginalName();
                $originalName = Str::slug(pathinfo($name, PATHINFO_FILENAME)) . '.' . $extension;
                $storedName = 'id' . $submission->id . '-' . $fileHash . '.' . $extension;
                $path = $file->storeAs('formsubmissions/' . date('Y'), $storedName, 'local');

                if (!$path) {
                    throw new RuntimeException('Nao foi possivel salvar o arquivo enviado.');
                }

                $data[$fieldName] = [
                    'original_name' => $originalName,
                    'stored_path' => $path,
                    'content_hash' => $fileHash,
                ];
            }
        }

        return $data;
    }
}
