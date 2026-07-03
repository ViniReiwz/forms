# API pública

A porta oficial da biblioteca é `Uspdev\Forms\Facades\Forms`.

```php
use Uspdev\Forms\Facades\Forms;
```

Esta página resume a API pública. A relação entre facade e métodos diretos nos models aparece em:

* [API via facade Forms](facade_forms.md)
* [Métodos diretos nos models](metodos_diretos.md)
* [Equivalência entre facade e models](equivalencia_facade_model.md)

## Regra de versão

Métodos que recebem `name` e `version` aceitam a versão como parâmetro opcional. Quando `version` é omitida, a biblioteca usa a versão ativa daquele `name`.

Versão explícita aparece quando a operação é reprodutível ou presa a uma definição concreta. Versão omitida aparece quando o objetivo é sempre trabalhar com a versão ativa.

## Definições

Versão ativa:

```php
$definition = Forms::definition('parecer_final');
```

Versão explícita:

```php
$definition = Forms::definition('parecer_final', 2);
```

Versão ativa de forma explícita:

```php
$definition = Forms::activeDefinition('parecer_final');
```

Definições por grupo:

```php
$definitions = Forms::definitions('workflow');
```

## Renderização

Renderize a versão ativa:

```php
$html = Forms::render('parecer_final', [
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);
```

Renderize uma versão explícita:

```php
$html = Forms::render('parecer_final', 2, [
    'action' => route('pareceres.store'),
    'method' => 'POST',
]);
```

Para edição, passe a submissão. A renderização usa a definição relacionada à submissão, mesmo que uma versão ativa mais recente exista.

```php
$html = Forms::render('parecer_final', ['method' => 'PUT'], $submission);
```

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

A biblioteca também pode ser usada apenas para renderizar e validar formulários, sem persistir os dados submetidos em `form_submissions`.

Esse modo aparece quando a aplicação usa o `forms` como componente de interface e validação, mas processa os dados por conta própria, por exemplo:

* enviar os dados para outra API;
* alimentar uma regra de negócio própria;
* salvar em uma tabela específica da aplicação;
* usar os dados dentro de uma transição de workflow sem criar uma submissão persistida.

Renderize normalmente:

```php
$html = Forms::render('parecer_final', [
    'action' => route('parecer.preview'),
    'method' => 'POST',
]);
```

Valide sem persistir:

```php
$validated = Forms::validate($request);
```

Nesse formato, a biblioteca resolve a definição a partir dos dados do request. Se o request não trouxer a identificação do formulário, a chamada recebe `name` e, se necessário, `version`.

Nome e versão ativa:

```php
$validated = Forms::validate($request, 'parecer_final');
```

Nome e versão explícita:

```php
$validated = Forms::validate($request, 'parecer_final', 1);
```

`Forms::validate()` retorna os dados validados ou lança `ValidationException`. Ele não cria nem atualiza `FormSubmission`.

`Forms::submit()` e `Forms::update()` ficam para persistência em `form_submissions`.

## Filtros

```php
$submissions = Forms::filterSubmissions(
    'parecer_final',
    field: 'resultado',
    operator: '==',
    value: 'aprovado',
    key: 'workflow-123'
);
```

Com versão explícita:

```php
$submissions = Forms::filterSubmissions(
    'parecer_final',
    2,
    'resultado',
    '==',
    'aprovado',
    'workflow-123'
);
```

### Operadores suportados

| Operador | Significado |
| -------- | ----------- |
| `contains` | busca se o valor informado está contido no campo JSON |
| `==` | igualdade |
| `!=` | diferença simples |
| `empty` | campo nulo ou string vazia |
| `not_empty` | campo não nulo e diferente de string vazia |

O operador `==` faz comparação de igualdade. O operador `=` não é aceito pela API pública.

## Arquivos

```php
return Forms::downloadFile($submission, 'arquivo');
```

## Exclusão

```php
$deleted = Forms::deleteSubmission($submission, auth()->user());
```

## Sincronização

```php
$result = Forms::syncFromDirectory(storage_path('app/formsJson'));
```

`syncFromDirectory` lê arquivos `.json` de um diretório e sincroniza as definições com a tabela `form_definitions`.

O método faz o seguinte:

* lê apenas arquivos JSON do diretório informado;
* valida cada definição com `FormDefinitionSchemaValidator`;
* cria ou atualiza registros usando `name + version`;
* quando um JSON vem com `status = active`, desativa as outras versões do mesmo `name`;
* retorna um resumo com arquivos processados, criados, atualizados, ignorados e erros.

Esse método é útil para manter definições versionadas em arquivos do projeto e publicá-las no banco durante deploy, setup local ou atualização controlada de ambientes.
