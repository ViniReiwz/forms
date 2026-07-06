<?php

namespace Uspdev\Forms\Services;

use App\Models\User;
use InvalidArgumentException;
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

        $html = $form->generateHtmlFromDefinition($definition, $submission);

        if ($html === null) {
            throw new InvalidArgumentException("Form definition '{$definition->name}' nao encontrada.");
        }

        return $html;
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
}
