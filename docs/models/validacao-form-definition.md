# Validação de form_definition

`Uspdev\Forms\Services\FormDefinitionSchemaValidator` valida a estrutura de uma definição antes de ela ser persistida ou sincronizada.

## Pontos de integração

* `FormDefinition::saving`
* `FormDefinitionSyncService`
* `DefinitionController::store`
* `DefinitionController::update`

## Regras principais

| Item | Regra |
| ---- | ----- |
| name | obrigatório, string, máximo 255 |
| version | obrigatório, inteiro positivo, não nulo |
| name + version | é único no banco |
| status | obrigatório, não nulo; `draft`, `active` ou `disabled`; default `active` quando omitido no sync |
| active por name | apenas uma versão ativa por `name`, protegida pelo model/service e por restrição de banco |
| group | obrigatório, string, máximo 255 |
| description | nullable, string, máximo 255 |
| fields | obrigatório, array, com ao menos um campo |
| field.name | obrigatório e único, exceto em `separator` |
| field.type | obrigatório e dentro dos tipos suportados |
| field.required | boolean quando informado |
| field.validation_rule | string nullable quando informado |
| field.options | array quando informado; obrigatório em `select` |
| field.width | inteiro entre 1 e 12 |
| field.accept | string nullable quando informado |

### Observação sobre `group`

Embora o validador exija `group` atualmente, ele é tratado como metadado de catálogo: serve para organização e filtragem de definições, não para o funcionamento da engine de formulários ou para a identificação das submissões. A necessidade de mantê-lo obrigatório como contrato entre os consumidores permanece em aberto.

## Comportamento esperado

Definições inválidas lançam `Illuminate\Validation\ValidationException`.

Casos rejeitados incluem:

* definição sem `name`;
* definição sem `version`;
* `version` menor que `1`;
* duplicidade de `name + version`;
* mais de uma versão ativa para o mesmo `name`;
* campo sem `name`, exceto `separator`;
* tipo de campo não suportado;
* nomes de campos duplicados;
* `width` fora de `1..12`;
* `select` sem `options`;
* linhas vazias ou itens de linha que não sejam campos.

## Ativação de versão

Quando uma definição é salva com `status = active`, a biblioteca marca as demais definições com o mesmo `name` como `disabled` antes de salvar a versão ativa. Esse comportamento preserva a regra de uma única versão ativa por formulário lógico e permite que a restrição de banco funcione como proteção adicional.

Em PostgreSQL e SQLite essa proteção usa índice único parcial sobre `name` para linhas com `status = active`. Em MySQL e MariaDB, por compatibilidade com versões antigas, a migration usa uma coluna auxiliar interna e triggers para manter o mesmo efeito.
