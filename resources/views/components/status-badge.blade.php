@props(['status'])
<span class="badge badge-{{ $status }}">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $status)) }}</span>
