<form action="{{ $form->action }}" method="{{ $form->method == 'PUT' ? 'POST' : $form->method }}"
  name="{{ $form->definition->name }}" id="generatedForm" enctype="multipart/form-data">
  @method($form->method)
  @csrf()
  <input type="hidden" name="form_definition_id" value="{{ $form->definition->id }}">
  <input type="hidden" name="form_definition" value="{{ $form->definition->name }}">
  <input type="hidden" name="version" value="{{ $form->definition->version }}">
  <input type="hidden" name="form_key" value="{{ $form->key }}">

  {!! $fields !!}

  <button type="submit" class="btn btn-primary {{ $form->btnSize }}">{{ $form->btnLabel }}</button>
</form>
