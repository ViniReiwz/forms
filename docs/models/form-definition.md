# Definição de form_definition

Uma `form_definition` descreve um formulário e sua estrutura de campos. Cada registro representa uma versão concreta de um formulário lógico.

## Campos da tabela

| Campo | Tipo/Observação |
| ----- | --------------- |
| id | PK |
| name | string obrigatório; identifica o formulário lógico |
| version | inteiro positivo obrigatório; identifica uma versão concreta |
| status | obrigatório; `draft`, `active` ou `disabled`; indica o estado da versão |
| group | string |
| description | string nullable |
| fields | JSON com a estrutura dos campos |
| created_at | timestamp |
| updated_at | timestamp |

## Papel de `group`

`group` é atualmente um metadado de organização e catalogação da definição. Ele permite filtrar definições pela API (`Forms::definitions($group)`), é exibido nas telas administrativas e de backup, é preservado nos JSONs de backup e sincronização e possui índice no banco para facilitar esse filtro.

Esse campo não participa da resolução do formulário, que usa `name`, `version` e `status`, nem da renderização, da validação ou persistência de submissões, do versionamento, da autorização ou da identificação da submissão.

A obrigatoriedade de `group` está em aberto: hoje ela é aplicada pelo formulário administrativo, pelo validador e pelo banco, mas sua permanência só se justifica como contrato deliberado de organização entre os consumidores. Caso esse contrato não seja necessário, o campo poderá se tornar opcional ou receber um valor padrão explícito em uma decisão futura.

## Regras de versionamento

* `name` não é único isoladamente.
* `name + version` é único no banco.
* Existe no máximo uma versão ativa para cada `name`.
* Quando uma versão é marcada como ativa, as demais versões do mesmo `name` são desativadas pelo service/model antes do salvamento.
* O banco também protege a regra de uma única versão ativa por `name`.
* Sistemas externos podem referenciar uma versão explícita com `name + version`.
* Quando `version` é omitida em chamadas públicas, a biblioteca usa a versão ativa do `name`.

## Estrutura JSON

```json
{
  "name": "parecer_final",
  "version": 2,
  "status": "active",
  "group": "workflow",
  "description": "Parecer final",
  "fields": [
    {
      "name": "titulo",
      "type": "text",
      "label": "Título",
      "required": true,
      "validation_rule": "max:150",
      "width": 6
    },
    [
      {
        "name": "email",
        "type": "email",
        "label": "Email"
      },
      {
        "name": "resultado",
        "type": "select",
        "label": "Resultado",
        "options": [
          "aprovado",
          "reprovado"
        ]
      }
    ]
  ]
}
```

## Campos da definição

| Campo | Tipo/Observação |
| ----- | --------------- |
| name | obrigatório, string |
| version | obrigatório, inteiro positivo |
| status | obrigatório; `draft`, `active` ou `disabled` |
| group | obrigatório, string |
| description | string nullable |
| fields | array obrigatório |

## Campos de `fields`

| Campo | Tipo/Observação |
| ----- | --------------- |
| name | obrigatório e único na definição, exceto em `separator` |
| type | obrigatório |
| label | texto exibido; campos sem label são tratados como administrativos em visualização |
| required | boolean opcional |
| validation_rule | regra adicional de validação Laravel |
| options | array obrigatório para `select` |
| width | inteiro de 1 a 12 para grid Bootstrap |
| accept | string opcional para `file` |

## O que é `separator`

`separator` é um tipo de campo visual usado para separar seções do formulário. Ele não coleta dados, não gera entrada submetida e por isso dispensa `name`.

Exemplo:

```json
{
  "type": "separator",
  "label": "Dados do parecer"
}
```

## Tipos suportados

* `checkbox`
* `date`
* `disciplina-usp`
* `email`
* `file`
* `hidden`
* `local-usp`
* `number`
* `patrimonio-usp`
* `pessoa-usp`
* `select`
* `separator`
* `textarea`
* `text`
* `time`
* `url`

## Linhas com múltiplos campos

Um item de `fields` pode ser um campo único ou uma lista de campos. Listas são renderizadas na mesma linha.

```json
[
  {
    "name": "nome",
    "type": "text",
    "label": "Nome"
  },
  [
    {
      "name": "email",
      "type": "email",
      "label": "Email"
    },
    {
      "name": "telefone",
      "type": "text",
      "label": "Telefone"
    }
  ]
]
```

## Campos USP

Os tipos `pessoa-usp`, `disciplina-usp`, `patrimonio-usp` e `local-usp` dependem do pacote `uspdev/replicado` e das rotas de busca disponibilizadas pela biblioteca.
