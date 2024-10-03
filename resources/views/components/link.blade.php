<a
    href="{{ $href }}"
    {{ $attributes->merge([
       'rel' => $defaultRel,
       'target' => $defaultTarget,
    ]) }}
>
    {!! $slot !!}
</a>
