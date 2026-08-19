# Submissões, auditoria e relacionamento com definições

## form_submissions

Contém as submissões de formulários.

| Campo | Tipo/Observação |
| ----- | --------------- |
| id | PK |
| form_definition_id | FK para `form_definitions` |
| user_id | usuário associado, nullable |
| key | chave controlada pela aplicação |
| data | JSON com os dados submetidos |
| created_at | timestamp |
| updated_at | timestamp |
| deleted_at | soft delete |

## Regras

* `FormSubmission` dispensa coluna `version`.
* A versão usada por uma submissão é determinada por `form_definition_id`.
* Ao criar submissão, a API resolve a definição por `name + version` ou pela versão ativa quando `version` for omitida. `name` e `version` podem vir do HTML renderizado ou ser passados explicitamente a `Forms::submit()`.
* Quando id, nome e versão forem informados juntos, todos devem apontar para a mesma definição. A API rejeita divergências antes de persistir.
* Ao renderizar ou visualizar uma submissão existente, a biblioteca usa `$submission->formDefinition`, nunca a versão ativa por `name`.
* Submissões antigas continuam renderizando com a definição exata usada no momento do envio.

## Auditoria

A auditoria operacional de submissões continua usando `spatie/laravel-activitylog`.

As consultas públicas de auditoria ficam na facade:

```php
$activities = Forms::submissionActivities($submission, 20);
$activity = Forms::activity($activityId);
```

`submissionActivities()` aceita uma `FormSubmission` ou um id de submissão e
retorna as activities mais recentes, sempre filtradas pelo morph type de
`FormSubmission`. `activity()` retorna apenas uma activity cujo subject seja
uma submissão. Ids inexistentes ou activities de outros tipos lançam
`ModelNotFoundException`. A consulta por id inclui submissões removidas por
soft delete, preservando o acesso ao evento de exclusão.

Não existe `form_submission_history` nesta versão. O histórico próprio só é reavaliado se houver necessidade de diff estruturado, rollback, snapshots completos por edição ou auditoria independente do Spatie.

## Relacionamento

```mermaid
erDiagram
    FORM_DEFINITIONS ||--o{ FORM_SUBMISSIONS : "possui"
    FORM_DEFINITIONS {
        bigint id
        string name
        int version
        string status
        string group
        string description
        json fields
    }
    FORM_SUBMISSIONS {
        bigint id
        bigint form_definition_id
        bigint user_id
        string key
        json data
    }
```
