# Equivalência entre facade e models

Esta página explicita quais métodos públicos existem via facade, quais existem diretamente nos models e quais existem nos dois lados.

A regra geral é:

* a facade `Forms` funciona como API de alto nível, com resolução de definição, versão ativa, submissão ou fluxo completo;
* métodos diretos entram quando já há uma `FormDefinition` ou `FormSubmission` carregada;
* quando as duas formas existem, elas delegam para o mesmo serviço interno, retornam o mesmo tipo e lançam as mesmas exceções.

## Métodos equivalentes

Estes comportamentos têm entrada via facade e entrada direta no model.

| Facade | Model | Quando preferir facade | Quando preferir model |
| ------ | ----- | ---------------------- | --------------------- |
| `Forms::render($name, $versionOrOptions = null, $options = [], $submission = null)` | `$definition->render($options = [], $submission = null)` | Quando a aplicação resolve a definição por `name + version` ou versão ativa. | Quando já existe uma `FormDefinition`. |
| `Forms::validate($request, $name = null, $version = null)` | `$definition->validateData($request)` | Quando a aplicação resolve a definição a partir do request, `name` ou `version`. | Quando já se sabe exatamente qual definição valida os dados. |
| `Forms::submit($request)` | `$definition->submit($request)` | Quando o request identifica o formulário e a biblioteca resolve a definição. | Quando a submissão é criada a partir de uma definição já carregada. |
| `Forms::update($request, $submission)` | `$submission->updateFromRequest($request)` | Quando a aplicação padroniza operações por facade ou tem apenas o id/model da submissão. | Quando já existe uma `FormSubmission` e a atualização é direta. |
| `Forms::downloadFile($submission, $field)` | `$submission->download($field)` | Quando a aplicação padroniza operações por facade ou recebe a submissão como parâmetro. | Quando a operação ocorre sobre uma submissão concreta. |
| `Forms::deleteSubmission($submission, $user = null)` | `$submission->deleteWithActivity($user = null)` | Quando a aplicação padroniza operações por facade ou quer aceitar id/model. | Quando a exclusão ocorre sobre uma submissão já carregada. |

## Métodos apenas via facade

Estes métodos não possuem equivalente direto em model porque localizam, resolvem ou consultam entidades, em vez de operar naturalmente sobre uma entidade já carregada.

| Facade | Por que não tem equivalente direto |
| ------ | ---------------------------------- |
| `Forms::definition($name, $version = null)` | O objetivo é localizar uma `FormDefinition`. Antes da chamada, ainda não há model carregado. |
| `Forms::activeDefinition($name)` | O objetivo é resolver a versão ativa de um formulário lógico. |
| `Forms::definitions($group = null)` | O objetivo é listar definições, opcionalmente por grupo. |
| `Forms::submission($id)` | O objetivo é localizar uma `FormSubmission`. Antes da chamada, ainda não há submissão carregada. |
| `Forms::submissions($name, $version = null, $key = null)` | O objetivo é consultar submissões por definição resolvida e chave. |
| `Forms::filterSubmissions($name, $version = null, $field = null, $operator = null, $value = null, $key = null)` | O objetivo é consultar submissões a partir de critérios externos. |
| `Forms::syncFromDirectory($directory)` | Sincronização lê arquivos JSON e cria ou atualiza definições; não pertence a uma entidade específica já carregada. |

## Métodos apenas via model

Estes métodos não possuem equivalente na facade porque são comportamento natural de uma entidade já carregada.

| Model | Por que não tem equivalente na facade |
| ----- | ------------------------------------ |
| `$submission->showHtml($longName = false, $isAdmin = false)` | É a visualização de uma submissão concreta usando sua `formDefinition`. A facade não funciona como entrada para esse detalhe de apresentação. |
| `$submission->formDefinition` | É relacionamento Eloquent entre submissão e definição. |
| `$submission->user` | É relacionamento Eloquent entre submissão e usuário. |
| `$definition->formSubmissions()` | É relacionamento Eloquent entre definição e submissões. |
| `$definition->flattenFields()` | É utilitário interno/natural da definição para trabalhar com seus campos. |

## Regra para novos métodos

Ao adicionar um método público novo, classifique-o antes de implementar:

1. **Facade apenas**: quando a operação localiza, resolve ou orquestra entidades.
2. **Facade e model**: quando as duas formas têm uso real para consumidores diferentes.
3. **Model apenas**: quando a operação pertence claramente a uma entidade já carregada.

Se um método é disponibilizado nos dois lados, a implementação reutiliza um serviço interno compartilhado. Não há duplicação de regra de negócio na facade e no model.
