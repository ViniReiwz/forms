<?php

namespace Uspdev\Forms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Uspdev\Forms\Models\FormDefinition definition(string $name, ?int $version = null)
 * @method static \Uspdev\Forms\Models\FormDefinition activeDefinition(string $name)
 * @method static \Illuminate\Support\Collection definitions(?string $group = null)
 * @method static string render(string $name, int|array|null $versionOrOptions = null, array|\Uspdev\Forms\Models\FormSubmission $options = [], ?\Uspdev\Forms\Models\FormSubmission $submission = null)
 * @method static \Uspdev\Forms\Models\FormSubmission submit(\Illuminate\Http\Request $request, ?string $name = null, ?int $version = null)
 * @method static \Uspdev\Forms\Models\FormSubmission update(\Illuminate\Http\Request $request, \Uspdev\Forms\Models\FormSubmission|int $submission)
 * @method static array validate(\Illuminate\Http\Request $request, ?string $name = null, ?int $version = null)
 * @method static \Uspdev\Forms\Models\FormSubmission|null submission(int $id)
 * @method static \Illuminate\Support\Collection submissions(string $name, ?int $version = null, ?string $key = null)
 * @method static \Illuminate\Support\Collection filterSubmissions(string $name, int|string|null $version = null, ?string $field = null, ?string $operator = null, mixed $value = null, ?string $key = null)
 * @method static \Symfony\Component\HttpFoundation\BinaryFileResponse downloadFile(\Uspdev\Forms\Models\FormSubmission|int $submission, string $fieldName)
 * @method static \Uspdev\Forms\Models\FormSubmission|false deleteSubmission(\Uspdev\Forms\Models\FormSubmission|int $submission, ?\App\Models\User $user = null)
 * @method static \Illuminate\Support\Collection submissionActivities(\Uspdev\Forms\Models\FormSubmission|int $submission, int $take = 20)
 * @method static \Spatie\Activitylog\Models\Activity activity(int $id)
 * @method static array syncFromDirectory(string $directory)
 */
class Forms extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'forms';
    }
}
