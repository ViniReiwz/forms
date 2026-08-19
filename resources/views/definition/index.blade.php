@extends('uspdev-forms::layouts.app')

@section('header')
@endsection

@section('content')

<div class="col-2">@include('uspdev-forms::definition.partials.tabs')</div>
<div class="card">
    <div class="card-header h4 card-header-sticky d-flex justify-content-between align-items-center">
      <div>
        <span class="text-danger">USPdev forms</span> >
        Definições
        <a href="{{ route('form-definitions.create') }}" class="btn btn-sm btn-primary">Nova Definição</a>
      </div>
      <div>
        @include('uspdev-forms::partials.ajuda-modal')
      </div>
    </div>
    <div class="card-body">
      <table class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Versão</th>
            <th>Grupo</th>
            <th>Descrição</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($formDefinitions as $formDefinition)
            <tr>
              <td>
                {{ $formDefinition->name }}
                <span class="badge badge-primary badge-pill" title="Submissões">
                  {{ $formDefinition->formSubmissions->count() }}
                </span>
                <span class="badge badge-danger badge-pill" title="Submissões excluídas">
                  {{ $formDefinition->formSubmissions()->onlyTrashed()->count() }}
                </span>
              </td>
              <td>
                {{ $formDefinition->version }}
                @if ($formDefinition->status->value === 'active')
                  <span class="badge badge-success">ativa</span>
                @else
                  <span class="badge badge-secondary">{{ $formDefinition->status->value }}</span>
                @endif
              </td>
              <td>
                {{ $formDefinition->group }}
              </td>
              <td>
                {{ $formDefinition->description }}
              </td>
              <td class="d-flex justify-content-start">
                @include('uspdev-forms::definition.partials.show-btn')
                @include('uspdev-forms::definition.partials.editar-btn')
                @include('uspdev-forms::definition.partials.delete-btn')
                @includeWhen(
                    $formDefinition->formSubmissions()->onlyTrashed()->count() > 0,
                    'uspdev-forms::definition.partials.delete-trashed-btn')
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
