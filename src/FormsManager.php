<?php

/**
 * FormsManager coordena a API de alto nível da biblioteca de formulários.
 *
 * Ele resolve definições e submissões e delega operações especializadas aos
 * serviços internos. A facade Forms é a entrada pública recomendada para
 * consumidores externos; este manager é o serviço registrado no container.
 */
namespace Uspdev\Forms;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Uspdev\Forms\Enums\FormDefinitionStatus;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;
use Uspdev\Forms\Services\FormDefinitionSyncService;
use Uspdev\Forms\Services\FormRendererService;
use Uspdev\Forms\Services\FormSubmissionFileService;
use Uspdev\Forms\Services\FormSubmissionService;

class FormsManager
{
    /**
     * Renderiza o HTML de uma definição de formulário pelo nome.
     * Retorna a string HTML renderizada ou lança InvalidArgumentException
     * quando a definição informada não existir.
     */
    public function render(
        string $name,
        int|array|null $versionOrOptions = null,
        array|FormSubmission $options = [],
        ?FormSubmission $submission = null
    ): string {
        if (is_array($versionOrOptions)) {
            if ($options instanceof FormSubmission) {
                $submission = $options;
            }
            $options = $versionOrOptions;
            $version = null;
        } else {
            $version = $versionOrOptions;
            if ($options instanceof FormSubmission) {
                $submission = $options;
                $options = [];
            }
        }

        $definition = $submission?->formDefinition ?? $this->definition($name, $version);
        if (!$definition) {
            throw new InvalidArgumentException("Form definition '{$name}' nao encontrada.");
        }

        return app(FormRendererService::class)->render($definition, $options, $submission);
    }

    /**
     * Busca uma definição de formulário por nome e versão.
     */
    public function definition(string $name, ?int $version = null): ?FormDefinition
    {
        return $version === null
            ? $this->activeDefinition($name)
            : FormDefinition::where('name', $name)->where('version', $version)->first();
    }

