@extends('uspdev-forms::layouts.app')

@section('content')
  <div class="card">
    <div class="card-header h4 card-header-sticky d-flex justify-content-between align-items-center">
      <div>
        <a href="{{ route('form-definitions.index') }}">USPdev forms</a>
        > submissões para <b>{{ $form->name }}</b>
        <a href="{{ route('form-submissions.create', $formDefinition) }}" class="btn btn-sm btn-primary">
          Nova submissão
        </a>
      </div>
      <div>
        @include('uspdev-forms::partials.ajuda-modal')
      </div>
    </div>
    <div class="card-body">
      <x-uspdev-forms::submissions-table
        :form="$form"
        :definition="$formDefinition"
        :submissions="$submissions"
      ></x-uspdev-forms::submissions-table>
    </div>
  </div>
@endsection
