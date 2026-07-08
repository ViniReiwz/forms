<?php

namespace Uspdev\Forms\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Uspdev\Forms\Enums\FormDefinitionStatus;
use Uspdev\Forms\Models\FormSubmission;
use Uspdev\Forms\Services\FormDefinitionSchemaValidator;
use Uspdev\Forms\Services\FormRendererService;
use Uspdev\Forms\Services\FormSubmissionService;

class FormDefinition extends Model
{
    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast. (Laravel 11 style)
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'version' => 'integer',
            'status' => FormDefinitionStatus::class,
        ];
    }

    /**
     * Sobrescreve o método boot do Eloquent Model.
     *
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->version = $model->version ?: 1;
            if (!$model->status) {
                $model->status = FormDefinitionStatus::Active;
            }

            app(FormDefinitionSchemaValidator::class)->validate([
                'name' => $model->name,
                'version' => $model->version,
                'status' => $model->status instanceof FormDefinitionStatus
                    ? $model->status->value
                    : $model->status,
                'group' => $model->group,
                'description' => $model->description,
                'fields' => $model->fields,
            ], $model->id);

            if ($model->status !== FormDefinitionStatus::Active) {
                return;
            }

            static::where('name', $model->name)
                ->when($model->exists, fn ($query) => $query->where('id', '!=', $model->id))
                ->where('status', FormDefinitionStatus::Active->value)
                ->update(['status' => FormDefinitionStatus::Disabled->value]);
        });
    }

    /**
     * Retorna fields mas achatado - sem subarrays
     */
    public function flattenFields()
    {
        $ret = [];
        foreach ($this->fields as $field) {
            if (array_is_list($field)) {
                foreach ($field as $f) {
                    $ret[] = $f;
                }
            } else {
                $ret[] = $field;
            }
        }
        return $ret;
    }

    /**
     * Filtro para buscar por campos específicos dentro do JSON de fields.
     */
    public function scopeFilter($query, string $key, mixed $value)
    {
        return $query->whereJsonContains("fields->{$key}", $value);
    }


    /**
     * Relacionamento com FormSubmission
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function render(array $options = [], ?FormSubmission $submission = null): string
    {
        return app(FormRendererService::class)->render($this, $options, $submission);
    }

    public function validateData(Request $request): array
    {
        return app(FormSubmissionService::class)->validateData($request, $this);
    }

    public function submit(Request $request): FormSubmission
    {
        return app(FormSubmissionService::class)->submit($request, $this);
    }
}
