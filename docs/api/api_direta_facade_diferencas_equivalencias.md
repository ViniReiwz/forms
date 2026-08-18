# API via facade e API direta: diferenças e equivalências

Esta página centraliza a comparação entre as duas formas públicas de uso do `uspdev/forms`.

* **API via facade**: entrada de alto nível por `Uspdev\Forms\Facades\Forms`, indicada quando a biblioteca resolve definição, versão ativa, submissão, consulta ou sincronização.
* **API direta**: entrada pelos models `FormDefinition` e `FormSubmission`, indicada quando a aplicação já tem a entidade carregada e a ação pertence naturalmente a ela.

## Visão rápida

| Situação | API via facade | API direta |
| -------- | -------------- | ---------- |
| A aplicação tem apenas `name` e talvez `version` | Resolve pela facade | A aplicação carrega a `FormDefinition` antes |
| A aplicação já tem uma `FormDefinition` | Também funciona | É a forma mais expressiva |
| A aplicação já tem uma `FormSubmission` | Também funciona | É a forma mais expressiva |
| A operação consulta ou localiza entidades | Entrada natural | Usa Eloquent ou relacionamento já carregado |
| A operação pertence a uma entidade concreta | Funciona como padronização | Entrada natural |
| A operação sincroniza arquivos JSON | Entrada disponível | Sem equivalente direto |

## Métodos equivalentes

Estes comportamentos existem nas duas abordagens. As chamadas delegam para a mesma implementação interna, retornam o mesmo tipo e lançam as mesmas exceções.

| Fluxo | API via facade | API direta | Diferença prática |
| ----- | -------------- | ---------- | ----------------- |
| Renderizar formulário | `Forms::render($name, $versionOrOptions = null, $options = [], $submission = null)` | `$definition->render($options = [], $submission = null)` | A facade resolve a definição; a API direta parte de uma `FormDefinition`. |
| Validar sem persistir | `Forms::validate($request, $name = null, $version = null)` | `$definition->validateData($request)` | A facade pode resolver a definição pelo request, `name` ou `version`; a API direta já sabe qual definição valida os dados. |
| Criar submissão | `Forms::submit($request, $name = null, $version = null)` | `$definition->submit($request)` | A facade resolve a definição a partir do request ou dos argumentos explícitos; a API direta cria a submissão a partir da definição carregada. |
| Atualizar submissão | `Forms::update($request, $submission)` | `$submission->updateFromRequest($request)` | A facade aceita id/model e padroniza o fluxo; a API direta atualiza a submissão carregada. |
| Baixar arquivo | `Forms::downloadFile($submission, $field)` | `$submission->download($field)` | A facade aceita a submissão como entrada; a API direta parte da submissão concreta. |
| Excluir submissão | `Forms::deleteSubmission($submission, $user = null)` | `$submission->deleteWithActivity($user = null)` | A facade padroniza a chamada; a API direta expressa que a exclusão pertence à submissão. |

## Apenas via facade

Estes fluxos localizam, resolvem, listam, filtram ou sincronizam entidades. Eles não pertencem naturalmente a uma `FormDefinition` ou `FormSubmission` já carregada.

| Método | Por que fica na facade |
| ------ | ---------------------- |
| `Forms::definition($name, $version = null)` | Resolve uma definição por `name + version` ou pela versão ativa e lança `InvalidArgumentException` quando ela não existir. Antes da chamada, ainda não há `FormDefinition` carregada. |
| `Forms::activeDefinition($name)` | Resolve explicitamente a versão ativa de um formulário lógico e lança `InvalidArgumentException` quando ela não existir. |
| `Forms::definitions($group = null)` | Lista definições, opcionalmente por grupo. |
| `Forms::submission($id)` | Localiza uma submissão por id. Antes da chamada, ainda não há `FormSubmission` carregada. |
| `Forms::submissions($name, $version = null, $key = null)` | Consulta submissões a partir de uma definição resolvida por `name` e `version`. |
| `Forms::filterSubmissions($name, $version = null, $field = null, $operator = null, $value = null, $key = null)` | Centraliza filtros por campo JSON, operador, valor e chave. |
| `Forms::submissionActivities($submission, $take = 20)` | Lista as activities mais recentes de uma submissão por id ou model. |
| `Forms::activity($id)` | Localiza uma activity específica por id. |
| `Forms::syncFromDirectory($directory)` | Sincroniza arquivos JSON com `form_definitions`; é uma operação de orquestração sobre arquivos e banco. |

Na API direta, consultas equivalentes podem ser feitas com Eloquent quando a entidade já existe, por exemplo:

```php
$submissions = $definition->formSubmissions()
    ->where('key', 'workflow-123')
    ->get();
```

Essa consulta direta não replica os operadores de `Forms::filterSubmissions()`; ela usa os recursos normais do query builder.

## Apenas via direta

Estes comportamentos são naturais de uma entidade já carregada e não possuem entrada própria pela facade.

| Método ou relação | Por que fica na API direta |
| ----------------- | -------------------------- |
| `$submission->showHtml($longName = false, $isAdmin = false)` | Renderiza a visualização de uma submissão concreta usando sua `formDefinition`. |
| `$submission->formDefinition` | Relacionamento Eloquent entre submissão e definição. |
| `$submission->user` | Relacionamento Eloquent entre submissão e usuário. |
| `$definition->formSubmissions()` | Relacionamento Eloquent entre definição e submissões. |
| `$definition->flattenFields()` | Utilitário da definição para trabalhar com seus campos. |

## Escolha da abordagem

| Se a aplicação... | Abordagem mais direta |
| ---------------- | --------------------- |
| só tem `name`, `version`, id ou dados externos do request | API via facade |
| quer padronizar fluxos públicos por uma porta única | API via facade |
| precisa sincronizar definições em JSON | API via facade |
| já carregou uma `FormDefinition` | API direta |
| já carregou uma `FormSubmission` | API direta |
| está trabalhando com relacionamentos Eloquent | API direta |

## Regra para novos métodos

Novos métodos públicos seguem a mesma separação:

1. **Facade apenas**: quando a operação localiza, resolve, lista, filtra ou orquestra entidades.
2. **Facade e direta**: quando as duas formas têm uso real para consumidores diferentes.
3. **Direta apenas**: quando a operação pertence claramente a uma entidade já carregada.

Quando um comportamento existe nas duas abordagens, a implementação reutiliza um serviço interno compartilhado. Não há duplicação de regra de negócio na facade e no model.
