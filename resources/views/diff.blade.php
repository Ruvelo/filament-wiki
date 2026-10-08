{{-- The changes a revision made, line by line. $diff comes from Ruvelo\Wiki\Support\Diff::collapse(). --}}
<div class="fi-wiki-changes">
    <p class="fi-wiki-changes-head">
        <span>{{ $previous ? 'Changes since #'.$previous->id : 'First version' }}</span>
        <span class="fi-wiki-add">+{{ $stats['added'] }}</span>
        <span class="fi-wiki-del">−{{ $stats['removed'] }}</span>
    </p>

    @if ($previous && $previous->title !== $revision->title)
        <p>Renamed from “{{ $previous->title }}” to “{{ $revision->title }}”.</p>
    @endif

    @if ($stats['added'] + $stats['removed'] === 0)
        <p class="fi-wiki-muted">The text didn’t change.</p>
    @else
        <div class="fi-wiki-diff" role="table" aria-label="Line changes">
            @foreach ($diff as [$op, $line])
                @if ($op === '…')
                    <div role="row" class="fi-wiki-diff-skip">⋯ {{ $line }} unchanged {{ \Illuminate\Support\Str::plural('line', $line) }}</div>
                @else
                    <div role="row" class="{{ ['+' => 'fi-wiki-diff-add', '-' => 'fi-wiki-diff-del', ' ' => 'fi-wiki-diff-eq'][$op] }}"><span aria-label="{{ ['+' => 'Added', '-' => 'Removed', ' ' => 'Unchanged'][$op] }}">{{ $op }}</span> {{ $line }}</div>
                @endif
            @endforeach
        </div>
    @endif
</div>
