# Caso de uso completo: formulário de parecer final

Este exemplo mostra um fluxo completo usando `uspdev/forms`: criar a definição, sincronizar com o banco, renderizar o formulário, submeter dados, validar erros, editar uma submissão e consultar resultados.

## Cenário

Uma aplicação coleta um parecer final de análise. O formulário tem:

* título do parecer;
* resultado da análise;
* justificativa;
* arquivo opcional.

O formulário será identificado por:

```text
name: parecer_final
version: 1
```

## Perspectivas do fluxo

Este caso de uso mistura duas responsabilidades diferentes:

* **Desenvolvedor consumidor da biblioteca**: prepara arquivos, rotas, controllers e views da aplicação que usa `uspdev/forms`.
* **Usuário configurador do formulário**: define campos, rótulos, obrigatoriedade e regras em uma interface interativa. Essa interface ainda não está implementada, mas o resultado esperado dela é uma definição equivalente ao JSON mostrado abaixo.

As etapas com `storage/app/formsJson`, `php artisan forms:sync`, rotas e controller descrevem o fluxo do desenvolvedor. No fluxo com interface interativa, o usuário não precisa acessar o código nem executar comandos Artisan: a aplicação coleta as escolhas feitas na tela, monta a definição, valida a estrutura e persiste em `form_definitions`.

## 1. Criar a definição JSON

No fluxo do desenvolvedor, crie um arquivo em `storage/app/formsJson/parecer_final.v1.json`.

No fluxo futuro com interface interativa, este JSON não precisa ser escrito manualmente. A interface deve gerar uma definição com a mesma estrutura a partir das escolhas do usuário configurador.

```json
{
  "name": "parecer_final",
  "version": 1,
  "status": "active",
  "group": "workflow",
  "description": "Formulário de parecer final",
  "fields": [
    {
      "type": "separator",
      "label": "Dados do parecer"
    },
    {
      "name": "titulo",
      "type": "text",
      "label": "Título",
      "required": true,
      "validation_rule": "max:150",
      "width": 8
    },
    [
      {
        "name": "resultado",
        "type": "select",
        "label": "Resultado",
        "required": true,
        "options": [
          "aprovado",
          "reprovado",
          "pendente"
        ]
      },
      {
        "name": "data_parecer",
        "type": "date",
        "label": "Data do parecer",
        "required": true
      }
    ],
    {
      "name": "justificativa",
      "type": "textarea",
      "label": "Justificativa",
      "required": true,
      "validation_rule": "min:20"
    },
    {
      "name": "anexo",
      "type": "file",
      "label": "Anexo",
      "required": false,
      "accept": ".pdf,image/*"
    }
  ]
}
```

## 2. Sincronizar a definição

No fluxo do desenvolvedor, sincronize os arquivos JSON com o banco.

```bash
php artisan forms:sync --path=storage/app/formsJson
```

Ou, pela API:

```php
use Uspdev\Forms\Facades\Forms;

$result = Forms::syncFromDirectory(storage_path('app/formsJson'));
```

O sync valida a definição, cria ou atualiza o registro por `name + version` e marca `parecer_final` versão `1` como ativa.

No fluxo com interface interativa, a sincronização por arquivo não é necessária. A própria aplicação deve enviar a definição gerada para o backend, validar com as mesmas regras de `FormDefinitionSchemaValidator` e criar ou atualizar o registro em `form_definitions`.

## 3. Criar rotas da aplicação

Esta etapa é responsabilidade do desenvolvedor consumidor da biblioteca. As rotas abaixo expõem, na aplicação, as telas e endpoints que vão renderizar o formulário, receber submissões, editar respostas e baixar arquivos.

```php
use App\Http\Controllers\ParecerController;
use Illuminate\Support\Facades\Route;

Route::get('/parecer/create', [ParecerController::class, 'create'])
    ->name('parecer.create');

Route::post('/parecer', [ParecerController::class, 'store'])
    ->name('parecer.store');

Route::get('/parecer/{submission}/edit', [ParecerController::class, 'edit'])
    ->name('parecer.edit');

Route::put('/parecer/{submission}', [ParecerController::class, 'update'])
    ->name('parecer.update');

Route::get('/parecer/{submission}/download/{field}', [ParecerController::class, 'download'])
    ->name('parecer.download');
```

