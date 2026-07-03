# Métodos diretos nos models

Métodos diretos são atalhos públicos em models como `FormDefinition` e `FormSubmission`.

Eles são indicados quando a aplicação já tem a entidade carregada e quer executar uma ação que pertence naturalmente a essa entidade.

## Contexto

Métodos diretos dão mais flexibilidade e deixam o código mais expressivo quando o objeto já está em mãos.

Exemplo:

```php
return $submission->download('arquivo');
```

Nesse caso, a aplicação já tem uma `FormSubmission`. O arquivo pertence àquela submissão e o caminho está nos dados dela. Chamar o método direto evita passar a submissão de volta para a facade apenas para operar sobre ela.

## Regra para expor métodos diretos

Um método direto só é público quando há uma justificativa clara:

* a operação pertence naturalmente à entidade;
* a aplicação consumidora pode já ter a entidade carregada;
* o método deixa o código mais expressivo;
* a versão via facade continua útil para fluxos de alto nível;
* as duas formas podem compartilhar a mesma implementação interna.

Se não houver ganho real em oferecer as duas formas, o método não é duplicado publicamente.

## FormDefinition

`FormDefinition` representa a estrutura e o versionamento de um formulário.

Métodos diretos disponíveis:

```php
$html = $definition->render([
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);

$validated = $definition->validateData($request);

$submission = $definition->submit($request);
```

Esses métodos fazem sentido quando a aplicação já resolveu a definição que deseja usar.

Equivalentes via facade:

```php
$html = Forms::render('parecer_final', 2, [
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);

$validated = Forms::validate($request, 'parecer_final', 2);

$submission = Forms::submit($request);
```

## FormSubmission

`FormSubmission` representa uma submissão concreta e aponta para a definição usada no envio por meio de `form_definition_id`.

Métodos diretos disponíveis:

```php
$html = $submission->showHtml();

return $submission->download('arquivo');

$submission = $submission->updateFromRequest($request);

$deleted = $submission->deleteWithActivity(auth()->user());
```

Esses métodos fazem sentido quando a aplicação já tem a submissão em mãos.

Equivalentes via facade:

```php
return Forms::downloadFile($submission, 'arquivo');

$submission = Forms::update($request, $submission);

$deleted = Forms::deleteSubmission($submission, auth()->user());
```

`showHtml` é model apenas, porque é uma visualização direta da submissão concreta.

## Equivalência obrigatória

Quando um comportamento está disponível pela facade e pelo model, as duas formas:

* delegam para o mesmo serviço interno;
* retornam o mesmo tipo;
* lançam as mesmas exceções;
* respeitam as mesmas regras de validação, autorização e auditoria;
* têm testes de equivalência.

Exemplo de implementação esperada:

```php
Forms::downloadFile($submission, $field);
$submission->download($field);
```

Ambas as chamadas chegam ao mesmo serviço interno de arquivos de submissão.

## Contexto da facade

A facade entra quando a operação resolve definição, versão ativa, submissão ou executa um fluxo completo.

## Contexto do model

O método direto entra quando a entidade já está carregada e a ação pertence claramente a ela.

O mapa completo de métodos equivalentes, métodos apenas via facade e métodos apenas via model está em [Equivalência entre facade e models](equivalencia_facade_model.md).
