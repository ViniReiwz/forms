# Forms

Forms é uma biblioteca **uspdev** para criar formulários dinâmicos a partir de definições persistidas, renderizar HTML, validar submissões, persistir respostas e manipular arquivos enviados.

## Funcionalidades

* Gera formulários a partir de definições armazenadas no banco.
* Processa submissões com validação e persistência.
* Permite validar dados sem persistir submissões.
* Suporta versões de definição por `name + version`.
* Usa a versão ativa quando `version` é omitida em chamadas públicas.
* Mantém submissões presas a uma versão concreta de `FormDefinition`.
* Possui CRUD administrativo.
* Suporta Bootstrap 4 e 5.
* Integra com Laravel 11 em diante.

## Instalação

```bash
composer require uspdev/forms
php artisan vendor:publish --tag=forms-config
php artisan vendor:publish --tag=forms-migrations
php artisan migrate
```

## Menu administrativo

No arquivo `config/laravel-usp-theme.php`, a chave `uspdev-forms` faz o menu ficar visível apenas para administradores.

```php
[
    'key' => 'uspdev-forms',
],
```

## Sincronização de formulários

Sincroniza definições em `.json` para `form_definitions`.

```bash
php artisan forms:sync
php artisan forms:sync --path=storage/app/formsJson
```

O comando usa `name + version` para criar ou atualizar definições.

## Exemplo rápido

```php
use Uspdev\Forms\Facades\Forms;

$html = Forms::render('parecer_final', [
    'action' => route('pareceres.store'),
]);
```

```php
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Uspdev\Forms\Facades\Forms;

public function store(Request $request)
{
    try {
        $submission = Forms::submit($request);
    } catch (ValidationException $e) {
        return back()->withErrors($e->validator)->withInput();
    }

    return redirect()->back()->with('alert-success', 'Formulário enviado com sucesso.');
}
```

## Documentação

Comece por [Documentação técnica](docs/documentacao-tecnica.md). Ela organiza os demais documentos na ordem recomendada de leitura.
