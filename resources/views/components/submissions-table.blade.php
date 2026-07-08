{{-- 
  Mostra uma tabela com as submissões de um formulário.
  
  Chamar com:
  <x-uspdev-forms::submissions-table
    :form="$form"
    :definition="$definition"
    :submissions="$submissions"
  ></x-submission-table>

  masakik, 29/5/2025
 --}}
@props(['form', 'definition', 'submissions'])
<div class="table-responsive">
  <table class="table table-striped table-bordered datatable-simples">
    <thead>
      <tr>
        @foreach ($definition->flattenFields() as $field)
          <th>{{ $field['label'] ?? $field['name'] }}</th>
        @endforeach
        <th style="width: 40px"></th>
      </tr>
    </thead>
    <tbody>
      @foreach ($submissions as $submission)
        <tr>
          @foreach ($definition->flattenFields() as $field)
            <td>
              @includeFirst([
                  'uspdev-forms::partials.' . $field['type'] . '-view',
                  'uspdev-forms::partials.default-view',
              ])
            </td>
          @endforeach
          <td>
            <div class="d-flex">
              @include('uspdev-forms::submission.partials.edit-btn')
              <div class="px-1"></div>
              @include('uspdev-forms::submission.partials.delete-btn')
              <div class="px-1"></div>
              @include('uspdev-forms::submission.partials.show-btn')
              @include('uspdev-forms::submission.partials.show-form-modal')
            </div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
