# Documentação técnica do Forms

Esta é a porta de entrada da documentação técnica do `uspdev/forms`. Os links abaixo estão organizados na ordem recomendada de leitura para quem quer entender ou integrar a biblioteca.

## Leitura recomendada

1. [Conceitos e definição de formulário](models/form-definition.md)  
   Descreve como `form_definitions` funciona, como `name + version` identifica uma versão concreta e como a versão ativa é escolhida.

2. [API via facade](api/api_facade.md)  
   Lista os fluxos pela facade: renderização, submissão, atualização, consulta, filtros, arquivos, exclusão, auditoria e sincronização.

3. [API direta](api/api_direta.md)  
   Espelha os mesmos fluxos pela abordagem direta em `FormDefinition` e `FormSubmission`.

4. [Diferenças e equivalências entre API direta e facade](api/api_direta_facade_diferencas_equivalencias.md)  
   Mostra os métodos equivalentes, os fluxos apenas via facade, os fluxos apenas via direta e os critérios de escolha entre as abordagens.

5. [Caso de uso completo: parecer final](caso-de-uso-parecer-final.md)  
   Mostra um exemplo de ponta a ponta: definição JSON, sync, renderização, submissão, validação, edição e consulta.

6. [Submissões, auditoria e relacionamento com definições](models/form-submission.md)  
   Mostra como `form_submissions` se relaciona com `form_definitions` e por que submissões antigas continuam presas à definição usada no envio.

7. [Validação de form_definition](models/validacao-form-definition.md)  
   Resume as regras aplicadas pelo `FormDefinitionSchemaValidator`.

8. [Guia de migração para consumidores](migracao.md)  
   Descreve a migração de sistemas que usam `uspdev/forms`, incluindo bibliotecas consumidoras como `uspdev/workflow`, e destaca as mudanças incompatíveis.

9. [Decisões da V2](decisoes-v2.md)  
   Reúne as decisões aprovadas e as alternativas descartadas durante a evolução para a versão 2.

## Resumo

`uspdev/forms` é uma biblioteca Laravel para definir formulários dinâmicos, renderizar HTML a partir dessas definições, validar dados com ou sem persistência, consultar dados submetidos, manipular arquivos enviados e acessar auditoria operacional.

A API pública oficial é a facade `Uspdev\Forms\Facades\Forms`. A classe `Uspdev\Forms\Form` permanece no pacote como implementação interna e não é usada diretamente por sistemas consumidores.

As definições de formulário são identificadas por `name + version`. Quando uma chamada pública omitir `version`, a biblioteca usa a versão ativa daquele `name`.

## Divisão de responsabilidades

A separação principal da V2 é entre definição e submissão.

`FormDefinition` representa a estrutura do formulário: nome, versão, versão ativa, grupo, descrição e campos. É a parte que define como o formulário existe e é validado.

`FormSubmission` representa os dados enviados por alguém. Cada submissão aponta para a definição usada no momento do envio por meio de `form_definition_id`, então uma submissão antiga continua ligada à versão exata do formulário que a criou.

A facade `Forms` é apenas a porta pública da biblioteca. Ela existe para oferecer uma API simples e estável para os consumidores, mas não significa que toda a regra interna precise ficar em uma única classe. A implementação pode ser organizada em serviços internos de definição, submissão, renderização e arquivos, sem mudar o contrato público.

Os métodos públicos são classificados em três grupos: métodos apenas via facade, métodos disponíveis via facade e model, e métodos apenas via model. Essa classificação está documentada em [Diferenças e equivalências entre API direta e facade](api/api_direta_facade_diferencas_equivalencias.md), [API via facade](api/api_facade.md) e [API direta](api/api_direta.md).
