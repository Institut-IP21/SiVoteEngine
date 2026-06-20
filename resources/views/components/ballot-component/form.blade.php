<div class="">
    @if ($componentTree[$component->type][$component->version]['livewireForm'])
        {{-- Embedded builder preview: the cross-origin iframe can't carry the engine
             session the Livewire widget needs (third-party cookies blocked → CSRF 419),
             so render a static, non-interactive version. The standalone full-page
             preview and the real ballot keep the live widget. --}}
        @if (request()->boolean('embed'))
            @include($component->form_template_preview, ['component' => $component, 'election' => $election])
        @else
            @livewire(Str::kebab($component->type) . '-livewire', ['ballot' => $ballot, 'component' => $component])
        @endif
    @else
        @include($component->form_template, ['component' => $component, 'election' => $election])
    @endif
</div>