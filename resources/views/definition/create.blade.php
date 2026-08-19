@extends('uspdev-forms::layouts.app')

@section('content')
  <div class="card">
    <div class="card-header h4 card-header-sticky d-flex justify-content-between align-items-center">
      <div>
        <a href="{{ route('form-definitions.index') }}">USPdev forms</a> >

        @if ($formDefinition)
          Editar <b>{{ $formDefinition->name }}</b>
        @else
          Nova definição
        @endif
        <a href="{{ route('form-definitions.index') }}" class="btn btn-sm btn-outline-secondary mr-2">
          <i class="fas fa-arrow-left"></i> Voltar
        </a>
      </div>
      <div>
        @include('uspdev-forms::partials.ajuda-modal')
      </div>
    </div>
    <div class="card-body">
      <form id="form-definition-form"
        action="{{ isset($formDefinition) ? route('form-definitions.update', $formDefinition) : route('form-definitions.store') }}"
        method="POST">
        @isset($formDefinition)
          @method('PUT')
        @endisset
        @csrf
        <div class="form-group">
          <label for="name">Nome do formulário <span class="text-danger">*</span></label>
          <input type="text" id="name" name="name" class="form-control"
            value="{{ old('name', $formDefinition->name ?? '') }}" required>
        </div>

        <div class="form-group">
          <label for="version">Versão <span class="text-danger">*</span></label>
          <input type="number" id="version" name="version" min="1" class="form-control"
            value="{{ old('version', $formDefinition->version ?? 1) }}" required>
        </div>

        <div class="form-group">
          <label for="status">Status <span class="text-danger">*</span></label>
          <select id="status" name="status" class="form-control" required>
            @foreach (['draft' => 'Rascunho', 'active' => 'Ativa', 'disabled' => 'Desativada'] as $value => $label)
              <option value="{{ $value }}" @selected(old('status', isset($formDefinition) ? $formDefinition->status->value : 'active') === $value)>
                {{ $label }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label for="group">Grupo <span class="text-danger">*</span></label>
          <input type="text" id="group" name="group" class="form-control"
            value="{{ old('group', $formDefinition->group ?? '') }}" required>
        </div>

        <div class="form-group">
          <label for="description">Descrição</label>
          <input type="text" id="description" name="description" class="form-control"
            value="{{ old('description', $formDefinition->description ?? '') }}">
        </div>

        <div class="form-group">
          <label for="fields">Campos (json)</label>
          <textarea id="fields" name="fields" class="form-control autoexpand">{{ old('fields') ?? (isset($formDefinition) ? json_encode($formDefinition->fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Salvar</button>
      </form>
    </div>
  </div>
@endsection

<style>
  .autoexpand {
    field-sizing: content;
    min-height: 100px
  }
</style>
{{--
Bloco para autoexpandir textarea conforme necessidade.

Uso:
- Incluir no layouts.app ou em outro lugar: @include('laravel-usp-theme::blocos.textarea-autoexpand')
- Adiconar a classe 'autoexpand'

@author Masakik, em 8/5/2024
--}}
@once
  @section('javascripts_bottom')
    @parent
    <script>
      $(document).ready(function() {

        // //{{-- https://stackoverflow.com/questions/2948230/auto-expand-a-textarea-using-jquery --}}
        // $(document).on('change keyup paste cut', '.autoexpand', function(e) {

        //   while ($(this).outerHeight() < this.scrollHeight + parseFloat($(this).css("borderTopWidth")) + parseFloat(
        //       $(this).css("borderBottomWidth"))) {
        //     $(this).height($(this).height() + 1);
        //   };


        //   // $(this).height(0).height(this.scrollHeight)
        //   // $(this).height(0).height(
        //   //   this.scrollHeight +
        //   //   parseFloat($(this).css('borderTopWidth')) +
        //   //   parseFloat($(this).css('borderBottomWidth'))
        //   // )
        // })

        // // aparentemente precisa dar um tempinho para poder disparar o autoexpand
        // setTimeout(() => {
        //   $('.autoexpand').trigger('change')
        // }, 500)

        // valida fields antes de submeter o formulário
        $('#form-definition-form').on('submit', function(e) {
          const jsonText = $('#fields').val()

          try {
            JSON.parse(jsonText)
          } catch (error) {
            e.preventDefault();
            alert('O JSON precisa ser válido!')
          }
        })

      })
    </script>
  @endsection
@endonce
