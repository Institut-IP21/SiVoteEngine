@php
    // Plain-text audit log: one line per processed pairwise edge, strongest
    // margin first — either it locked in, or it was skipped because it would
    // have created a cycle with what was already locked. Params: $log
    // (list of OrderedListResult['lock_in_log'] entries).
@endphp
<ol class="flex flex-col gap-2 text-[13px] leading-relaxed" style="color: var(--color-ink)">
    @foreach ($log as $entry)
        <li style="overflow-wrap:anywhere">
            @if ($entry['type'] === 'locked')
                {{ __('components.orderedlist.log_locked', [
                    'winner' => $entry['winner'],
                    'loser' => $entry['loser'],
                    'for' => $entry['for'],
                    'against' => $entry['against'],
                    'margin' => $entry['margin'],
                ]) }}
            @else
                {{ __('components.orderedlist.log_skipped', [
                    'winner' => $entry['winner'],
                    'loser' => $entry['loser'],
                    'for' => $entry['for'],
                    'against' => $entry['against'],
                    'margin' => $entry['margin'],
                    'members' => implode(', ', $entry['members'] ?? []),
                ]) }}
            @endif
        </li>
    @endforeach
</ol>
