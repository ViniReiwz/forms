# API via facade

A facade `Uspdev\Forms\Facades\Forms` é a porta oficial e estável para consumir a biblioteca por fluxos de alto nível.

```php
use Uspdev\Forms\Facades\Forms;
```

## Quando usar esta abordagem

A facade funciona bem quando a aplicação quer que a biblioteca resolva definição, versão ativa, submissão, validação, persistência, consultas ou sincronização.

Ela reduz o acoplamento com os models internos. O consumidor chama métodos de alto nível e a biblioteca decide quais serviços internos e models participam.

Esta abordagem é indicada quando:

* ainda não há uma `FormDefinition` ou `FormSubmission` carregada;
* a operação resolve `name + version` ou versão ativa;
* a operação representa um fluxo completo, como renderizar, submeter, atualizar, consultar ou sincronizar;
* a aplicação padroniza o consumo pela facade.

Quando a aplicação já tem uma `FormDefinition` ou `FormSubmission` carregada e a ação pertence diretamente a essa entidade, a [API direta](api_direta.md) pode deixar o código mais expressivo.

A comparação completa entre as duas abordagens está em [API via facade e API direta: diferenças e equivalências](api_direta_facade_diferencas_equivalencias.md).

## Regra de versão

Métodos que recebem `name` e `version` aceitam a versão como parâmetro opcional. Quando `version` é omitida, a biblioteca usa a versão ativa daquele `name`.

Versão explícita aparece quando a operação é reprodutível ou presa a uma definição concreta. Versão omitida aparece quando o objetivo é sempre trabalhar com a versão ativa.

```php
// Versão ativa:
$definition = Forms::definition('parecer_final');

// Versão específica:
$definition = Forms::definition('parecer_final', 2);
```

## Definições

```php
// Versão ativa:
$definition = Forms::definition('parecer_final');

// Versão específica:
$definition = Forms::definition('parecer_final', 2);

// Versão ativa de forma explícita:
$definition = Forms::activeDefinition('parecer_final');

// Definições por grupo:
$definitions = Forms::definitions('workflow');
```

## Renderização

```php
// Versão ativa:
$html = Forms::render('parecer_final', [
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);

// Versão específica:
$html = Forms::render('parecer_final', 2, [
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);
```

Na tela de edição (página que mostra um formulário já preenchido para o usuário alterar os dados), a facade recebe a submissão como terceiro argumento. A biblioteca usa `$submission->formDefinition`, preenche os campos com os dados já salvos e preserva a versão usada no envio original.

```php
// GET /parecer/{submission}/edit
// Mostra a tela de edição. Não altera dados e não chama update().
public function edit(FormSubmission $submission)
{
    $html = Forms::render('parecer_final', [
        // Esta URL é gravada no atributo action do <form> gerado.
        // Quando o usuário enviar o formulário, o navegador fará uma nova requisição para ela.
        'action' => route('pareceres.update', $submission),
        'method' => 'PUT',
    ], $submission);

    return view('parecer.form', compact('html', 'submission'));
}

// PUT /parecer/{submission}
// Esta ação é chamada pela requisição enviada pelo navegador a partir do formulário acima.
// Aqui a submissão é atualizada explicitamente.
public function update(Request $request, FormSubmission $submission)
{
    $submission = Forms::update($request, $submission);

    return redirect()->route('pareceres.edit', $submission);
}
```

`edit()` e `update()` não chamam um ao outro diretamente. A ligação entre eles é o HTML gerado por `render()`: o `action` do formulário aponta para a rota de `update`, e o `method => 'PUT'` faz o envio chegar nessa rota como uma requisição `PUT`.

### Opções de `method` em `render()`

`method` é uma opção de renderização do HTML, não uma operação de persistência. Ela define o método HTTP do `<form>` gerado e não chama `Forms::submit()` nem `Forms::update()`.

| Valor | Comportamento no HTML gerado | Processamento esperado depois do envio |
| ----- | ---------------------------- | --------------------------------------- |
| `POST` | gera um formulário `POST` normal | a URL definida em `action` aponta para uma ação do controller que chama `Forms::submit()` quando a intenção é criar uma submissão |
| `PUT` | gera um formulário enviado por `POST` com spoofing Laravel para `PUT` | a URL definida em `action` aponta para uma ação do controller que chama `Forms::update()` quando a intenção é atualizar uma submissão existente |

`POST` é o valor padrão de `method`. `PUT` é usado na tela de edição apenas para preparar o envio HTTP do formulário. A edição não acontece durante `render()`; ela acontece quando o request enviado pelo formulário é processado por `Forms::update()`.

Outros métodos HTTP não fazem parte do contrato público de `Forms::render()`.

## Submissões

```php
$submission = Forms::submit($request);
$submission = Forms::update($request, $submission);
$submission = Forms::submission($id);
$submissions = Forms::submissions('parecer_final', key: 'workflow-123');
$submissions = Forms::submissions('parecer_final', 2, 'workflow-123');
```

`submit` e `update` retornam `FormSubmission` ou lançam exceção, como `ValidationException`. A API pública não retorna strings ou arrays de erro legados.

## Uso sem persistência

`Forms::validate()` retorna os dados validados ou lança `ValidationException`. Ele não cria nem atualiza `FormSubmission`.

```php
// Renderize normalmente:
$html = Forms::render('parecer_final', [
    'action' => route('parecer.preview'),
    'method' => 'POST',
]);

// Valide sem persistir:
$validated = Forms::validate($request);
```

Nesse formato, a biblioteca resolve a definição a partir dos dados do request. Se o request não trouxer a identificação do formulário, a chamada recebe `name` e, se necessário, `version`.

```php
$validated = Forms::validate($request, 'parecer_final', 1);
```

## Filtros

```php
$submissions = Forms::filterSubmissions(
    name: 'parecer_final',
    field: 'resultado',
    operator: '==',
    version: 2,
    value: 'aprovado',
    key: 'workflow-123'
);
```

### Operadores suportados

| Operador | Significado |
| -------- | ----------- |
| `contains` | busca se o valor informado está contido no campo JSON |
| `==` | igualdade |
| `!=` | diferente de |
| `empty` | campo nulo ou string vazia |
| `not_empty` | campo não nulo e diferente de string vazia |

## Arquivos

```php
return Forms::downloadFile($submission, 'arquivo');
```

## Exclusão

```php
$deleted = Forms::deleteSubmission($submission, auth()->user());
```

## Auditoria

```php
$activities = Forms::submissionActivities($submission, 20);
$activity = Forms::activity($activityId);
```

`submissionActivities()` retorna as activities mais recentes de uma submissão. `activity()` busca uma activity específica pelo id ou lança `ModelNotFoundException`.

## Sincronização

```php
$result = Forms::syncFromDirectory(storage_path('app/formsJson'));
```

`syncFromDirectory` lê arquivos `.json` de um diretório e sincroniza as definições com a tabela `form_definitions`.

* lê apenas arquivos JSON do diretório informado;
* valida cada definição com `FormDefinitionSchemaValidator`;
* cria ou atualiza registros usando `name + version`;
* quando um JSON vem com `status = active`, desativa as outras versões do mesmo `name`;
* retorna um resumo com arquivos processados, criados, atualizados, ignorados e erros.

Esse método é útil para manter definições versionadas em arquivos do projeto e publicá-las no banco durante deploy, setup local ou atualização controlada de ambientes.
