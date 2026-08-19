<?php

namespace Uspdev\Forms\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Uspdev\Forms\Models\FormSubmission;

class FormSubmissionFileService
{
    public function download(FormSubmission $submission, string $fieldName): BinaryFileResponse
    {
        $path = $submission->data[$fieldName]['stored_path'] ?? null;

        if (!$path || !Storage::disk('local')->exists($path)) {
            return abort(404, 'Arquivo não encontrado');
        }

        $downloadName = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '-', basename($path));
        $downloadName = $submission->data[$fieldName]['original_name'] ?? $downloadName;

        return response()->download(Storage::disk('local')->path($path), null, [
            'Content-Type' => Storage::disk('local')->mimeType($path),
        ])->setContentDisposition('attachment', $downloadName);
    }

    public function deleteWithActivity(FormSubmission $submission, ?User $user = null): FormSubmission|false
    {
        $user = $user ?? Auth::user();
        $deletedSubmission = $submission;

        if ($submission->delete()) {
            activity()->performedOn($deletedSubmission)->causedBy($user)->log('Chave excluída');
            return $deletedSubmission;
        }

        return false;
    }
}
