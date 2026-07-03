# Guia de migração para consumidores

Este guia é voltado para sistemas que usam `uspdev/forms`, incluindo bibliotecas consumidoras como `uspdev/workflow`.

## Objetivo

Migrar do uso direto de `Uspdev\Forms\Form` para a facade `Uspdev\Forms\Facades\Forms` e passar a referenciar definições por `name + version` quando uma versão concreta for necessária.

## Passo 1: atualizar definições JSON

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

## Passo 2: substituir new Form por Forms

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
