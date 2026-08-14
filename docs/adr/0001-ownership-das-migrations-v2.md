# ADR 0001 — Ownership das migrations V2 do Forms

- Status: aceito
- Data: 2026-08-14

## Contexto

O Forms possui migrations copiadas para aplicações consumidoras, enquanto o `FormServiceProvider` não carregava as migrations diretamente. Esse modelo permitiu que o Equivalencia mantivesse cópias concorrentes das migrations do pacote.

Além das tabelas de definições e submissões, o Forms utiliza `spatie/laravel-activitylog` para auditar `FormSubmission`. Por isso, `activity_log` faz parte do escopo de ownership do Forms nesta integração.

## Decisão

O Forms será o único dono das tabelas `form_definitions`, `form_submissions` e `activity_log`.

As migrations V2:

- ficarão em uma pasta dedicada `database/migrations/v2`;
- criarão o schema completo do Forms V2;
- não farão normalização ou backfill de dados legados nesta implantação;
- serão carregadas diretamente pelo `FormServiceProvider`;
- não serão publicadas para cópia nas aplicações consumidoras.

As migrations históricas permanecerão como histórico e não serão alteradas nem recarregadas pelo conjunto V2. Aplicações consumidoras devem remover cópias locais e executar as migrations fornecidas pelo pacote.

## Consequências

- O schema de Forms possui um único dono.
- O Equivalencia não precisa manter cópias de migrations do Forms.
- `activity_log` é regenerado junto com o Forms nesta implantação, conforme decisão do consumidor.
- Consumidores que já tenham tabelas e migrations históricas precisam executar uma transição própria antes do schema V2.
- O pacote passa a controlar diretamente a ordem e o conteúdo das migrations V2.

## Alternativas consideradas

- Continuar publicando migrations para cada aplicação: rejeitado porque mantém duplicação e permite divergência.
- Manter `activity_log` sob ownership da aplicação: rejeitado para o Equivalencia porque o único consumidor identificado é o Forms.
- Fazer uma migration genérica de normalização de dados legados: fora do escopo desta implantação, que não possui dados operacionais de Forms a preservar.
