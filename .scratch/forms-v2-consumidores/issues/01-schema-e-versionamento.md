# 01 — Consolidar schema e versionamento do Forms V2

**What to build:** O Forms V2 passa a ser o dono efetivo do próprio schema e consegue armazenar e resolver definições por nome e versão, sem depender de migrations copiadas pelos consumidores.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] As migrations V2 criam o schema necessário para definições, versões, status, índices e controle de uma única versão ativa por nome.
- [ ] A resolução por nome usa a versão `active`; a resolução com versão informada busca a versão exata.
- [ ] Os estados `draft`, `active` e `disabled` têm comportamento consistente.
- [ ] O provider do Forms carrega diretamente as migrations do pacote, sem depender de publicação ou cópia no consumidor.
- [ ] Migrations históricas não são alteradas e não há backfill de dados legados nesta entrega.
- [ ] Testes comprovam unicidade de versão ativa e resolução ativa/exata.
