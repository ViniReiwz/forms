<?php

namespace Uspdev\Forms\Console\Commands;

use Illuminate\Console\Command;
use Uspdev\Forms\Services\FormDefinitionSyncService;

class FormSync extends Command
{
    // O nome do comando é "forms:sync" e tem uma opção "--path" para especificar o diretório dos arquivos JSON
    protected $signature = 'forms:sync {--path= : Diretório com os arquivos .json de formularios} {--all= : Sincronizar todos os formulários}';
    protected $aliases = ['form:sync'];
    protected $description = 'Sincroniza definicoes de formularios a partir de arquivos JSON';

    public function handle()
    {
        // O caminho para sincronização pode ser passado como opção ou definido na configuração
        $syncPath = $this->option('path') ?: config('uspdev-forms.forms_storage_dir');
        $this->info('Sincronizando formularios em: ' . $syncPath);
        
        
        if(is_dir($syncPath))
        {
            
            $result = app(FormDefinitionSyncService::class)->syncFromDirectory($syncPath);

            $this->line('Arquivos JSON encontrados: ' . $result['total_files']);
            $this->line('Formularios sincronizados: ' . $result['synced']);
            $this->line('Criados: ' . $result['created']);
            $this->line('Atualizados: ' . $result['updated']);
            $this->line('Sem alteracoes: ' . $result['unchanged']);
            $this->line('Ignorados: ' . $result['ignored']);

            foreach ($result['messages'] as $message) {
                $this->line('- ' . $message);
            }

            if (!empty($result['errors'])) {
                $this->error('Erros encontrados durante a sincronizacao:');
                foreach ($result['errors'] as $error) {
                    $this->error('- ' . $error);
                }
                return self::FAILURE;
            }

            $this->info('Sincronizacao concluida com sucesso.');
            return self::SUCCESS;
        }

        elseif(is_file($syncPath))
        {
            try {
                app(FormDefinitionSyncService::class)->syncFromFile($syncPath);
            } catch (\Throwable $e) {
                $this->error('Erro ao sincronizar: ' . $e->getMessage());
                return self::FAILURE;
            }

            $this->info('Sincronizacao concluida com sucesso.');
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