## 4. Renderizar o formulário

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Uspdev\Forms\Facades\Forms;
use Uspdev\Forms\Models\FormSubmission;

class ParecerController
{
    public function create()
    {
        $parecerFormHtml = Forms::render('parecer_final', [
            'action' => route('parecer.store'),
            'method' => 'POST',
            'key' => 'processo-123',
        ]);

        return view('parecer.form', compact('parecerFormHtml'));
    }
}
```

`Forms::render()` retorna uma string HTML com o formulário renderizado. O nome da variável que recebe esse retorno é responsabilidade da aplicação consumidora. Use nomes semânticos, como `$parecerFormHtml`, especialmente quando a mesma tela puder exibir mais de um formulário.

Como `version` foi omitida, a biblioteca usa a versão ativa de `parecer_final`.

Quando a aplicação vincula esse fluxo à versão `1`, a renderização ocorre com a versão `1`:

```php
$parecerFormHtml = Forms::render('parecer_final', 1, [
    'action' => route('parecer.store'),
    'method' => 'POST',
    'key' => 'processo-123',
]);
```

## 5. Exibir na view

```blade
{{-- resources/views/parecer/form.blade.php --}}

@extends('layouts.app')

@section('content')
    {!! $parecerFormHtml !!}
@endsection
```

A view apenas imprime o HTML recebido do controller. Como `{!! ... !!}` não escapa o conteúdo, use essa saída somente para HTML gerado pela biblioteca ou por código controlado pela aplicação.

## 6. Submeter e validar

```php
public function store(Request $request)
{
    try {
        $submission = Forms::submit($request);
    } catch (ValidationException $e) {
        return back()
            ->withErrors($e->validator)
            ->withInput();
    }

    return redirect()
        ->route('parecer.edit', $submission)
        ->with('alert-success', 'Parecer salvo com sucesso.');
}
```

Se algum campo obrigatório estiver ausente ou uma regra como `min:20` falhar, `Forms::submit()` lança `ValidationException`.

## 6.1. Validar sem persistir

Quando a aplicação usa o formulário sem persistir os dados em `form_submissions`, `Forms::validate()` entra no lugar de `Forms::submit()`.

```php
public function preview(Request $request)
{
    try {
        $validated = Forms::validate($request, 'parecer_final');
    } catch (ValidationException $e) {
        return back()
            ->withErrors($e->validator)
            ->withInput();
    }

    // A aplicação decide o que fazer com os dados.
    // Ex.: enviar para uma API, calcular um resultado ou salvar em tabela própria.
    return view('parecer.preview', [
        'data' => $validated,
    ]);
}
```

Com versão explícita:

```php
$validated = Forms::validate($request, 'parecer_final', 1);
```

Esse fluxo valida os dados com as regras da definição, mas não cria `FormSubmission`.

## 7. Editar uma submissão existente

Esta ação exibe a tela de edição. Ela responde ao `GET /parecer/{submission}/edit`, renderiza o formulário preenchido e não altera dados no banco.

```php
public function edit(FormSubmission $submission)
{
    $parecerFormHtml = Forms::render('parecer_final', [
        'action' => route('parecer.update', $submission),
        'method' => 'PUT',
    ], $submission);

    return view('parecer.form', compact('parecerFormHtml', 'submission'));
}
```

Ao receber uma submissão, a biblioteca renderiza usando `$submission->formDefinition`. Isso preserva a versão usada no envio original, mesmo que outra versão de `parecer_final` esteja ativa.

Esse método apenas monta o formulário HTML da tela de edição. Ele não chama `update()` diretamente. A ligação com a atualização fica no HTML gerado: o atributo `action` aponta para `route('parecer.update', $submission)` e o `method => 'PUT'` faz o navegador enviar uma nova requisição `PUT` para essa rota quando o usuário envia o formulário.

## 8. Atualizar a submissão

Esta ação processa o envio da tela de edição. Ela responde ao `PUT /parecer/{submission}` e chama `Forms::update()` para atualizar os dados.

```php
public function update(Request $request, FormSubmission $submission)
{
    try {
        $submission = Forms::update($request, $submission);
    } catch (ValidationException $e) {
        return back()
            ->withErrors($e->validator)
            ->withInput();
    }

    return redirect()
        ->route('parecer.edit', $submission)
        ->with('alert-success', 'Parecer atualizado com sucesso.');
}
```

## 9. Consultar submissões

```php
// Buscar todas as submissões da versão ativa:
$submissions = Forms::submissions('parecer_final');

