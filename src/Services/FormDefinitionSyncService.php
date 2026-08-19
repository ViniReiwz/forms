<?php

namespace Uspdev\Forms\Services;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Uspdev\Forms\Enums\FormDefinitionStatus;
use Uspdev\Forms\Models\FormDefinition;

class FormDefinitionSyncService
{
    public function __construct(protected FormDefinitionSchemaValidator $validator)
    {
    }

    // Sincroniza as definições de formulários a partir dos arquivos JSON encontrados em um diretório.
    public function syncFromDirectory(string $directory): array
    {
        $resolvedDirectory = $this->resolveDirectory($directory);

        $result = [
            'total_files' => 0,
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'ignored' => 0,
            'messages' => [],
            'errors' => [],
        ];

        $files = $this->listJsonFiles($resolvedDirectory);
        $result['total_files'] = count($files);

        foreach ($files as $filePath) {
            try {
                $definition = $this->readDefinitionFromFile($filePath);
                $syncStatus = $this->syncDefinition($definition);

                $result['synced']++;
                $result[$syncStatus]++;
                if ($syncStatus === 'unchanged') {
                    $result['ignored']++;
                }
                $result['messages'][] = basename($filePath) . ' -> ' . $syncStatus;
        
            } catch (\Throwable $e) {
                $result['errors'][] = basename($filePath) . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    // Sincroniza uma definição de formulário no banco de dados. Retorna 'created', 'updated' ou 'unchanged' dependendo do resultado.
    public function syncDefinition(array $definition): string
    {
        $definition['status'] = $this->normalizeStatus($definition);

        $name = $definition['name'] ?? null;
        $version = $definition['version'] ?? null;
        $existent = ($name && $version)
            ? FormDefinition::where('name', $name)->where('version', $version)->first()
            : null;
        $definition = $this->validator->validate($definition, $existent?->id);

        if (!$existent) {
            FormDefinition::create($definition);
            return 'created';
        }
        
        $existent->fill($definition);

        // Verifica se houve alterações comparando os atributos atuais com os do banco de dados
        if ($existent->isDirty()) {
            $existent->save();
            return 'updated';
        }

        return 'unchanged';
    }

    public function syncFromFile(string $filePath): string
    {
        return $this->syncDefinition($this->readDefinitionFromFile($filePath));
    }

    // Lista os arquivos .json em um diretório, ignorando subdiretórios e arquivos que não sejam JSON. 
    // Retorna um array de caminhos completos dos arquivos JSON encontrados.
    protected function listJsonFiles(string $directory): array
    {
        $files = scandir($directory);

        if ($files === false) {
            throw new RuntimeException('Nao foi possivel ler o diretorio informado.');
        }

        $jsonFiles = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            // Verifica se é um arquivo regular e tem extensão .json
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
            if (!is_file($filePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($extension !== 'json') {
                continue;
            }

            $jsonFiles[] = $filePath;
        }
        sort($jsonFiles);
        return $jsonFiles;
    }

    // Lê o conteúdo de um arquivo JSON e decodifica para um array associativo
    protected function readDefinitionFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException('Nao foi possivel ler o arquivo.');
        }

        try {
            // O JSON_THROW_ON_ERROR faz com que json_decode lance uma JsonException em caso de erro
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('JSON invalido: ' . $e->getMessage());
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('A definicao deve ser um objeto JSON.');
        }

        return [
            'name' => $decoded['name'] ?? null,
            'version' => $decoded['version'] ?? null,
            'status' => $decoded['status'] ?? null,
            'group' => $decoded['group'] ?? null,
            'description' => $decoded['description'] ?? null,
            'fields' => $decoded['fields'] ?? null,
        ];
    }

    protected function normalizeStatus(array $definition): string
    {
        if (!empty($definition['status'])) {
            return $definition['status'] instanceof FormDefinitionStatus
                ? $definition['status']->value
                : (string) $definition['status'];
        }

        return FormDefinitionStatus::Active->value;
    }
    // Resolve um caminho de diretório, tratando caminhos relativos e verificando se o diretório existe. 
    // Retorna o caminho absoluto do diretório.
    protected function resolveDirectory(string $directory): string
    {
        if ($directory === '') {
            throw new InvalidArgumentException('Diretorio de formularios nao informado.');
        }

        $resolved = $directory;
        if (!str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $resolved = base_path($directory);
        }

        if (!is_dir($resolved)) {
            throw new InvalidArgumentException('Diretorio nao encontrado: ' . $resolved);
        }

        return $resolved;
    }
}
