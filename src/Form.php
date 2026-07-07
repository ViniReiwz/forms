<?php

namespace Uspdev\Forms;

use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;
use Spatie\Activitylog\Models\Activity;
use Uspdev\Forms\Enums\FormDefinitionStatus;

class Form
{
    /** Chave definida pelo usuário para esta instancia do form */
    public $key;

    public $group;
    public $btnLabel;
    public $btnSize;

    /** formDefinition object */
    public $definition;

    /** Metodo do form */
    public $method;

    /** corresponde ao campo action do formulário */
    public $action;

    /** Nome do formulario no BD*/
    public $name;

    /** Versao da definicao, quando informada */
    public $version;

    /** se true, pode ser editado. nesse caso precisa passar o id da submissão */
    public $editable; // bool

    public $user;

    public $admin;

    public function __construct($config = [])
    {
        $this->key = isset($config['key']) ? $config['key'] : config('uspdev-forms.defaultKey');
        $this->method = isset($config['method']) ? $config['method'] : config('uspdev-forms.defaultMethod');

        $this->group = config('uspdev-forms.defaultGroup');
        $this->btnLabel = config('uspdev-forms.defaultBtnLabel');
        $this->btnSize = config('uspdev-forms.formSize') == 'small' ? ' btn-sm ' : '';

        // nome do form definition
        $this->name = isset($config['name']) ? $config['name'] : null;
        $this->version = isset($config['version']) ? $config['version'] : null;

        $this->action = isset($config['action']) ? $config['action'] : null;
        $this->editable = isset($config['editable']) ? $config['editable'] : false;
    }

    /**
     * Retorna as regras de validação para os campos do form
     */
    public static function getValidationRules(FormDefinition $definition): array
    {
        $rules = [];

        foreach ($definition->fields as $field) {
            if (array_is_list($field)) {
                foreach ($field as $f) {
                    if ($f['type'] == 'file') {
                        $key = 'file.' . $f['name'];
                    } else {
                        $key = $f['name'];
                    }
                    $rules[$key] = self::getFieldValidationRule($f);
                }
            } else {
                if ($field['type'] == 'file') {
                    $key = 'file.' . $field['name'];
                } else {
                    $key = $field['name'];
                }
                $rules[$key] = self::getFieldValidationRule($field);
            }
        }
        return $rules;
    }

    /**
     * Return the validation rule for a field based on required or type
     */
    protected static function getFieldValidationRule($field)
    {
        $rule = !empty($field['required']) ? 'required' : 'nullable';
        $rule = !empty($field['validation_rule']) ? $rule . '|' . $field['validation_rule'] : $rule;
        $rule = self::normalizeRule($rule);

        $options = $field['options'] ?? [];
        $options = is_array($options) ? $options : [];
        $values = [];
        if (!empty($options)) {
            $values = array_map(function ($option) {
                return is_array($option) && isset($option['value']) ? $option['value'] : $option;
            }, $options);
        }

        $rulesMap = [
            'email' => 'email',
            'number' => 'numeric',
            'date' => 'date',
            'url' => 'url',
            'file' => 'file',
            'select' => 'in:' . implode(',', $values),
        ];

        if (isset($rulesMap[$field['type']])) {

            $rule .= '|' . $rulesMap[$field['type']];
        }

        return $rule;
    }

    /**
     * Normaliza uma string de regras de validação do Laravel.
     *
     * Remove regras duplicadas e elimina "nullable" quando
     * a regra "required" estiver presente.
     *
     * Exemplos:
     * - required|nullable|email => required|email
     * - nullable|email|email => nullable|email
     *
     * @param string $rule Regras separadas por pipe.
     * @return string Regras normalizadas.
     */
    protected static function normalizeRule(string $rule): string
    {
        $parts = collect(explode('|', $rule))->filter()->unique();

        if ($parts->contains('required')) {
            $parts = $parts->reject(fn($item) => $item === 'nullable');
        }

        return $parts->implode('|');
    }

    protected static function addFieldGenParams($field)
    {
        $field['bs'] = config('uspdev-forms.bootstrapVersion');
        $field['required'] = isset($field['required']) ? $field['required'] : false;
        $field['requiredLabel'] = $field['required'] ? ' <span class="text-danger">*</span>' : '';
        $field['formGroupClass'] = $field['bs'] == 5 ? 'mb-3' : 'form-group';
        $field['controlClass'] = 'form-control ' . (config('uspdev-forms.formSize') == 'small' ? ' form-control-sm ' : '');
        $field['id'] = 'uspdev-forms-' . $field['name'];

        $field['old'] = null;

        return $field;
    }