// Buscar submissões da versão `1`:
$submissions = Forms::submissions('parecer_final', 1);

// Filtrar pareceres aprovados da versão `1`:
$aprovados = Forms::filterSubmissions(
    'parecer_final',
    1,
    'resultado',
    '==',
    'aprovado'
);
```

## 10. Baixar arquivo enviado

```php
public function download(FormSubmission $submission, string $field)
{
    return Forms::downloadFile($submission, $field);
}
```

Exemplo de URL:

```text
/parecer/15/download/anexo
```

## Fluxos ilustrados

### Fluxo do desenvolvedor consumidor

```mermaid
sequenceDiagram
    participant Dev as Desenvolvedor
    participant Sync as forms:sync
    participant App as Aplicação
    participant Forms as Forms facade
    participant DB as Banco

    Dev->>Sync: cria parecer_final.v1.json
    Sync->>Forms: valida e sincroniza definição
    Forms->>DB: salva form_definition name+version
    App->>Forms: render('parecer_final')
    Forms->>DB: busca versão ativa
    Forms-->>App: HTML do formulário
    App->>Forms: submit(request)
    Forms->>Forms: valida dados
    Forms->>DB: cria form_submission
    App->>Forms: render(..., submission)
    Forms->>DB: usa submission.formDefinition
    Forms-->>App: HTML preenchido
```

### Fluxo do usuário configurador

Este fluxo representa a interface interativa futura. Ele não substitui as rotas da aplicação consumidora; ele substitui a criação manual do arquivo JSON e o comando `forms:sync`.

```mermaid
sequenceDiagram
    participant User as Usuário configurador
    participant UI as Interface interativa
    participant App as Aplicação
    participant Validator as FormDefinitionSchemaValidator
    participant DB as Banco

    User->>UI: escolhe campos, tipos e regras
    UI->>App: envia definição estruturada
    App->>Validator: valida name, version, status e fields
    Validator-->>App: definição válida
    App->>DB: cria ou atualiza form_definition
    DB-->>App: definição persistida
    App-->>UI: formulário disponível para uso
```

### Fluxo do usuário que preenche o formulário

Depois que a definição existe no banco, seja por arquivo sincronizado ou por interface interativa, o uso do formulário é o mesmo.

```mermaid
sequenceDiagram
    participant User as Usuário respondente
    participant App as Aplicação
    participant Forms as Forms facade
    participant DB as Banco

    User->>App: acessa tela de parecer final
    App->>Forms: render('parecer_final')
    Forms->>DB: busca form_definition ativa
    Forms-->>App: HTML do formulário
    App-->>User: exibe formulário
    User->>App: envia dados preenchidos
    App->>Forms: submit(request)
    Forms->>Forms: valida dados
    Forms->>DB: cria form_submission
    App-->>User: confirma submissão
```

## Pontos importantes

* Omitir `version` usa a versão ativa.
* Informar `version` prende a operação a uma versão concreta.
* Arquivos JSON e `forms:sync` são um fluxo de desenvolvedor.
* Uma interface interativa futura deve gerar e persistir uma `FormDefinition` equivalente ao JSON manual.
* `Forms::validate()` valida sem persistir.
* `Forms::submit()` e `Forms::update()` validam e persistem.
* Editar ou visualizar uma submissão existente usa a definição relacionada à submissão.
* Igualdade em filtros usa somente o operador `==`.
* `separator` é apenas visual e não gera dado submetido.
