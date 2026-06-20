{{-- min-width:0 + overflow-wrap keep a long option label fully visible in the cell
     instead of overflowing the results row (voter-facing: never truncate). --}}
<div class="flex-1 border p-3" style="min-width:0;overflow-wrap:anywhere">
    {{ $slot }}
</div>