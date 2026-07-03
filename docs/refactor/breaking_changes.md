# Breaking changes

Esta versão altera a API pública e o contrato de definições. Sistemas que não puderem migrar permanecem na versão anterior do pacote.

## Classe Form deixa de ser API pública

Consumidores externos migram para `Uspdev\Forms\Facades\Forms`.

Antes:

```php
use Uspdev\Forms\Form;

$form = new Form(['action' => route('forms.store')]);
$html = $form->generateHtml('demo');
```

Depois, usando a versão ativa:

```php
use Uspdev\Forms\Facades\Forms;

$html = Forms::render('demo', [
    'action' => route('forms.store'),
]);
```

Depois, usando uma versão explícita:

```php
$html = Forms::render('demo', 1, [
    'action' => route('forms.store'),
]);
```

Antes:

```php
$submission = (new Form(['editable' => true]))->handleSubmission($request);
```

Depois:

```php
$submission = Forms::submit($request);
```

Para atualizar uma submissão:

```php
$submission = Forms::update($request, $submission);
```

## Definições agora usam name + version

Antes, `name` era único.

Depois, `name` identifica o formulário lógico e `version` identifica uma versão concreta.

```php
$active = Forms::definition('parecer_final');
$definition = Forms::definition('parecer_final', 2);
```

Arquivos JSON de definição informam `version`.

```json
{
  "name": "parecer_final",
  "version": 2,
  "status": "active",
  "group": "workflow",
  "description": "Parecer final",
  "fields": []
}
```

## Erros de submissão

A API pública retorna `FormSubmission` em caso de sucesso ou lança exceções em falha. Fluxos que tratavam retorno string/array de `Form::handleSubmission` migram para `try/catch`.
