# Decisões da V2

Este documento registra decisões arquiteturais tomadas durante a evolução para a versão 2 da biblioteca. Ele também documenta alternativas descartadas para evitar que voltem como ambiguidades durante a implementação.

## Forms como API oficial

A facade `Uspdev\Forms\Facades\Forms` é a porta pública oficial da biblioteca. Ela encapsula `FormsManager` e evita que consumidores externos dependam da classe interna `Form`.

A facade não concentra toda a implementação. Ela é a entrada estável para quem usa o pacote. Internamente, as responsabilidades ficam separadas em serviços próprios para definição, submissão, renderização e arquivos.

## Divisão entre definição e submissão

`FormDefinition` representa a estrutura e o versionamento do formulário. Ela responde por `name`, `version`, `status`, `group`, `description` e `fields`.

`FormSubmission` representa os dados submetidos. Ela guarda `form_definition_id`, `user_id`, `key` e `data`, além de auditoria e soft delete. A versão usada por uma submissão vem sempre da relação com `formDefinition`, não de uma coluna própria de versão.

## Facade e métodos diretos nos models

A V2 pode oferecer duas entradas públicas para o mesmo comportamento quando houver justificativa real: uma via facade e outra direta no model.

A facade oferece facilidade. Ela é indicada para fluxos de alto nível, resolução de definição, resolução de versão ativa, consultas, submissões e sincronização.

Métodos diretos nos models oferecem flexibilidade. Eles são indicados quando a aplicação já tem uma entidade carregada e a operação pertence naturalmente a essa entidade.

A classificação entre fluxos apenas via facade, fluxos disponíveis via facade e model, e fluxos apenas via model está documentada em [API via facade e API direta: diferenças e equivalências](api/api_direta_facade_diferencas_equivalencias.md). Os detalhes de cada entrada pública ficam em [API via facade](api/api_facade.md) e [API direta](api/api_direta.md).

Quando duas formas públicas existem para o mesmo comportamento, elas devem usar a mesma implementação interna, retornar o mesmo tipo, lançar as mesmas exceções e ter testes de equivalência.

## Form como implementação interna

`Uspdev\Forms\Form` permanece no pacote apenas como objeto interno de contexto para as views e para regras estáticas de validação reaproveitadas pelos serviços.

Ela não é API pública recomendada e não concentra mais fluxos de submissão, renderização, consulta, upload, download ou auditoria. Essas responsabilidades ficam em `FormsManager`, `FormRendererService`, `FormSubmissionService`, `FormSubmissionFileService` e nos models públicos.

## Versionamento aprovado

`form_definitions` passa a usar `name + version`.

Motivos:

* permitir mais de uma versão concreta para o mesmo formulário lógico;
* preservar a renderização de submissões antigas pela `formDefinition` usada no envio;
* permitir que sistemas consumidores, como `workflow`, referenciem uma versão explícita de formulário em transições.

## Uso da versão ativa quando version é omitida

Chamadas públicas que recebem `name` e `version` podem omitir `version`. Nesse caso, a biblioteca usa a versão ativa do formulário.

Essa regra melhora a ergonomia para casos comuns, mas consumidores que exigem reprodutibilidade informam a versão explicitamente.

## Proteção da versão ativa no banco

A regra de no máximo uma versão ativa por `name` é parte do contrato público da V2. Por isso, ela não fica apenas no service/model.

A migration também cria uma proteção de banco:

* PostgreSQL e SQLite usam índice único parcial em `name` para linhas com `status = active`;
* MySQL e MariaDB usam uma coluna auxiliar interna e triggers, evitando dependência de generated columns e preservando compatibilidade com versões mais antigas.

Essa decisão adiciona complexidade à migration, mas evita que escritas diretas, jobs concorrentes ou mudanças futuras deixem duas versões ativas para o mesmo formulário lógico.

## Uso sem persistência aprovado

A V2 permite o uso do `forms` sem persistir os dados submetidos.

Esse modo atende aplicações que usam a biblioteca como componente de renderização e validação, mas processam os dados fora de `form_submissions`.

Regra definida:

* `Forms::render()` renderiza o formulário.
* `Forms::validate()` valida e retorna os dados validados sem persistir.
* `Forms::submit()` valida e cria `FormSubmission`.
* `Forms::update()` valida e atualiza `FormSubmission`.

## DTOs barrados

DTOs não foram introduzidos na V2.

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
* a consulta pública de auditoria é feita por `Forms::submissionActivities()` e `Forms::activity()`;
* histórico próprio só é reavaliado se houver necessidade de diff estruturado, rollback, snapshots completos por edição ou auditoria independente do Spatie.
