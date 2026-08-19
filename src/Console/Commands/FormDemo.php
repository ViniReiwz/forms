<?php

namespace Uspdev\Forms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Uspdev\Forms\Enums\FormDefinitionStatus;

class FormDemo extends Command
{
    protected $signature = 'forms:demo';
    protected $description = 'Adiciona dados de exemplo no BD do forms';

    public function handle()
    {
        $form = [
            // Linha 1
            [
                ["name" => "texto", "type" => "text", "label" => "Campo de texto", "required" => false],
                ["name" => "rating", "type" => "select", "label" => "Campo select simples", "options" => ["1", "2", "3", "4", "5"], "required" => false],
            ],
            // Linha 2
            [
                ["name" => "number", "type" => "number", "label" => "Campo de número", "required" => false],
                ["name" => "email", "type" => "email", "label" => "Email", "required" => false],
                ["name" => "data", "type" => "date", "label" => "Campo de data", "required" => true],
            ],
            // Linha 3
            [
                ["name" => "textarea", "type" => "textarea", "label" => "Textarea", "required" => false],
            ],
            // Linha 4
            ["name" => "arquivo", "type" => "file", "label" => "Arquivo", "accept" => ".pdf,image/*", "required" => false],
            // Linha 5
            [
                ["name" => "codpes", "type" => "pessoa-usp", "label" => "Pessoa USP", "required" => true],
                ["name" => "codlocusp", "type" => "local-usp", "label" => "Local USP"],
                ["name" => "coddis", "type" => "disciplina-usp", "label" => "Disciplina USP"],
                ["name" => "numpat", "type" => "patrimonio-usp", "label" => "Patrimônio USP", "required" => true]
            ]
        ];

        // o $name é uma chave única para identificar o formulário
        $name = 'Demo Form';
        $group = 'demo';
        $description = 'Esse é um formulário de demonstração criado com artisan form:demo.';
        $version = 1;

        $hasVersion = Schema::hasColumn('form_definitions', 'version');
        $hasStatus = Schema::hasColumn('form_definitions', 'status');

        $query = DB::table('form_definitions')->where('name', $name);
        if ($hasVersion) {
            $query->where('version', $version);
        }

        $existing = $query->first();
        if ($existing) {
            if (!$this->confirm('O formulário "Demo Form" já existe. Deseja substituir?')) {
                $this->info('Operação cancelada.');
                return 0;
            }

            $payload = [
                'group' => $group,
                'description' => $description,
                'fields' => json_encode($form),
                'updated_at' => Carbon::now(),
            ];
            if ($hasVersion) {
                $payload['version'] = $version;
            }
            if ($hasStatus) {
                $payload['status'] = FormDefinitionStatus::Active->value;
            }

            DB::table('form_definitions')
                ->where('id', $existing->id)
                ->update($payload);

            $this->info('Formulário substituído com sucesso.');
        } else {
            $payload = [
                'name' => $name,
                'group' => $group,
                'description' => $description,
                'fields' => json_encode($form),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            if ($hasVersion) {
                $payload['version'] = $version;
            }
            if ($hasStatus) {
                $payload['status'] = FormDefinitionStatus::Active->value;
            }

            DB::table('form_definitions')->insert($payload);

            $this->info('Formulário criado com sucesso.');
        }

        $this->info('Dados de exemplo adicionados ao banco de dados.');
    }
}
