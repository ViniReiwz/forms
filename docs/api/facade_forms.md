# API via facade Forms

A facade `Uspdev\Forms\Facades\Forms` funciona como porta única, estável e orientada a fluxo para consumo da biblioteca.

```php
use Uspdev\Forms\Facades\Forms;
```

A facade funciona quando o pacote resolve detalhes como versão ativa, definição concreta, validação, persistência e consultas.

Ela também reduz o acoplamento do consumidor com os models internos. O consumidor chama métodos de alto nível e a biblioteca decide quais serviços internos e models participam.

## Quando a facade aparece

A facade aparece quando:

* ainda não há uma `FormDefinition` ou `FormSubmission` carregada;
* a operação resolve `name + version` ou versão ativa;
* a operação representa um fluxo completo, como renderizar, submeter, atualizar, consultar ou sincronizar;
* a API uniforme cobre a maior parte do consumo.

## Métodos disponíveis

### Definições

```php
$definition = Forms::definition('parecer_final');
$definition = Forms::definition('parecer_final', 2);
$definition = Forms::activeDefinition('parecer_final');
$definitions = Forms::definitions('workflow');
```

Esses métodos são facade apenas. Eles localizam definições e resolvem versionamento.

### Renderização

```php
$html = Forms::render('parecer_final', [
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);

$html = Forms::render('parecer_final', 2, [
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);
```

Quando a aplicação já tiver uma `FormDefinition`, o método direto equivalente está documentado em [métodos diretos nos models](metodos_diretos.md).

### Validação sem persistência

```php
$validated = Forms::validate($request);
$validated = Forms::validate($request, 'parecer_final');
$validated = Forms::validate($request, 'parecer_final', 2);
```

Quando a aplicação já tiver uma `FormDefinition`, a validação também acontece diretamente pela definição.

### Submissões

```php
$submission = Forms::submit($request);
$submission = Forms::update($request, $submission);
$submission = Forms::submission($id);
$submissions = Forms::submissions('parecer_final', key: 'workflow-123');
$submissions = Forms::submissions('parecer_final', 2, 'workflow-123');
```

`submit` e `update` representam fluxos completos e continuam disponíveis na facade. Quando uma entidade já está carregada, a biblioteca também oferece métodos diretos equivalentes para maior expressividade.

### Filtros

```php
$submissions = Forms::filterSubmissions(
    'parecer_final',
    field: 'resultado',
    operator: '==',
    value: 'aprovado',
    key: 'workflow-123'
);
```

Filtros por formulário são facade apenas, porque dependem de resolução de definição, versão e consulta.

### Arquivos

```php
return Forms::downloadFile($submission, 'arquivo');
```

Quando a aplicação já tiver uma `FormSubmission`, o método direto equivalente também está disponível:

```php
return $submission->download('arquivo');
```

As duas chamadas usam a mesma implementação interna, retornam o mesmo tipo e lançam as mesmas exceções.

### Exclusão

```php
$deleted = Forms::deleteSubmission($submission, auth()->user());
```

Quando a aplicação já tiver uma `FormSubmission`, o método direto equivalente `$submission->deleteWithActivity($user)` também está disponível.

### Sincronização

```php
$result = Forms::syncFromDirectory(storage_path('app/formsJson'));
```

Sincronização é facade apenas. Não há model já resolvido que represente naturalmente essa operação.

## Relação com métodos diretos

A facade oferece facilidade. Métodos diretos nos models oferecem flexibilidade.

Quando as duas formas existem para o mesmo comportamento, elas são equivalentes e delegam para o mesmo serviço interno. Se não houver justificativa clara para manter as duas formas públicas, apenas uma fica exposta.

Consulte o mapa completo em [Equivalência entre facade e models](equivalencia_facade_model.md).
