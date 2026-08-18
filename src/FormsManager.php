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
    public function __construct(
        protected FormRendererService $renderer,
        protected FormSubmissionService $submissionsService,
        protected FormSubmissionFileService $files,
        protected FormDefinitionSyncService $definitionsSync
    ) {
    }

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

        $definition = $submission?->formDefinition;
        if ($submission && !$definition) {
            throw new InvalidArgumentException('A submissao nao possui definicao relacionada.');
        }

        return $this->renderer->render(
            $definition ?? $this->definition($name, $version),
            $options,
            $submission
        );
    }

    /**
     * Resolve uma definição por nome e versão, ou a versão ativa quando omitida.
     *
     * @throws InvalidArgumentException
     */
    public function definition(string $name, ?int $version = null): FormDefinition
    {
        if ($version === null) {
            return $this->activeDefinition($name);
        }

        $this->ensureValidVersion($version);

        $definition = FormDefinition::where('name', $name)
            ->where('version', $version)
            ->first();

        if (!$definition) {
            throw new InvalidArgumentException(
                "A definicao do formulario '{$name}' na versao {$version} nao foi encontrada."
            );
        }

        return $definition;
    }

    /**
     * Resolve a versão ativa de uma definição de formulário.
     *
     * @throws InvalidArgumentException
     */
    public function activeDefinition(string $name): FormDefinition
    {
        $definition = FormDefinition::where('name', $name)
            ->where('status', FormDefinitionStatus::Active->value)
            ->first();

        if (!$definition) {
            throw new InvalidArgumentException(
                "Nenhuma definicao ativa foi encontrada para o formulario '{$name}'."
            );
        }

        return $definition;
    }

    /**
     * Processa uma nova submissão a partir do Request recebido.
     * Retorna FormSubmission em caso de sucesso ou lança ValidationException
     * ou RuntimeException quando a submissão não puder ser salva.
     */
    public function submit(
        Request $request,
        ?string $name = null,
        ?int $version = null
    ): FormSubmission
    {
        return $this->submissionsService->submit(
            $request,
            $this->resolveDefinitionFromRequest($request, $name, $version)
        );
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

        return $this->submissionsService->update($request, $submission);
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
     * Valida os dados usando a definição informada ou resolvida pelo request,
     * sem criar ou atualizar uma submissão.
     */
    public function validate(Request $request, ?string $name = null, ?int $version = null): array
    {
        return $this->submissionsService->validateData(
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

        return FormSubmission::query()
            ->where('form_definition_id', $definition->id)
            ->when($key !== null, fn ($query) => $query->where('key', $key))
            ->get();
    }

    /**
     * Filtra submissões de um formulário por campo armazenado no JSON data.
     * Lança InvalidArgumentException quando a definição ou o operador forem
     * inválidos.
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
        return $this->files->download($this->resolveSubmission($submission), $fieldName);
    }

    /**
     * Remove logicamente uma submissão e registra a atividade.
     * Retorna a FormSubmission removida em caso de sucesso, false quando o delete
     * falhar, ou lança ModelNotFoundException para id inválido.
     */
    public function deleteSubmission(FormSubmission|int $submission, ?User $user = null): FormSubmission|false
    {
        return $this->files->deleteWithActivity($this->resolveSubmission($submission), $user);
    }

    /**
     * Retorna as activities mais recentes de uma submissão.
     */
    public function submissionActivities(FormSubmission|int $submission, int $take = 20): Collection
    {
        if ($take < 1) {
            throw new InvalidArgumentException('A quantidade de atividades deve ser um inteiro positivo.');
        }

        $submission = $submission instanceof FormSubmission
            ? $submission
            : FormSubmission::withTrashed()->findOrFail($submission);
        $subjectType = $submission->getMorphClass();

        return Activity::orderBy('created_at', 'DESC')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $submission->id)
            ->take($take)
            ->get();
    }

    /**
     * Retorna uma activity pelo id ou lança ModelNotFoundException.
     */
    public function activity(int $id): Activity
    {
        return Activity::query()
            ->where('subject_type', (new FormSubmission())->getMorphClass())
            ->findOrFail($id);
    }

    /**
     * Sincroniza definições de formulário a partir de um diretório.
     * Retorna o array de resumo do FormDefinitionSyncService com contadores,
     * mensagens e erros por arquivo.
     */
    public function syncFromDirectory(string $directory): array
    {
        return $this->definitionsSync->syncFromDirectory($directory);
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
        $definitionId = $this->definitionIdFromRequest($request);
        $requestName = $request->input('form_definition');

        if ($name !== null && $requestName !== null && $name !== $requestName) {
            throw new InvalidArgumentException(
                'Os identificadores da definicao informados no request nao correspondem.'
            );
        }

        $name = $name ?? $requestName ?? $request->input('name');
        $version = $this->versionFromRequest($request, $version);

        if ($name) {
            $definition = $this->definition((string) $name, $version);

            if ($definitionId !== null && $definitionId !== $definition->id) {
                throw new InvalidArgumentException(
                    'Os identificadores da definicao informados no request nao correspondem.'
                );
            }

            return $definition;
        }

        if ($definitionId !== null) {
            $definition = FormDefinition::find($definitionId);

            if (!$definition) {
                throw new InvalidArgumentException(
                    "A definicao de formulario com id {$definitionId} nao foi encontrada."
                );
            }

            return $definition;
        }

        throw new InvalidArgumentException('Definicao de formulario nao informada.');
    }

    protected function versionFromRequest(Request $request, ?int $version): ?int
    {
        $requestVersion = null;
        if ($request->filled('version')) {
            $requestVersion = filter_var($request->input('version'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($requestVersion === false) {
                throw new InvalidArgumentException('A versao da definicao deve ser um inteiro positivo.');
            }
        }

        if ($version !== null) {
            $this->ensureValidVersion($version);

            if ($requestVersion !== null && $requestVersion !== $version) {
                throw new InvalidArgumentException(
                    'Os identificadores da definicao informados no request nao correspondem.'
                );
            }

            return $version;
        }

        return $requestVersion;
    }

    protected function definitionIdFromRequest(Request $request): ?int
    {
        if (!$request->filled('form_definition_id')) {
            return null;
        }

        $definitionId = filter_var($request->input('form_definition_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($definitionId === false) {
            throw new InvalidArgumentException('O id da definicao deve ser um inteiro positivo.');
        }

        return $definitionId;
    }

    protected function ensureValidVersion(int $version): void
    {
        if ($version < 1) {
            throw new InvalidArgumentException('A versao da definicao deve ser um inteiro positivo.');
        }
    }
}