    public function generateHtmlFromDefinition(FormDefinition $definition, $formSubmission = null)
    {
        $this->definition = $definition;

        $fields = '';
        foreach ($this->definition->fields as $field) {
            $has_sep = false;

            if (array_is_list($field)) {

                // Verifica se há a necessidade de um separador entre esta linha e a anteriror
                if ($field[0]['type'] == 'separator') {
                    $fields .=
                        '<div class="d-flex align-items-center mt-5 mb-2">
                        <h6 class="text-secondary mr-2 ">
                            <strong>' . ($field[0]['label'] ?? '') . '</strong>
                        </h6>
                        <div class="flex-grow-1 border mb-2"></div>
                    </div>';
                }

                // agrupando campos na mesma linha: igual para bs4 e bs5
                $fields .= '<div class="row">';

                foreach ($field as $f) {
                    if ($f['type'] != 'separator') {
                        $colClass = 'col';
                        if (isset($f['width']) && is_numeric($f['width'])) {
                            $width = (int) $f['width'];
                            if ($width >= 1 && $width <= 12) {
                                $colClass = 'col-' . $width;
                            }
                        }
                        $fields .= '<div class="' . $colClass . '">' . $this->generateField($f, $formSubmission) . '</div>';
                    }
                }
                $fields .= '</div>';
            } else {
                // a linha possui um campo somente
                if (isset($field['width']) && is_numeric($field['width'])) {
                    $width = (int) $field['width'];
                    if ($width >= 1 && $width <= 12) {
                        $fields .= '<div class="col-' . $width . '">' . $this->generateField($field, $formSubmission) . '</div>';
                        continue;
                    }
                }

                $fields .= $this->generateField($field, $formSubmission);
            }
        }
        if ($formSubmission) {
            $this->btnLabel = 'Atualizar';
        }

        return view('uspdev-forms::partials.form', [
            'form' => $this,
            'fields' => $fields,
        ])->render();
    }

    /**
     * Generates fields for the form generator
     */
    protected function generateField($field, $formSubmission)
    {
        // tipos de entradas do form conhecidos
        $types = ['textarea', 'select', 'checkbox', 'hidden', 'time', 'date', 'file', 'pessoa-usp', 'disciplina-usp', 'patrimonio-usp', 'local-usp'];

        $field = Form::addFieldGenParams($field);

        if (isset($formSubmission->data[$field['name']])) {
            $field['old'] = $formSubmission->data[$field['name']];
        }

        // vamos escolher o template do input com base no 'type'
        if (in_array($field['type'], $types)) {
            $html = view('uspdev-forms::partials.' . $field['type'], compact('field'))->render();
        } else {
            $html = view('uspdev-forms::partials.default', compact('field'))->render();
        }

        return $html;
    }

    /**
     * List form submissions filtering by key and optionally by formName
     *
     * If there's no specific key, it lists all submissions
     */
    public function listSubmission($formName = null)
    {
        $cond = [];
        if ($this->key != config('uspdev-forms.defaultKey')) {
            $cond['key'] = $this->key;
        }

        if ($formName) {
            $cond['form_definition_id'] = $this->getDefinition($formName)->id;
        }

        return FormSubmission::where($cond)->get();
    }

    /**
     * Retorna as últimas 20 activities de uma submissão
     *
     * Retorna primeiro as mais recentes.
     * A quantidade pode ser personalizada pelo parâmeto $take
     *
     */
    public function getSubmissionActivities($id, $take = 20)
    {
        return Activity::orderBy('created_at', 'DESC')->where('subject_id', $id)->take($take)->get();
    }

    /**
     * Returns form definition by form name
     */
    public function getDefinition($formName = null)
    {
        $name = $formName ?? $this->name;

        if ($this->version) {
            return FormDefinition::where('name', $name)
                ->where('version', $this->version)
                ->first();
        }

        return FormDefinition::where('name', $name)
            ->where('status', FormDefinitionStatus::Active->value)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Retorna informações detalhadas de uma activity de submissão, incluindo os dados do formulário no momento da atividade.
     *
     * @param int $id ID da atividade a ser detalhada
     * @return \Spatie\Activitylog\Models\Activity
     */
    public function detailActivity($id)
    {
        return Activity::findOrFail($id);
    }
}
