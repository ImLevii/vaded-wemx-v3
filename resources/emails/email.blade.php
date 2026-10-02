<x-mail::message>
# Hi {{ $name ?? 'there' }},

@foreach($lines as $line)
{{ $line . "\n" }}
@endforeach


@if(isset($table) && is_array($table) && isset($table['columns']) && is_array($table['columns']) && isset($table['rows']) && is_array($table['rows']))
<table width="100%" cellpadding="10" style="text-align: center; border-collapse: collapse;">
    <thead>
    <tr>
        @foreach($table['columns'] as $column)
        <th style="border-bottom: 1px solid #ddd; padding: 8px;">{{ $column }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
        @foreach($table['rows'] as $row)
        <tr>
            @foreach($row as $cell)
                <td>{{ $cell }}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if(isset($button) && is_array($button) && isset($button['url']))
<x-mail::button url="{{ $button['url'] ?? url('/') }}">
{{ $button['text'] ?? 'Learn more' }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
