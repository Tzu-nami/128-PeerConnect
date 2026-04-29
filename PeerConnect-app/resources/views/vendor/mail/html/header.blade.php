@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/LRC_logo.png" class="logo" alt="LRC PeerConnect Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
