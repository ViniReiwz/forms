# Guia de migração para consumidores

Este guia é voltado para sistemas que usam `uspdev/forms`, incluindo bibliotecas consumidoras como `uspdev/workflow`.

## Objetivo

Migrar do uso direto de `Uspdev\Forms\Form` para a facade `Uspdev\Forms\Facades\Forms` e passar a referenciar definições por `name + version` quando uma versão concreta for necessária.

Sistemas que não puderem migrar para este contrato devem permanecer na versão anterior do pacote.

## Premissas da migração de banco

A migration de versionamento parte da tabela legada original, em que `form_definitions.name` era único e ainda não existiam `version` e `status`.

Nesse cenário, cada definição existente é convertida para `version = 1` e `status = active`. Esse preenchimento automático representa a versão inicial do formulário que já existia antes da V2.

Estados intermediários não são corrigidos por inferência silenciosa. A migration falha e exige correção manual quando encontra, por exemplo:

* múltiplas linhas do mesmo `name` com `version = NULL`;
* `version = NULL` em conflito com uma linha já existente em `version = 1`;
* duplicidade de `name + version`;
* `version` menor que `1`;
* `status` diferente de `draft`, `active` ou `disabled`.

Apenas `status = NULL` é convertido automaticamente para `active`, para preservar o comportamento legado em que toda definição existente era a definição utilizável.

Depois da migration, `name + version` identifica uma versão concreta, `version` e `status` são obrigatórios, e o banco impede que exista mais de uma versão `active` para o mesmo `name`.

Em MySQL e MariaDB, essa última regra usa uma coluna auxiliar interna e triggers. Após atualizar em ambiente com esses bancos, valide manualmente que:

* a migration criou os triggers de `form_definitions`;
* um `INSERT` ou `UPDATE` direto tentando deixar duas versões `active` para o mesmo `name` falha;
* salvar uma nova versão ativa pela biblioteca continua desativando a versão anterior.

O rollback dessa mudança é conceitualmente limitado: após existirem múltiplas versões para o mesmo `name`, voltar para o modelo antigo de `name` único pode falhar ou exigir perda/colapso de versões. Trate a migration como mudança estrutural de ida para ambientes com dados versionados.

## Breaking Changes

**[BREAKING CHANGE]** `Uspdev\Forms\Form` deixa de ser a API pública para consumidores externos. A integração deve passar pela facade `Uspdev\Forms\Facades\Forms`.

**[BREAKING CHANGE]** `name` não identifica mais sozinho uma definição única. Agora `name` identifica o formulário lógico e `version` identifica uma versão concreta.

**[BREAKING CHANGE]** Arquivos JSON de definição devem informar `version`. O campo `status` define se uma versão está ativa, em rascunho ou desabilitada.

**[BREAKING CHANGE]** Falhas de submissão deixam de ser tratadas como retorno `string` ou `array` de `Form::handleSubmission()`. A API pública retorna `FormSubmission` em caso de sucesso ou lança exceções em falha.

## Passo 1: atualizar definições JSON

**[BREAKING CHANGE]**

Inclua `version` em todos os arquivos de definição.

Antes:

```json
{
  "name": "parecer_final",
  "group": "workflow",
  "description": "Parecer final",
  "fields": []
}
```

Depois:

```json
{
  "name": "parecer_final",
  "version": 1,
  "status": "active",
  "group": "workflow",
  "description": "Parecer final",
  "fields": []
}
```

Antes, `name` era único. Depois, `name` identifica o formulário lógico e `version` identifica uma versão concreta. Quando uma chamada pública omitir `version`, a biblioteca usa a versão ativa daquele `name`.

## Passo 2: substituir new Form por Forms

**[BREAKING CHANGE]**

Consumidores externos migram de `Uspdev\Forms\Form` para `Uspdev\Forms\Facades\Forms`.

Antes:

```php
use Uspdev\Forms\Form;

$html = (new Form([
    'action' => route('pareceres.store'),
]))->generateHtml('parecer_final');
```

Depois, usando a versão ativa:

```php
use Uspdev\Forms\Facades\Forms;

$html = Forms::render('parecer_final', [
    'action' => route('pareceres.store'),
]);
```

Depois, usando uma versão explícita:

```php
$html = Forms::render('parecer_final', 1, [
    'action' => route('pareceres.store'),
]);
```

## Passo 3: atualizar submissões

**[BREAKING CHANGE]**

Fluxos que tratavam retorno `string` ou `array` de `Form::handleSubmission()` migram para `try/catch`.

Antes:

```php
$result = (new Form(['editable' => true]))->handleSubmission($request);

if (is_array($result)) {
    return back()->withErrors($result['errors'])->withInput();
}
```

Depois:

```php
use Illuminate\Validation\ValidationException;
use Uspdev\Forms\Facades\Forms;

try {
    $submission = Forms::submit($request);
} catch (ValidationException $e) {
    return back()->withErrors($e->validator)->withInput();
}
```

## Passo 4: atualizar edição

Ao editar uma submissão existente, o consumidor passa a submissão para que a biblioteca use a `formDefinition` relacionada.

