<?php

namespace Uspdev\Forms\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Uspdev\Forms\Models\FormDefinition;

class FormDefinitionBackupService
{
    public function backup(FormDefinition $definition): string
    {
        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory, 0777, true);

        $path = $directory . DIRECTORY_SEPARATOR . $definition->name . '@' . now()->format('d-m-Y_H:i:s') . '.json';
        $written = File::put($path, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($written === false) {
            throw new RuntimeException('Nao foi possivel gerar o backup da definicao.');
        }

        return $path;
    }

    public function backupAll(): int
    {
        $count = 0;

        foreach (FormDefinition::all() as $definition) {
            $this->backup($definition);
            $count++;
        }

        return $count;
    }

    public function list(FormDefinition $definition): array
    {
        $directory = $this->backupDirectory();
        if (!is_dir($directory)) {
            return [];
        }

        $files = array_filter(scandir($directory) ?: [], fn ($filename) => str_contains($filename, $definition->name));
        $backups = [];

        foreach ($files as $filename) {
            if (!str_contains($filename, '@')) {
                continue;
            }

            $createdTime = explode('@', $filename, 2)[1];
            $createdTime = explode('.', $createdTime, 2)[0];
            $backups[$createdTime] = date('d-m-Y_H:i:s', filemtime($directory . DIRECTORY_SEPARATOR . $filename));
        }

        return $backups;
    }

    public function restore(FormDefinition $definition, string $createdTime): string
    {
        $createdTime = $this->normalizeCreatedTime($createdTime);
        $filename = $definition->name . '@' . $createdTime . '.json';
        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $filename;

        Artisan::call('form:sync', ['--path' => $path]);

        return $createdTime;
    }

    public function remove(FormDefinition $definition, string $createdTime): bool
    {
        $createdTime = $this->normalizeCreatedTime($createdTime);
        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $definition->name . '@' . $createdTime . '.json';

        if (!File::exists($path)) {
            return false;
        }

        return File::delete($path);
    }

    public function removeForDefinition(FormDefinition $definition): int
    {
        return $this->removeMatching(fn ($filename) => str_contains($filename, $definition->name));
    }

    public function removeAll(): int
    {
        return $this->removeMatching(fn ($filename) => str_ends_with($filename, '.json'));
    }

    protected function removeMatching(callable $matches): int
    {
        $directory = $this->backupDirectory();
        if (!is_dir($directory)) {
            return 0;
        }

        $removed = 0;
        foreach (scandir($directory) ?: [] as $filename) {
            if (!$matches($filename)) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $filename;
            if (File::exists($path) && File::delete($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    protected function normalizeCreatedTime(string $createdTime): string
    {
        return str_replace([' - ', '/'], ['_', '-'], $createdTime);
    }

    protected function backupDirectory(): string
    {
        return config('uspdev-forms.forms_storage_dir');
    }
}