    /**
     * Busca a versão ativa de uma definição de formulário.
     */
    public function activeDefinition(string $name): ?FormDefinition
    {
        return FormDefinition::where('name', $name)
            ->where('status', FormDefinitionStatus::Active->value)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Processa uma nova submissão a partir do Request recebido.
     * Retorna FormSubmission em caso de sucesso ou lança ValidationException
     * ou RuntimeException quando a submissão não puder ser salva.
     */
    public function submit(Request $request): FormSubmission
    {
        $definition = $this->resolveDefinitionFromRequest($request);
        return app(FormSubmissionService::class)->submit($request, $definition);
    }

    /**
     * Atualiza uma submissão existente, recebida como model ou id.
     * Retorna a FormSubmission atualizada ou lança ModelNotFoundException,
     * ValidationException ou RuntimeException em caso de falha.
     */
    public function update(Request $request, FormSubmission|int $submission): FormSubmission
    {
        $submission = $this->resolveSubmission($submission);
        $definition = $submission->formDefinition;

        if (!$definition) {
            throw new InvalidArgumentException('A submissao nao possui definicao relacionada.');
        }

        return app(FormSubmissionService::class)->update($request, $submission);
    }

    /**
     * Lista definições de formulário, opcionalmente filtradas por grupo.
     * Retorna uma Collection de FormDefinition; quando não houver registros,
     * retorna uma Collection vazia.
     */
    public function definitions(?string $group = null): Collection
    {
        return FormDefinition::query()
            ->when($group, fn ($query) => $query->where('group', $group))
            ->get();
    }

    /**
     * Busca uma submissão pelo id.
     * Retorna FormSubmission quando encontrada ou null quando o id não existir.
     */
    public function submission(int $id): ?FormSubmission
    {
        return FormSubmission::find($id);
    }

    /**
     * Lista submissões, opcionalmente filtradas por nome de formulário e chave.
     * Retorna uma Collection de FormSubmission; se o formulário informado não
     * existir, retorna uma Collection vazia.
     */
    public function validate(Request $request, ?string $name = null, ?int $version = null): array
    {
        return app(FormSubmissionService::class)->validateData(
            $request,
            $this->resolveDefinitionFromRequest($request, $name, $version)
        );
    }

    /**
     * Lista submissões, filtradas pela definição resolvida e chave opcional.
     */
    public function submissions(string $name, ?int $version = null, ?string $key = null): Collection
    {
        $definition = $this->definition($name, $version);

        if (!$definition) {
            return collect();
        }

        return FormSubmission::query()
            ->where('form_definition_id', $definition->id)
            ->when($key !== null, fn ($query) => $query->where('key', $key))
            ->get();
    }

    /**
     * Filtra submissões de um formulário por campo armazenado no JSON data.
     * Retorna uma Collection com os resultados, uma Collection vazia se a
     * definição não existir, ou lança InvalidArgumentException para operador inválido.
     */
    public function filterSubmissions(
        string $name,
        int|string|null $version = null,
        ?string $field = null,
        ?string $operator = null,
        mixed $value = null,
        ?string $key = null
    ): Collection {
        if (is_string($version)) {
            $field = $version;
            $version = null;
        }

        if (!$field || !$operator) {
            throw new InvalidArgumentException('Campo e operador sao obrigatorios para filtrar submissoes.');
        }

        $definition = $this->definition($name, $version);

        if (!$definition) {
            return collect();
        }

        $jsonField = "data->{$field}";
        $query = FormSubmission::query()
            ->where('form_definition_id', $definition->id)
            ->when($key !== null, fn ($query) => $query->where('key', $key));

        return match ($operator) {
            'contains' => $query->whereJsonContains($jsonField, (string) $value)->get(),
            '==' => $query->where($jsonField, $value)->get(),
            '!=' => $query->where($jsonField, '!=', $value)->get(),
            'empty' => $query->where(function ($query) use ($jsonField) {
                $query->whereNull($jsonField)->orWhere($jsonField, '');
            })->get(),
            'not_empty' => $query->where(function ($query) use ($jsonField) {
                $query->whereNotNull($jsonField)->where($jsonField, '!=', '');
            })->get(),
            default => throw new InvalidArgumentException(
                sprintf("Operador '%s' nao suportado.", $operator)
            ),
        };
    }

    /**
     * Baixa um arquivo associado a uma submissão e campo de upload.
     * Retorna BinaryFileResponse em caso de sucesso; pode lançar ModelNotFoundException
     * para id inválido ou abortar com 404 quando o arquivo não existir no storage.
     */
    public function downloadFile(FormSubmission|int $submission, string $fieldName): BinaryFileResponse
    {
        return app(FormSubmissionFileService::class)->download($this->resolveSubmission($submission), $fieldName);
    }

    /**
     * Remove logicamente uma submissão e registra a atividade.
     * Retorna a FormSubmission removida em caso de sucesso, false quando o delete
     * falhar, ou lança ModelNotFoundException para id inválido.
     */
    public function deleteSubmission(FormSubmission|int $submission, ?User $user = null): FormSubmission|false
    {
        return app(FormSubmissionFileService::class)->deleteWithActivity($this->resolveSubmission($submission), $user);
    }

    /**
     * Retorna as activities mais recentes de uma submissão.
     */
    public function submissionActivities(FormSubmission|int $submission, int $take = 20): Collection
    {
        $submissionId = $submission instanceof FormSubmission ? $submission->id : $submission;

        return Activity::orderBy('created_at', 'DESC')
            ->where('subject_id', $submissionId)
            ->take($take)
            ->get();
    }

    /**
     * Retorna uma activity pelo id ou lança ModelNotFoundException.
     */
    public function activity(int $id): Activity
    {
        return Activity::findOrFail($id);
    }

    /**
     * Sincroniza definições de formulário a partir de um diretório.
     * Retorna o array de resumo do FormDefinitionSyncService com contadores,
     * mensagens e erros por arquivo.
     */
    public function syncFromDirectory(string $directory): array
    {
        return app(FormDefinitionSyncService::class)->syncFromDirectory($directory);
    }

    /**
     * Normaliza uma submissão recebida como model ou id.
     * Retorna FormSubmission quando encontrada ou lança ModelNotFoundException
     * quando o id informado não existir.
     */
    protected function resolveSubmission(FormSubmission|int $submission): FormSubmission
    {
        if ($submission instanceof FormSubmission) {
            return $submission;
        }

        return FormSubmission::findOrFail($submission);
    }

    protected function resolveDefinitionFromRequest(
        Request $request,
        ?string $name = null,
        ?int $version = null
    ): FormDefinition {
        if ($request->filled('form_definition_id')) {
            return FormDefinition::findOrFail((int) $request->input('form_definition_id'));
        }

        $name = $name ?? $request->input('form_definition') ?? $request->input('name');
        $version = $version ?? ($request->filled('version') ? (int) $request->input('version') : null);

        if (!$name) {
            throw new InvalidArgumentException('Definicao de formulario nao informada.');
        }

        $definition = $this->definition($name, $version);

        if (!$definition) {
            throw new InvalidArgumentException("Form definition '{$name}' nao encontrada.");
        }

        return $definition;
    }

}
