# Documentação técnica do Forms

Esta é a porta de entrada da documentação técnica do `uspdev/forms`. Os links abaixo estão organizados na ordem recomendada de leitura para quem quer entender ou integrar a biblioteca.

## Leitura recomendada

1. [Conceitos e definição de formulário](definicoes/form_definition.md)  
   A página descreve como `form_definitions` funciona, como `name + version` identifica uma versão concreta e como a versão ativa é escolhida.

2. [API pública via facade Forms](api/api_publica.md)  
   A página lista os métodos oficiais para renderizar, submeter, atualizar, consultar e sincronizar formulários.

3. [API via facade Forms](api/facade_forms.md)  
   A página descreve quando a facade funciona como porta de alto nível e quais métodos ela expõe.

4. [Métodos diretos nos models](api/metodos_diretos.md)  
   A página descreve quando métodos em `FormDefinition` e `FormSubmission` aparecem e como eles se relacionam com a facade.

5. [Equivalência entre facade e models](api/equivalencia_facade_model.md)  
   A página mostra quais métodos existem nos dois lados, quais são apenas facade e quais são apenas model.

6. [Caso de uso completo: parecer final](casos-de-uso/parecer_final.md)  
   A página mostra um exemplo de ponta a ponta: definição JSON, sync, renderização, submissão, validação, edição e consulta.

7. [Submissões, auditoria e relacionamento com definições](submissoes/modelagem.md)  
   A página mostra como `form_submissions` se relaciona com `form_definitions` e por que submissões antigas continuam presas à definição usada no envio.

8. [Validação de form_definition](definicoes/validacao_form_definition.md)  
   A página resume as regras aplicadas pelo `FormDefinitionSchemaValidator`.

9. [Guia de migração para consumidores](consumidores/migracao_consumidores.md)  
   A página descreve a migração de sistemas que usam `uspdev/forms`, incluindo bibliotecas consumidoras como `uspdev/workflow`.

10. [Breaking changes](refactor/breaking_changes.md)  
   A página resume as mudanças incompatíveis da nova versão.

11. [Decisões do refactor](refactor/decisoes-refactor.md)  
   A página reúne as decisões aprovadas e as alternativas descartadas durante o refactor.

## Resumo

`uspdev/forms` é uma biblioteca Laravel para definir formulários dinâmicos, renderizar HTML a partir dessas definições, validar dados com ou sem persistência, consultar dados submetidos e manipular arquivos enviados.

A API pública oficial é a facade `Uspdev\Forms\Facades\Forms`. A classe `Uspdev\Forms\Form` permanece no pacote como implementação interna e não é usada diretamente por sistemas consumidores.

As definições de formulário são identificadas por `name + version`. Quando uma chamada pública omitir `version`, a biblioteca usa a versão ativa daquele `name`.

## Divisão de responsabilidades

A separação principal da V2 é entre definição e submissão.

`FormDefinition` representa a estrutura do formulário: nome, versão, versão ativa, grupo, descrição e campos. É a parte que define como o formulário existe e é validado.

`FormSubmission` representa os dados enviados por alguém. Cada submissão aponta para a definição usada no momento do envio por meio de `form_definition_id`, então uma submissão antiga continua ligada à versão exata do formulário que a criou.

A facade `Forms` é apenas a porta pública da biblioteca. Ela existe para oferecer uma API simples e estável para os consumidores, mas não significa que toda a regra interna precise ficar em uma única classe. A implementação pode ser organizada em serviços internos de definição, submissão, renderização e arquivos, sem mudar o contrato público.

Os métodos públicos são classificados em três grupos: métodos apenas via facade, métodos disponíveis via facade e model, e métodos apenas via model. Essa classificação está documentada em [Equivalência entre facade e models](api/equivalencia_facade_model.md), [API via facade Forms](api/facade_forms.md), [Métodos diretos nos models](api/metodos_diretos.md) e [Decisões do refactor](refactor/decisoes-refactor.md).
