# Especificação — Forms V2 para consumidores

## Problem Statement

O Forms V2 possui um contrato diferente da integração antiga usada pelo Equivalencia. A versão nova organiza o acesso por uma API pública, introduz versionamento de `form_definitions` e concentra as migrations das próprias tabelas no pacote. O consumidor ainda possui migrations copiadas localmente e referências a classes internas ou a comportamentos anteriores.

O Equivalencia está em produção e seus dados operacionais precisam ser preservados, mas as tabelas de Forms não são utilizadas pelo fluxo operacional atual e foram aprovadas para regeneração controlada. A integração também precisa considerar que o Equivalencia está atualmente em Laravel 11, enquanto a implementação V2 exige Laravel 12.

Esta especificação define o contrato que um consumidor deve seguir. A decisão de regenerar as tabelas e o procedimento de implantação ficam documentados no repositório Equivalencia; o Workflow registra separadamente como uma transição referencia um formulário.

## Solution

Adotar o Forms V2 como única API de integração e como único dono das migrations e tabelas de Forms. O consumidor localizará definições por nome e versão, usará a versão ativa quando apropriado, executará renderização/validação/submissão por serviços públicos e manterá as submissões vinculadas à versão efetivamente utilizada.

A migration nova do Forms V2 acrescenta ao schema de definições os dados de versão e status, índices necessários e a proteção para uma única versão ativa por nome. Ela não recria o banco inteiro nem exige `migrate:fresh`; no cenário do Equivalencia, as tabelas foram aprovadas para regeneração explícita e a migration do pacote será aplicada sobre o schema recriado.

No cenário específico do Equivalencia, a adoção será feita depois de resolver a compatibilidade Laravel 11/Laravel 12 e depois da regeneração controlada das tabelas autorizadas. Não haverá backfill de dados de Forms nesta implantação, porque não existem dados operacionais de Forms a preservar no fluxo de produção em questão.

## User Stories

1. Como consumidor do Forms, quero usar uma API pública estável, para não acoplar a aplicação a classes internas do pacote.

2. Como consumidor do Forms, quero localizar uma definição pelo nome, para atender o caso comum sem conhecer a estrutura interna do pacote.

3. Como consumidor do Forms, quero informar uma versão exata, para renderizar e validar uma definição histórica ou explicitamente escolhida.

4. Como consumidor do Forms, quero omitir a versão quando desejo a versão ativa, para simplificar o uso normal.

5. Como responsável por definições, quero ter no máximo uma versão ativa por nome, para evitar resolução ambígua.

6. Como responsável por definições, quero distinguir `draft`, `active` e `disabled`, para controlar o ciclo de vida de uma definição de Forms.

7. Como responsável por submissões, quero que cada submissão mantenha vínculo com a definição e a versão utilizadas, para que o significado dos dados não mude retroativamente.

8. Como operador, quero consultar e validar uma definição por meio do contrato V2, para que renderização e persistência usem as mesmas regras.

9. Como operador, quero receber erros claros de definição ou validação, para corrigir o formulário sem depender de comportamento implícito.

10. Como responsável por auditoria, quero manter o registro de atividade associado às operações do Forms, para rastrear alterações e submissões conforme o mecanismo do pacote.

11. Como responsável pelo schema, quero que o pacote seja o único dono das migrations de Forms, para eliminar cópias divergentes nos consumidores.

12. Como responsável pelo deploy, quero que as migrations V2 sejam carregadas diretamente pelo provider do pacote, para não depender de publicação ou cópia manual de migrations.

13. Como consumidor do Workflow, quero que uma transição possa indicar um formulário pelo nome, para integrar o formulário de observação ao processo.

14. Como consumidor do Workflow, quero que uma transição sem formulário não invoque o Forms, para que ações sem dados adicionais permaneçam simples.

15. Como responsável pela compatibilidade, quero resolver o requisito Laravel 12 antes de ativar o Forms V2, para não executar uma biblioteca incompatível com a aplicação.

16. Como responsável pela produção, quero regenerar somente as tabelas de Forms autorizadas, para manter intactos os dados operacionais do Equivalencia.

17. Como responsável pela migração, quero manter migrations históricas sem alteração, para preservar a leitura do histórico do banco.

18. Como responsável pelo escopo, quero evitar backfill de dados de Forms nesta implantação, porque as tabelas não têm dados operacionais a preservar no cenário aprovado.

## Implementation Decisions