```php
// Exibição da tela de edição:
$html = Forms::render('parecer_final', [
    'action' => route('pareceres.update', $submission),
    'method' => 'PUT',
], $submission);

// Processamento do envio da edição:
$submission = Forms::update($request, $submission);
```

No primeiro trecho, `method => 'PUT'` configura apenas o método HTTP do formulário HTML. A atualização da submissão acontece somente no segundo trecho, com `Forms::update()`.

## Passo 5: atualizar consultas

Consultas podem usar a versão ativa ou uma versão explícita.

```php
$active = Forms::definition('parecer_final');
$definition = Forms::definition('parecer_final', 1);
$submissions = Forms::submissions('parecer_final', key: 'workflow-123');
$submissionsV1 = Forms::submissions('parecer_final', 1, 'workflow-123');
```

## Mapa de equivalências da API antiga

Os métodos públicos antigos de `Uspdev\Forms\Form` deixam de ser contrato público. A tabela abaixo mostra para onde cada uso deve migrar.

| API antiga | Substituição atual | Observação |
| --- | --- | --- |
| `(new Form($config))->generateHtml($name)` | `Forms::render($name, $config)` ou `Forms::render($name, $version, $config)` | Comportamento preservado; a versão ativa é usada quando `version` é omitida. |
| `Form::handleSubmission($request)` | `Forms::submit($request)` para criar ou `Forms::update($request, $submission)` para editar | O fluxo deixa de decidir por `$request->id`; criação e edição ficam explícitas. Falhas lançam exceção. |
| `Form::validate($request)` | `Forms::validate($request, $name, $version)` ou `$definition->validateData($request)` | O retorno passa a ser apenas os dados validados; falhas lançam `ValidationException`. |
| `Form::updateSubmission($request, $id)` | `Forms::update($request, $submission)` ou `Forms::update($request, $id)` | O formulário precisa ser resolvido pela submissão existente. |
| `Form::downloadSubmissionFile($submission, $field)` | `Forms::downloadFile($submission, $field)` ou `$submission->download($field)` | Comportamento preservado. |
| `Form::deleteSubmission($id, $user)` | `Forms::deleteSubmission($submission, $user)` ou `$submission->deleteWithActivity($user)` | Comportamento preservado. |
| `Form::getSubmission($id)` | `Forms::submission($id)` | Comportamento preservado. |
| `Form::getDefinition($name)` | `Forms::definition($name)` ou `Forms::definition($name, $version)` | `name` agora identifica o formulário lógico; use `version` para uma versão concreta. |
| `Form::listDefinition($group)` | `Forms::definitions($group)` | Comportamento preservado. |
| `Form::listSubmission($name)` | `Forms::submissions($name, $version, $key)` | Use `key` explicitamente quando quiser filtrar por chave. |
| `Form::whereSubmissionContains($field, $value)` | `Forms::filterSubmissions($name, field: $field, operator: 'contains', value: $value)` | O atalho antigo que retornava todas as submissões quando `admin=true` não existe mais; autorização e escopo devem ficar no consumidor. |
| `Form::filterSubmissionByField($field, $operator, $value)` | `Forms::filterSubmissions($name, field: $field, operator: $operator, value: $value)` | O operador aceito para igualdade é `==`; `=` não faz parte do novo contrato. |
| `Form::getSubmissionActivities($id)` | `Forms::submissionActivities($submission, $take)` | Aceita id ou model da submissão e retorna as activities mais recentes. |
| `Form::detailActivity($id)` | `Forms::activity($id)` | Retorna uma activity pelo id ou lança `ModelNotFoundException`. |

A classe `Uspdev\Forms\Form` permanece apenas como detalhe interno do pacote. Consumidores não devem depender dela para renderizar, submeter, consultar, baixar arquivos, excluir submissões ou consultar auditoria.

## Orientação para workflow

Sistemas de workflow normalmente associam formulários a transições.

Quando a transição precisa ser reprodutível e permanecer presa a uma versão concreta, a definição da transição guarda `form` e `form_version`.

```json
{
  "name": "tr_aprovar",
  "label": "Aprovar",
  "from": "analise",
  "tos": ["aprovado"],
  "form": "parecer_final",
  "form_version": 1
}
```

Ao renderizar:

```php
$html = Forms::render(
    $transition['form'],
    $transition['form_version'],
    ['action' => route('workflow.transitions.apply')]
);
```

Quando a transição usa sempre a versão ativa do formulário, `form_version` pode ser omitido.

```json
{
  "name": "tr_aprovar",
  "label": "Aprovar",
  "from": "analise",
  "tos": ["aprovado"],
  "form": "parecer_final"
}
```

```php
$html = Forms::render(
    $transition['form'],
    ['action' => route('workflow.transitions.apply')]
);
```

Ao processar a transição, o workflow preserva o `form_submission_id` retornado por `Forms::submit` ou `Forms::update`, quando houver formulário associado.

## Cuidados

* A versão ativa não é usada para renderizar uma submissão antiga.
* Ao renderizar uma submissão existente, passe a submissão para a API.
* `form_version` em workflows mantém a transição estável e reprodutível.
* Omitir `version` significa usar a versão ativa do formulário.
* Há apenas uma versão ativa por `name`.
