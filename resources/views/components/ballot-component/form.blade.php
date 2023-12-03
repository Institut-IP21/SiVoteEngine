<div class="">
    @if ($componentTree[$component->type][$component->version]['livewireForm'])
    @livewire(Str::kebab($component->type) . '-livewire', ['ballot' => $ballot, 'component'
    => $component])
    @else
    @include($component->form_template, ['component' => $component, 'election' =>
    $election])
    @endif
</div>