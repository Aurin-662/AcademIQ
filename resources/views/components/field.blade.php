@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null])
<div>
    <label class="label" for="{{ $name }}">{{ $label }}</label>
    <input class="input" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password') value="{{ $value ?? old($name) }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif required aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" {{ $attributes }}>
    @error($name)<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
</div>
