@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/innerr-logo.png') }}?v={{ time() }}" class="logo" alt="{{ config('app.name') }}" width="180" height="63">
</a>
</td>
</tr>
