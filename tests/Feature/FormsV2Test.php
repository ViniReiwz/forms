<?php

namespace Uspdev\Forms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Uspdev\Forms\Facades\Forms;
use Uspdev\Forms\Enums\FormDefinitionStatus;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;
use Uspdev\Forms\Services\FormDefinitionBackupService;
use Uspdev\Forms\Services\FormDefinitionService;
use Uspdev\Forms\Services\FormDefinitionSyncService;
use Uspdev\Forms\Tests\TestCase;

class FormsV2Test extends TestCase
{
    public function test_versions_share_name_and_only_one_is_active(): void
    {
        $v1 = $this->definition(version: 1, active: true);
        $v2 = $this->definition(version: 2, active: true);

        $this->assertSame(FormDefinitionStatus::Disabled, $v1->fresh()->status);
        $this->assertSame(FormDefinitionStatus::Active, $v2->fresh()->status);
        $this->assertSame(2, Forms::definition('parecer_final')->version);
        $this->assertSame(1, Forms::definition('parecer_final', 1)->version);
    }

    public function test_name_and_version_are_unique_together(): void
    {
        $this->definition(version: 1);
        $this->expectException(ValidationException::class);

        $this->definition(version: 1);
    }

    public function test_render_uses_submission_definition_for_editing(): void
    {
        $v1 = $this->definition(version: 1, active: false, fields: [
            ['name' => 'resultado_v1', 'type' => 'text', 'label' => 'Resultado V1'],
        ]);
        $this->definition(version: 2, active: true, fields: [
            ['name' => 'resultado_v2', 'type' => 'text', 'label' => 'Resultado V2'],
        ]);
        $submission = FormSubmission::create([
            'form_definition_id' => $v1->id,
            'key' => 'workflow-123',
            'data' => ['resultado_v1' => 'ok'],
        ]);

        $html = Forms::render('parecer_final', ['method' => 'PUT'], $submission);

        $this->assertStringContainsString('resultado_v1', $html);
        $this->assertStringNotContainsString('resultado_v2', $html);
    }

