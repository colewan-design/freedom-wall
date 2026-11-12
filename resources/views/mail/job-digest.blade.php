<x-mail::message>
# New matches for “{{ $monitor->name }}”

@foreach ($hits as $hit)
**[{{ $hit->listing->title }}]({{ route('jobs.show', $hit->listing) }})** — {{ $hit->score }}% match
{{ $hit->listing->employer ?? 'Employer not stated' }} · {{ $hit->listing->location ?? ($hit->listing->is_remote ? 'Remote' : 'Location not stated') }}
@if ($hit->listing->safety_flags)
⚠ Check this one before applying: {{ collect(array_keys($hit->listing->safety_flags))->map(fn ($k) => \App\Services\Jobs\JobSafety::LABELS[$k] ?? $k)->join(', ') }}
@endif

@endforeach
@if ($total > $hits->count())
…and {{ $total - $hits->count() }} more on the site.
@endif

<x-mail::button :url="$url">
Open My Jobs
</x-mail::button>

Always apply through the employer's own process. Never pay a fee to be considered for a job.

You get this because of a job monitor on BSU Freedom Wall. Turn email alerts off for it on the My Jobs page.
</x-mail::message>
