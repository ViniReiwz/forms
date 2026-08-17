# 02 — Disponibilizar a API pública de consumo do Forms V2

**What to build:** Uma aplicação consumidora consegue localizar, renderizar, validar, submeter, atualizar e consultar formulários usando somente a API pública do Forms V2, mantendo cada submissão presa à versão usada.

**Blocked by:** Forms 01 — Consolidar schema e versionamento do Forms V2.

**Status:** ready-for-agent

- [ ] A facade ou serviço público resolve definições por nome, por versão exata e pela versão ativa.
- [ ] Renderização e validação usam a mesma definição resolvida.
- [ ] Submissão e atualização funcionam sem chamadas diretas à classe interna de formulário.
- [ ] Cada submissão mantém o vínculo com a definição e a versão utilizadas.
- [ ] O registro de auditoria é produzido pelo mecanismo do Forms, sem duplicação no consumidor.
- [ ] Definições inexistentes, versões inválidas e dados inválidos produzem erros claros.
