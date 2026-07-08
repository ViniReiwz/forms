<?php

namespace Uspdev\Forms;

use Uspdev\Forms\Models\FormDefinition;
use Spatie\Activitylog\Models\Activity;

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