    public function test_validate_returns_data_without_persisting(): void
    {
        $this->definition();
        $request = Request::create('/', 'POST', [
            'form_definition' => 'parecer_final',
            'resultado' => 'aprovado',
        ]);

        $validated = Forms::validate($request);

        $this->assertSame(['resultado' => 'aprovado'], $validated);
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_submit_creates_submission_and_update_uses_original_definition(): void
    {
        $v1 = $this->definition(version: 1, active: false, fields: [
            ['name' => 'resultado_v1', 'type' => 'text', 'label' => 'Resultado V1', 'required' => true],
        ]);
        $this->definition(version: 2, active: true, fields: [
            ['name' => 'resultado_v2', 'type' => 'text', 'label' => 'Resultado V2', 'required' => true],
        ]);

        $submission = Forms::submit(Request::create('/', 'POST', [
            'form_definition' => 'parecer_final',
            'version' => 1,
            'form_key' => 'workflow-123',
            'resultado_v1' => 'aprovado',
        ]));

        $this->assertSame($v1->id, $submission->form_definition_id);
        $this->assertSame('aprovado', $submission->data['resultado_v1']);

        $this->expectException(ValidationException::class);
        Forms::update(Request::create('/', 'POST', ['resultado_v2' => 'novo']), $submission);
    }

    public function test_submission_queries_respect_version_and_filter_operators(): void
    {
        $v1 = $this->definition(version: 1, active: false);
        $v2 = $this->definition(version: 2, active: true);
        FormSubmission::create(['form_definition_id' => $v1->id, 'key' => 'k', 'data' => ['resultado' => 'antigo']]);
        FormSubmission::create(['form_definition_id' => $v2->id, 'key' => 'k', 'data' => ['resultado' => 'novo']]);

        $this->assertCount(1, Forms::submissions('parecer_final'));
        $this->assertSame('novo', Forms::filterSubmissions('parecer_final', field: 'resultado', operator: '==', value: 'novo')->first()->data['resultado']);

        $this->expectException(\InvalidArgumentException::class);
        Forms::filterSubmissions('parecer_final', field: 'resultado', operator: '=', value: 'novo');
    }

    public function test_sync_uses_name_and_version_and_switches_active_definition(): void
    {
        $directory = sys_get_temp_dir() . '/uspdev_forms_test_' . uniqid();
        mkdir($directory);
        file_put_contents($directory . '/v1.json', json_encode($this->definitionPayload(version: 1, active: true)));
        file_put_contents($directory . '/v2.json', json_encode($this->definitionPayload(version: 2, active: true)));

        $result = app(FormDefinitionSyncService::class)->syncFromDirectory($directory);

        $this->assertSame(2, $result['created']);
        $this->assertSame(
            FormDefinitionStatus::Disabled,
            FormDefinition::where('name', 'parecer_final')->where('version', 1)->first()->status
        );
        $this->assertSame(
            FormDefinitionStatus::Active,
            FormDefinition::where('name', 'parecer_final')->where('version', 2)->first()->status
        );
    }

    public function test_definition_direct_methods_are_equivalent_to_facade_methods(): void
    {
        $definition = $this->definition();
        $request = Request::create('/', 'POST', [
            'resultado' => 'aprovado',
            'form_key' => 'workflow-123',
        ]);

        $facadeHtml = Forms::render('parecer_final', 1, ['method' => 'POST']);
        $modelHtml = $definition->render(['method' => 'POST']);

        $this->assertSame($facadeHtml, $modelHtml);
        $this->assertSame(
            Forms::validate($request, 'parecer_final', 1),
            $definition->validateData($request)
        );

        $submission = $definition->submit($request);

        $this->assertInstanceOf(FormSubmission::class, $submission);
        $this->assertSame($definition->id, $submission->form_definition_id);
        $this->assertSame('aprovado', $submission->data['resultado']);
    }

    public function test_submission_direct_update_is_equivalent_to_facade_update(): void
    {
        $definition = $this->definition();
        $facadeSubmission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-123',
            'data' => ['resultado' => 'rascunho'],
        ]);
        $modelSubmission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-456',
            'data' => ['resultado' => 'rascunho'],
        ]);
        $request = Request::create('/', 'POST', ['resultado' => 'aprovado']);

        $updatedByFacade = Forms::update($request, $facadeSubmission);
        $updatedByModel = $modelSubmission->updateFromRequest($request);

        $this->assertSame($updatedByFacade->data['resultado'], $updatedByModel->data['resultado']);
    }

    public function test_submission_direct_download_is_equivalent_to_facade_download(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('formsubmissions/2026/documento.txt', 'conteudo');

        $definition = $this->definition(fields: [
            ['name' => 'arquivo', 'type' => 'file', 'label' => 'Arquivo'],
        ]);
        $submission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-123',
            'data' => [
                'arquivo' => [
                    'original_name' => 'documento.txt',
                    'stored_path' => 'formsubmissions/2026/documento.txt',
                    'content_hash' => 'hash',
                ],
            ],
        ]);

        $facadeResponse = Forms::downloadFile($submission, 'arquivo');
        $modelResponse = $submission->download('arquivo');

        $this->assertSame($facadeResponse->getFile()->getPathname(), $modelResponse->getFile()->getPathname());
        $this->assertSame(
            $facadeResponse->headers->get('content-disposition'),
            $modelResponse->headers->get('content-disposition')
        );
    }

    public function test_submission_direct_delete_is_equivalent_to_facade_delete(): void
    {
        $definition = $this->definition();
        $facadeSubmission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-123',
            'data' => ['resultado' => 'aprovado'],
        ]);
        $modelSubmission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-456',
            'data' => ['resultado' => 'aprovado'],
        ]);

        $deletedByFacade = Forms::deleteSubmission($facadeSubmission);
        $deletedByModel = $modelSubmission->deleteWithActivity();

        $this->assertInstanceOf(FormSubmission::class, $deletedByFacade);
        $this->assertInstanceOf(FormSubmission::class, $deletedByModel);
        $this->assertSoftDeleted('form_submissions', ['id' => $facadeSubmission->id]);
        $this->assertSoftDeleted('form_submissions', ['id' => $modelSubmission->id]);
    }

    public function test_facade_exposes_submission_activities_and_activity_detail(): void
    {
        $definition = $this->definition();
        $submission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-123',
            'data' => ['resultado' => 'aprovado'],
        ]);
        $otherSubmission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-456',
            'data' => ['resultado' => 'reprovado'],
        ]);

        Activity::query()->update([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $older = Activity::create([
            'log_name' => 'default',
            'description' => 'mais antiga',
            'subject_type' => FormSubmission::class,
            'subject_id' => $submission->id,
            'properties' => [],
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $newer = Activity::create([
            'log_name' => 'default',
            'description' => 'mais recente',
            'subject_type' => FormSubmission::class,
            'subject_id' => $submission->id,
            'properties' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Activity::create([
            'log_name' => 'default',
            'description' => 'outra submissao',
            'subject_type' => FormSubmission::class,
            'subject_id' => $otherSubmission->id,
            'properties' => [],
        ]);

        $activities = Forms::submissionActivities($submission, 2);

        $this->assertCount(2, $activities);
        $this->assertSame([$newer->id, $older->id], $activities->pluck('id')->all());
        $this->assertCount(2, Forms::submissionActivities($submission->id, 2));
        $this->assertCount(0, Forms::submissionActivities(999999, 2));
        $this->assertSame('mais recente', Forms::activity($newer->id)->description);
    }

    public function test_definition_service_creates_updates_and_purges_trashed_submissions(): void
    {
        $service = app(FormDefinitionService::class);
        $definition = $service->createFromRequest(Request::create('/', 'POST', [
            'name' => 'service_form',
            'version' => 1,
            'status' => FormDefinitionStatus::Draft->value,
            'group' => 'workflow',
            'description' => 'Rascunho',
            'fields' => json_encode([
                ['name' => 'resultado', 'type' => 'text', 'label' => 'Resultado'],
            ]),
        ]));
        $submission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'key' => 'workflow-123',
            'data' => ['resultado' => 'rascunho'],
        ]);

        $updated = $service->updateFromRequest(Request::create('/', 'POST', [
            'name' => 'service_form',
            'version' => 1,
            'status' => FormDefinitionStatus::Active->value,
            'group' => 'workflow',
            'description' => 'Ativo',
            'fields' => json_encode([
                ['name' => 'resultado', 'type' => 'text', 'label' => 'Resultado'],
            ]),
        ]), $definition);
        $submission->delete();

        $this->assertSame(FormDefinitionStatus::Active, $updated->fresh()->status);
        $this->assertSame(1, $service->purgeTrashedSubmissions($definition));
        $this->assertSame(0, FormSubmission::withTrashed()->where('id', $submission->id)->count());
    }

    public function test_definition_backup_service_generates_lists_and_removes_backups(): void
    {
        $directory = sys_get_temp_dir() . '/uspdev_forms_backups_' . uniqid();
        config(['uspdev-forms.forms_storage_dir' => $directory]);
        $definition = $this->definition();
        $service = app(FormDefinitionBackupService::class);

        $path = $service->backup($definition);
        $backups = $service->list($definition);

        $this->assertFileExists($path);
        $this->assertCount(1, $backups);
        $this->assertSame(1, $service->removeForDefinition($definition));
        $this->assertSame([], $service->list($definition));
    }

    protected function definition(int $version = 1, bool $active = true, ?array $fields = null): FormDefinition
    {
        return FormDefinition::create($this->definitionPayload($version, $active, $fields));
    }

    protected function definitionPayload(int $version = 1, bool $active = true, ?array $fields = null): array
    {
        return [
            'name' => 'parecer_final',
            'version' => $version,
            'status' => $active ? FormDefinitionStatus::Active->value : FormDefinitionStatus::Disabled->value,
            'group' => 'workflow',
            'description' => 'Parecer final',
            'fields' => $fields ?? [
                ['name' => 'resultado', 'type' => 'text', 'label' => 'Resultado', 'required' => true],
            ],
        ];
    }
}
