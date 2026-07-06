<?php

namespace Uspdev\Forms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Uspdev\Forms\Facades\Forms;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;
use Uspdev\Forms\Services\FormRendererService;

class SubmissionController extends Controller
{

    public function __construct()
    {
        $this->middleware('can:' . config('uspdev-forms.adminGate'));
    }

    public function index(FormDefinition $formDefinition)
    {
        \UspTheme::activeUrl(route('form-definitions.index'));

        $form = app(FormRendererService::class)->listingForm($formDefinition, Auth::user());

        return view('uspdev-forms::submission.index', compact('form', 'formDefinition'));
    }

    public function create(FormDefinition $formDefinition)
    {
        \UspTheme::activeUrl(route('form-definitions.index'));

        $config = [
            'key' => null,
            'action' => route('form-submissions.store', $formDefinition),
        ];
        $formHtml = Forms::render($formDefinition->name, $formDefinition->version, $config);

        return view('uspdev-forms::submission.edit', [
            'definition' => $formDefinition,
            'submission' => null,
            'formHtml' => $formHtml,
        ]);
    }

    public static function edit(FormDefinition $formDefinition, FormSubmission $formSubmission)
    {
        \UspTheme::activeUrl(route('form-definitions.index'));

        $formHtml = Forms::render($formDefinition->name, ['method' => 'PUT'], $formSubmission);

        return view('uspdev-forms::submission.edit')->with([
            'formHtml' => $formHtml,
            'submission' => $formSubmission,
            'definition' => $formDefinition,
        ]);
    }

    public function store(FormDefinition $formDefinition, Request $request)
    {
        $submission = self::processSubmission(fn () => Forms::submit($request));

        if ($submission instanceof FormSubmission) {
            return redirect()->route('form-submissions.index', $formDefinition)
                ->with('alert-success', 'Submissão criada com sucesso!');
        }

        if (is_array($submission)) {
            $message = '';
            $errors = $submission['errors'];
            foreach ($errors->getMessages() as $campo => $mensagens) {
                $message .= $campo . ' - ' . $mensagens[0] . "\n";
            }
        } else {
            $message = e($submission);
        }

        return redirect()->back()->withInput()
            ->with('alert-danger', 'Erro: ' . $message);
    }

    public static function update(Request $request, FormDefinition $formDefinition, FormSubmission $formSubmission)
    {
        $submission = self::processSubmission(fn () => Forms::update($request, $formSubmission));

        if ($submission instanceof FormSubmission) {
            return redirect(route('form-submissions.index', $formDefinition))
                ->with('alert-success', 'Submissão atualizada com sucesso!');
        }

        if (is_array($submission)) {
            $message = '';
            $errors = $submission['errors'];
            foreach ($errors->getMessages() as $campo => $mensagens) {
                $message .= $campo . ' - ' . $mensagens[0] . "\n";
            }
        } else {
            $message = e($submission);
        }

        return redirect()->back()->withInput()
            ->with('alert-danger', 'Erro: ' . $message);
    }

    public static function destroy(FormDefinition $formDefinition, FormSubmission $formSubmission)
    {
        Forms::deleteSubmission($formSubmission, Auth::user());

        return redirect(route('form-submissions.index', $formDefinition))
            ->with('alert-success', 'Submissão enviada para lixeira com sucesso!');
    }

    public function downloadFile($formDefinition, FormSubmission $formSubmission, $fieldName)
    {
        return Forms::downloadFile($formSubmission, $fieldName);
    }

    protected static function processSubmission(callable $callback): FormSubmission|array|string
    {
        try {
            return $callback();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return [
                'status' => 'error',
                'errors' => $exception->validator->errors(),
            ];
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }
}