- A facade e os serviços públicos do Forms V2 são a API de integração. A classe interna de formulário não será usada diretamente pelo Equivalencia ou pelo Workflow.

- Uma definição é identificada por `name + version`. Quando a versão for omitida, a resolução usará a versão `active`; quando for informada, a versão exata será procurada.

- O Forms V2 mantém os estados `draft`, `active` e `disabled`. A restrição do Workflow de não permitir `draft` em definições executáveis é uma regra do Workflow, não uma alteração no ciclo de vida geral do Forms.

- A ativação é determinada pelo status. `published_at` não é requisito funcional para que um consumidor resolva a versão ativa; nenhum fluxo do Equivalencia dependerá desse campo.

- O banco deve impedir mais de uma versão ativa para o mesmo nome. A forma específica de implementar essa proteção pertence às migrations e ao código do pacote.

- A submissão permanece vinculada à definição e versão usadas no envio. Não será criada uma tabela adicional de histórico de submissões nesta especificação.

- A auditoria de Forms permanece sob responsabilidade do pacote e de seu mecanismo de activity log. O consumidor não duplicará a lógica de auditoria.

- Forms é o único dono das migrations e tabelas de `form_definitions`, `form_submissions` e `activity_log`. Consumidores não devem manter cópias locais dessas migrations.

- As migrations V2 são migrations do pacote, carregadas diretamente pelo provider responsável. Elas não devem ser publicadas e copiadas para o repositório consumidor.

- Migrations novas do Forms V2 podem ser ajustadas antes da implantação.

- Migrations históricas já registradas no consumidor não serão alteradas. A remoção das cópias locais e a regeneração das tabelas são decisões de implantação do Equivalencia, documentadas separadamente.

- Para o Equivalencia, as tabelas de Forms aprovadas para descarte e regeneração são `form_definitions`, `form_submissions` e `activity_log`. Não há backfill nesta implantação.

- A adoção do Forms V2 depende de compatibilidade com Laravel 12. O consumidor não deve tratar a execução em Laravel 11 como compatível por aproximação.

- O Workflow resolve um nome de formulário por meio da API pública do Forms. Formulário omitido, nulo ou falso significa ausência de formulário e não deve gerar uma definição ou submissão artificial.

- Nenhum detalhe de notifications é definido pelo Forms V2 nesta especificação. A integração de notificações pertence ao contrato do Workflow e sua implementação técnica será definida por Lucas.

- Esta etapa é documental. A implementação, a remoção das cópias e a alteração de dependências serão realizadas somente após a revisão das especificações e do plano de implantação.

## Testing Decisions

- Testar a API pública do Forms para localizar uma definição por nome, localizar por versão explícita, selecionar a versão ativa e rejeitar resolução ambígua.

- Testar a regra de uma única versão ativa por nome e as transições entre `draft`, `active` e `disabled`.

- Testar renderização, validação, submissão e consulta usando os serviços públicos, sem chamadas diretas à classe interna de formulário.

- Testar que uma submissão referencia a versão correta da definição e que o registro de auditoria é produzido pelo mecanismo do pacote.

- No Equivalencia, executar os testes de integração sobre a cópia recente do banco de produção após a regeneração controlada. Registrar que dados de aproveitamentos automáticos permaneceram intactos.

- Confirmar que o provider do Forms carrega suas migrations V2 sem depender de migrations copiadas pelo consumidor.

- Confirmar o requisito de versão do Laravel antes de considerar a integração válida.

- Testar a fronteira com Workflow: transição com `obs` resolve o formulário; transição sem formulário não chama o Forms.

## Out of Scope

- Refactor do Workflow ou definição de seu schema.
- Alteração das documentações existentes do Workflow.
- Backfill ou conversão de dados legados de Forms no Equivalencia nesta implantação.
- Criação de `form_submission_history`.
- Definição técnica de eventos, filas, Mailables ou retries de notifications.
- Migração de produção ou execução de `migrate:fresh`.
- Alterações de código nesta etapa documental.

## Further Notes

- O contrato de integração deve ser lido junto com a especificação de migração do Equivalencia e com a especificação do Workflow V2. Cada repositório documenta somente as decisões sob sua responsabilidade.

- A regeneração aprovada das tabelas de Forms não significa que toda tabela do banco possa ser descartada. Dados operacionais do Equivalencia continuam fora do escopo.

- A seleção, inclusão e commit do documento ficam sob responsabilidade do desenvolvedor do repositório Forms.
