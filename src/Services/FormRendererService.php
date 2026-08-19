<?php

namespace Uspdev\Forms\Services;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Uspdev\Forms\Form;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;

class FormRendererService
{
    public function render(FormDefinition $definition, array $options = [], ?FormSubmission $submission = null): string {
        
        $form = new Form(array_merge($options, [
            'name' => $definition->name,
            'version' => $definition->version,
        ]));

        return $this->renderFromDefinition($form, $definition, $submission);
    }

    public function listingForm(FormDefinition $definition, ?User $user = null): Form
    {
        $form = new Form([
            'editable' => true,
            'name' => $definition->name,
            'version' => $definition->version,
            'action' => route('form-submissions.store', $definition->id),
        ]);

        $form->user = $user;
        $form->admin = Gate::allows('manager', $user);

        return $form;
    }

    protected function renderFromDefinition(Form $form, FormDefinition $definition, ?FormSubmission $submission = null): string
    {
        $form->definition = $definition;

        $fields = '';
        foreach ($definition->fields as $field) {
            if (array_is_list($field)) {
                if ($field[0]['type'] == 'separator') {
                    $fields .=
                        '<div class="d-flex align-items-center mt-5 mb-2">
                        <h6 class="text-secondary mr-2 ">
                            <strong>' . ($field[0]['label'] ?? '') . '</strong>
                        </h6>
                        <div class="flex-grow-1 border mb-2"></div>
                    </div>';
                }

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
                        $fields .= '<div class="' . $colClass . '">' . $this->renderField($f, $submission) . '</div>';
                    }
                }
                $fields .= '</div>';
            } else {
                if (isset($field['width']) && is_numeric($field['width'])) {
                    $width = (int) $field['width'];
                    if ($width >= 1 && $width <= 12) {
                        $fields .= '<div class="col-' . $width . '">' . $this->renderField($field, $submission) . '</div>';
                        continue;
                    }
                }

                $fields .= $this->renderField($field, $submission);
            }
        }

        if ($submission) {
            $form->btnLabel = 'Atualizar';
        }

        return view('uspdev-forms::partials.form', [
            'form' => $form,
            'fields' => $fields,
        ])->render();
    }

    protected function renderField(array $field, ?FormSubmission $submission): string
    {
        $types = ['textarea', 'select', 'checkbox', 'hidden', 'time', 'date', 'file', 'pessoa-usp', 'disciplina-usp', 'patrimonio-usp', 'local-usp'];

        $field = $this->addFieldGenParams($field);

        if (isset($submission->data[$field['name']])) {
            $field['old'] = $submission->data[$field['name']];
        }

        if (in_array($field['type'], $types)) {
            return view('uspdev-forms::partials.' . $field['type'], compact('field'))->render();
        }

        return view('uspdev-forms::partials.default', compact('field'))->render();
    }

    protected function addFieldGenParams(array $field): array
    {
        $field['bs'] = config('uspdev-forms.bootstrapVersion');
        $field['required'] = $field['required'] ?? false;
        $field['requiredLabel'] = $field['required'] ? ' <span class="text-danger">*</span>' : '';
        $field['formGroupClass'] = $field['bs'] == 5 ? 'mb-3' : 'form-group';
        $field['controlClass'] = 'form-control ' . (config('uspdev-forms.formSize') == 'small' ? ' form-control-sm ' : '');
        $field['id'] = 'uspdev-forms-' . $field['name'];
        $field['old'] = null;

        return $field;
    }
}
