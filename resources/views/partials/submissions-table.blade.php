{{-- 

  DEPRECADO: usar componente
  <x-uspdev-forms::submissions-table
    :form="$form"
    :definition="$definition"
    :submissions="$submissions"
  ></x-submission-table>

  Esta é uma diretiva blade.
  @submissionTable($form)
  
 --}}
<table class="table table-striped table-bordered datatable-simples w-100" style="table-layout: fixed; width: 100%;">
  <thead>
    <tr>
      @foreach ($definition->flattenFields() as $field)
        <th>{{ $field['label'] ?? $field['name'] }}</th>
      @endforeach
      @if ($form->editable)
        <th></th>
      @endif
    </tr>
  </thead>
  <tbody>
    @foreach ($submissions as $submission)
      <tr>
        @foreach ($definition->flattenFields() as $field)
          <td style="overflow: hidden; text-overflow: ellipsis; ">
            @if ($field['type'] == 'pessoa-usp')
              {{ $submission['data'][$field['name']] }}
              {{ \Uspdev\Replicado\Pessoa::retornarNome($submission['data'][$field['name']]) ?? $submission['data'][$field['name']] }}
            @elseif ($field['type'] == 'checkbox')
              {{ isset($submission['data'][$field['name']]) ? json_encode($submission['data'][$field['name']]) : 'n/a' }}
            @else
              {{ $submission['data'][$field['name']] ?? 'n/a' }}
            @endif
          </td>
        @endforeach
        {{-- @if ($form->editable) --}}
        <td>
          <a href="{{ url()->current() }}/{{ $submission->id }}/edit" class="btn btn-sm btn-outline-primary">Edit</a>
        </td>
        {{-- @endif --}}
      </tr>
    @endforeach
  </tbody>
</table>
