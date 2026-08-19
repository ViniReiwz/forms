# API direta

A API direta expõe métodos públicos em models como `FormDefinition` e `FormSubmission`.

```php
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;
```

## Quando usar esta abordagem

A API direta funciona bem quando a aplicação já tem uma entidade carregada e a operação pertence naturalmente a essa entidade.

Ela deixa o código mais expressivo em fluxos que já estão trabalhando com `FormDefinition` ou `FormSubmission`. O consumidor opera sobre o próprio model, sem passar a entidade de volta para a facade.

Esta abordagem é indicada quando:

* já há uma `FormDefinition` carregada;
* já há uma `FormSubmission` carregada;
* a ação pertence claramente à entidade em mãos;
* a resolução de `name + version`, versão ativa, listagens e consultas globais já aconteceu antes.

Quando a aplicação ainda precisa localizar definições, resolver versão ativa, buscar submissões, filtrar registros ou sincronizar arquivos JSON, a [API via facade](api_facade.md) é a entrada adequada.

A comparação completa entre as duas abordagens está em [API via facade e API direta: diferenças e equivalências](api_direta_facade_diferencas_equivalencias.md).

## Regra de versão

A API direta parte de uma `FormDefinition` ou `FormSubmission` já carregada. Por isso, ela não resolve `name + version` nem versão ativa durante a chamada.

```php
// A definição já foi resolvida antes:
$definition = FormDefinition::where('name', 'parecer_final')
    ->where('version', 2)
    ->firstOrFail();

// A submissão carrega a definição usada no envio original:
$definition = $submission->formDefinition;
```

## Definições

```php
// Definição específica já carregada:
$definition = FormDefinition::where('name', 'parecer_final')
    ->where('version', 2)
    ->firstOrFail();

// Submissões relacionadas à definição:
$submissions = $definition->formSubmissions;
```

## Renderização

```php
// Definição já carregada:
$html = $definition->render([
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);

// Definição específica já carregada:
$definition = FormDefinition::where('name', 'parecer_final')
    ->where('version', 2)
    ->firstOrFail();

$html = $definition->render([
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);
```

Na tela de edição, a renderização recebe a submissão como segundo argumento. A biblioteca usa `$submission->formDefinition`, preenche os campos com os dados já salvos e preserva a versão usada no envio original.

```php
// GET /parecer/{submission}/edit
// Mostra a tela de edição. Não altera dados e não chama updateFromRequest().
public function edit(FormSubmission $submission)
{
    $html = $submission->formDefinition->render([
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
    $submission = $submission->updateFromRequest($request);

    return redirect()->route('pareceres.edit', $submission);
}
```

`edit()` e `update()` não chamam um ao outro diretamente. A ligação entre eles é o HTML gerado por `render()`: o `action` do formulário aponta para a rota de `update`, e o `method => 'PUT'` faz o envio chegar nessa rota como uma requisição `PUT`.

### Opções de `method` em `render()`

`method` é uma opção de renderização do HTML, não uma operação de persistência. Ela define o método HTTP do `<form>` gerado e não chama `$definition->submit()` nem `$submission->updateFromRequest()`.

| Valor | Comportamento no HTML gerado | Processamento esperado depois do envio |
| ----- | ---------------------------- | --------------------------------------- |
| `POST` | gera um formulário `POST` normal | a URL definida em `action` aponta para uma ação do controller que chama `$definition->submit()` quando a intenção é criar uma submissão |
| `PUT` | gera um formulário enviado por `POST` com spoofing Laravel para `PUT` | a URL definida em `action` aponta para uma ação do controller que chama `$submission->updateFromRequest()` quando a intenção é atualizar uma submissão existente |

`POST` é o valor padrão de `method`. `PUT` é usado na tela de edição (página que mostra um formulário já preenchido para o usuário alterar os dados) apenas para preparar o envio HTTP do formulário. A edição não acontece durante `render()`; ela acontece quando o request enviado pelo formulário é processado por `$submission->updateFromRequest()`.

Outros métodos HTTP não fazem parte do contrato público de `render()` nesta versão.

## Submissões

```php
$submission = $definition->submit($request);
$submission = $submission->updateFromRequest($request);
$definitionSubmissions = $definition->formSubmissions;
$definitionSubmissionsByKey = $definition->formSubmissions()
    ->where('key', 'workflow-123')
    ->get();
```

`submit` e `updateFromRequest` retornam `FormSubmission` ou lançam exceção, como `ValidationException`. A API pública não retorna strings ou arrays de erro legados.

## Uso sem persistência

`$definition->validateData()` retorna os dados validados ou lança `ValidationException`. Ele não cria nem atualiza `FormSubmission`.

```php
// Renderize normalmente:
$html = $definition->render([
    'action' => route('parecer.preview'),
    'method' => 'POST',
]);

// Valide sem persistir:
$validated = $definition->validateData($request);
```

Nesse formato, a aplicação já sabe qual definição valida os dados.

## Filtros

```php
$submissions = $definition->formSubmissions()
    ->where('key', 'workflow-123')
    ->where('data->resultado', 'aprovado')
    ->get();
```

## Arquivos

```php
return $submission->download('arquivo');
```

## Exclusão

```php
$deleted = $submission->deleteWithActivity(auth()->user());
```
