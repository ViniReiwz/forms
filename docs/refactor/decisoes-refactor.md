# Decisões do refactor

Este documento registra decisões arquiteturais tomadas durante o refactor. Ele também documenta alternativas descartadas para evitar que voltem como ambiguidades durante a implementação.

## Forms como API oficial

A facade `Uspdev\Forms\Facades\Forms` é a porta pública oficial da biblioteca. Ela encapsula `FormsService` e evita que consumidores externos dependam da classe interna `Form`.

A facade não concentra toda a implementação. Ela é a entrada estável para quem usa o pacote. Internamente, as responsabilidades ficam separadas em serviços próprios para definição, submissão, renderização e arquivos.

## Divisão entre definição e submissão

`FormDefinition` representa a estrutura e o versionamento do formulário. Ela responde por `name`, `version`, `status`, `group`, `description` e `fields`.

`FormSubmission` representa os dados submetidos. Ela guarda `form_definition_id`, `user_id`, `key` e `data`, além de auditoria e soft delete. A versão usada por uma submissão vem sempre da relação com `formDefinition`, não de uma coluna própria de versão.

## Facade e métodos diretos nos models

A V2 pode oferecer duas entradas públicas para o mesmo comportamento quando houver justificativa real: uma via facade e outra direta no model.

A facade oferece facilidade. Ela é indicada para fluxos de alto nível, resolução de definição, resolução de versão ativa, consultas, submissões e sincronização.

Métodos diretos nos models oferecem flexibilidade. Eles são indicados quando a aplicação já tem uma entidade carregada e a operação pertence naturalmente a essa entidade.

Os métodos públicos ficam classificados em três grupos.

### Facade apenas

Métodos facade apenas são aqueles em que não há entidade já resolvida que represente naturalmente a operação, ou em que a operação existe para localizar/orquestrar outros objetos.

Exemplos:

```php
Forms::definition('parecer_final');
Forms::definition('parecer_final', 2);
Forms::activeDefinition('parecer_final');
Forms::definitions('workflow');
Forms::submission($id);
Forms::submissions('parecer_final', key: 'workflow-123');
Forms::filterSubmissions(...);
Forms::syncFromDirectory($path);
```

### Facade e model

Métodos podem existir nas duas formas quando ambas forem úteis para consumidores diferentes.

Exemplos:

```php
Forms::render('parecer_final', 2, $options);
$definition->render($options);

Forms::validate($request, 'parecer_final', 2);
$definition->validateData($request);

Forms::submit($request);
$definition->submit($request);

Forms::update($request, $submission);
$submission->updateFromRequest($request);

Forms::downloadFile($submission, 'arquivo');
$submission->download('arquivo');

Forms::deleteSubmission($submission, auth()->user());
$submission->deleteWithActivity(auth()->user());
```

Quando duas formas públicas existem para o mesmo comportamento, elas usam a mesma implementação interna, retornam o mesmo tipo, lançam as mesmas exceções e têm testes de equivalência.

### Model apenas

Métodos model apenas são aqueles que representam comportamento próprio de uma entidade já carregada e dispensam uma entrada global pela facade.

Exemplos:

```php
$submission->showHtml();
$submission->formDefinition;
$definition->formSubmissions();
```

Se não houver justificativa plausível para disponibilizar um comportamento nas duas formas, ele não é duplicado publicamente.

## Form como implementação interna

`Uspdev\Forms\Form` permanece no pacote para concentrar ou reaproveitar regras existentes de renderização, validação de submissão, upload e download. Ela não aparece como API recomendada na documentação pública.

## Versionamento aprovado

`form_definitions` passa a usar `name + version`.

Motivos:

* permitir mais de uma versão concreta para o mesmo formulário lógico;
* preservar a renderização de submissões antigas pela `formDefinition` usada no envio;
* permitir que sistemas consumidores, como `workflow`, referenciem uma versão explícita de formulário em transições.

## Uso da versão ativa quando version é omitida

Chamadas públicas que recebem `name` e `version` podem omitir `version`. Nesse caso, a biblioteca usa a versão ativa do formulário.

Essa regra melhora a ergonomia para casos comuns, mas consumidores que exigem reprodutibilidade informam a versão explicitamente.

## Uso sem persistência aprovado

A V2 permite o uso do `forms` sem persistir os dados submetidos.

Esse modo atende aplicações que usam a biblioteca como componente de renderização e validação, mas processam os dados fora de `form_submissions`.

Regra definida:

* `Forms::render()` renderiza o formulário.
* `Forms::validate()` valida e retorna os dados validados sem persistir.
* `Forms::submit()` valida e cria `FormSubmission`.
* `Forms::update()` valida e atualiza `FormSubmission`.

## DTOs barrados

DTOs não foram introduzidos neste refactor.

Justificativa:

* a estrutura de `forms` é mais linear que a de um motor de workflow;
* não há grafo de estados, transições, bindings e roles interdependentes;
* DTOs adicionariam cerimônia sem ganho proporcional;
* o ganho real está na validação estrutural centralizada em `FormDefinitionSchemaValidator`.

## form_submission_history barrado por agora

Não foi criada tabela `form_submission_history`.

Justificativa:

* `FormSubmission` já representa o dado submetido;
* a auditoria operacional existente continua usando `spatie/laravel-activitylog`;
* histórico próprio só é reavaliado se houver necessidade de diff estruturado, rollback, snapshots completos por edição ou auditoria independente do Spatie.
