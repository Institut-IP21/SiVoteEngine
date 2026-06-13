@props(['component', 'showType' => false])

@php
    // Map the stored component type to its localized name (see lang/*/components.php).
    $typeNameKeys = [
        'YesNo' => 'components.yesno.name',
        'FirstPastThePost' => 'components.firstpastthepost.name',
        'RankedChoice' => 'components.rankedchoice.name',
        'ApprovalVote' => 'components.approval.name',
    ];
@endphp
<div class="pb-3 font-bold text-xl sm:text-2xl flex justify-between items-baseline">
    <h2>{{ $component->title }}</h2>
    @if ($showType && isset($typeNameKeys[$component->type]))
        <span class="text-sm font-normal text-gray-500 ml-3 flex-shrink-0">{{ __($typeNameKeys[$component->type]) }}</span>
    @endif
</div>