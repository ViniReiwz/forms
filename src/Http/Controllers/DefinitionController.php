<?php

namespace Uspdev\Forms\Http\Controllers;

use Error;
use Exception;
use Illuminate\Http\Request;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Services\FormDefinitionBackupService;
use Uspdev\Forms\Services\FormDefinitionService;

class DefinitionController extends Controller
{

    public function __construct()
    {
        $this->middleware('can:' . config('uspdev-forms.adminGate'));
    }


    public function index()
    {
        \UspTheme::activeUrl(route('form-definitions.index'));

        $formDefinitions = app(FormDefinitionService::class)->definitions();
        // Inidica a aba de 'index' como ativa na view
        $activeTab = 'index';
        return view('uspdev-forms::definition.index', compact('formDefinitions','activeTab'));
    }

    public function show(FormDefinition $formDefinition)
    {
        return $formDefinition;
    }

    public function create()
    {
        \UspTheme::activeUrl(route('form-definitions.index'));
        return view('uspdev-forms::definition.create', ['formDefinition' => null]);
    }

    public function store(Request $request)
    {
        app(FormDefinitionService::class)->createFromRequest($request);

        return redirect()->route('form-definitions.index')
            ->with('alert-success', 'Definição criada com sucesso!');
    }

    public function edit(FormDefinition $formDefinition)
    {
        \UspTheme::activeUrl(route('form-definitions.index'));
        return view('uspdev-forms::definition.create', compact('formDefinition'));
    }

    public function update(Request $request, FormDefinition $formDefinition)
    {
        app(FormDefinitionService::class)->updateFromRequest($request, $formDefinition);

        return redirect()->route('form-definitions.index')
            ->with('alert-success', 'Definição atualizada com sucesso!');
    }

    /**
     * Remove o registro do banco de dados
     *
     * Também remove registros excluidos (softDeletes) limpando o BD
     */
    public function destroy(FormDefinition $formDefinition, Request $request)
    {
        if ($request->destroy_trashed) {
            app(FormDefinitionService::class)->purgeTrashedSubmissions($formDefinition);

            return redirect()->route('form-definitions.index')
                ->with('alert-success', 'Registros excluídos limpado com sucesso!');
        }

        try {
            app(FormDefinitionService::class)->delete($formDefinition);

            return redirect()->route('form-definitions.index')
                ->with('alert-success', 'Definição excluída com sucesso!');
        } catch (Exception $e) {
            return redirect()->route('form-definitions.index')
                ->with('alert-danger', 'Não foi possível excluir: ' . $e->getMessage());
        }
    }
    /**
     * Gera o backup de uma definição de formulário.
     * Inicialmente, verifica a existência do diretório para salvar os arquivos .json,
     *      caso não exista, o cria.
     * Após a verificação, cria o arquivo com nome no formato: 'nomedoform@datadacriaçãodobackup.json'
     * Assim, abre o arquivo e escreve a definição no formato esperado do .json]
     *
     * @param FormDefinition $formDefinition
     * @return \Illuminate\Http\RedirectResponse
     */
    public function backup_def(FormDefinition $formDefinition)
    {
        app(FormDefinitionBackupService::class)->backup($formDefinition);

        return redirect()->back()->with('alert-success','Backup de: '. $formDefinition['name'] .' gerado com sucesso em: ' . now() . '!');

    }

    /**
     * Gera um backup de todas as definições persisitidas no banco de dados
     * Apenas usa o método 'backup_def' para todas as definições
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function backup_all()
        {
            app(FormDefinitionBackupService::class)->backupAll();

            return redirect()->back()->with('alert-success','Backups gerados em: ' . now() . ' com sucesso!');
        }

    /**
     * Exibe informações básicas sobre os backups e definições:
     *  Definição - número de backups desta definição
     *  e botões de ação para visualizar e gerar novos backups.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function backups_index()
    {
        \UspTheme::activeUrl(route('form-definitions.backups'));

        // Indica que a aba ativa atualmente é a de backups
        $activeTab = 'backup';
        $formDefinitions = app(FormDefinitionService::class)->definitions();
        return view('uspdev-forms::definition.backup', compact('activeTab','formDefinitions'));
    }

    /**
     * Lista todos os backups de ua definição que existem atualmente
     *
     * @param FormDefinition $formDefinition
     * @return \Illuminate\Contracts\View\View
     */
    public function list_backups(FormDefinition $formDefinition)
    {
        $backup_data = app(FormDefinitionBackupService::class)->list($formDefinition);

        return view('uspdev-forms::definition.backup-list', ['formDefinition' => $formDefinition, 'backup_data' => $backup_data]);
    }

    /**
     * 'Restaura' um backup específico, subindo as alterações feitas no arquivo para o banco de dados
     * ou retornando a definição para o estado em que o backup se encontrava na data de criação
     *
     * @param FormDefinition $formDefinition
     * @param mixed $created_time
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore_backup(FormDefinition $formDefinition, string $created_time)
    {
        $created_time = app(FormDefinitionBackupService::class)->restore($formDefinition, $created_time);

        return redirect()->back()->with('alert-success','Backup de ' . $created_time . ' restaurado com sucesso !');
    }

    /**
     * Remove um arquivo de backup do diretório
     *
     * @param FormDefinition $formDefinition
     * @param string $created_time
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove_backup(FormDefinition $formDefinition, string $created_time)
    {
        $removed = app(FormDefinitionBackupService::class)->remove($formDefinition, $created_time);
        $filename = $formDefinition->name . '@' . str_replace([' - ', '/'], ['_', '-'], $created_time) . '.json';

        if($removed) {
            return redirect()->back()->with('alert-warning','Backup ' . $filename . ' removido com sucesso.' );
        }

        return redirect()->back()->with('alert-danger', 'Impossível remover ' . $filename .' => arquivo não existe.');
    }

    /**
     * Remove todos os backups de uma definição, filtrando pelo nome
     *
     * @param FormDefinition $formDefinition
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove_def_backups(FormDefinition $formDefinition)
    {
        app(FormDefinitionBackupService::class)->removeForDefinition($formDefinition);

        return redirect()->back()->with('alert-warning', 'Backups de ' . $formDefinition->name . ' removidos com sucesso.');
    }

    /**
     * Remove todos os backups de todas as definições
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove_all_backup()
    {
        app(FormDefinitionBackupService::class)->removeAll();

        return redirect()->back()->with('alert-warning', 'Backups removidos com sucesso.');

    }
}
