{{-- Page 2: the student's request to the Director General of Education, as an official letter. --}}
@php
    $blank = str_repeat('.', 45);
    $filled = fn ($text) => filled($text) ? e((string) $text) : $blank;
@endphp
<div class="page-heading" style="padding-top: 56mm;">{{ __('receipt.letter.directorate') }}</div>

<div class="letter">
    <div style="margin-bottom: 6mm;">{{ __('receipt.letter.addressee') }}</div>
    <div style="text-align: justify;">
        {!! __('receipt.letter.request', ['year' => '<bdo dir="ltr">'.e($academicYear).'</bdo>']) !!}
        {{ __('receipt.letter.branch') }} ({!! $filled($branch) !!})
        {{ __('receipt.letter.specialization') }} ({!! $filled($specialization) !!})
        {{ __('receipt.letter.last_grade') }} ({!! $filled($application->last_grade) !!})
        {{ __('receipt.letter.school') }} ({!! $filled($application->school_name) !!})
        {{ __('receipt.letter.birth_year') }} ({!! $filled($application->birth_date?->format('Y')) !!}).
    </div>
</div>

{{-- Signature block on the left, its lines centred under each other; a table so it is never split. --}}
<div style="padding-top: 40mm;"></div>
<table class="letter-signature" align="left" style="width: 60%;">
    <tr>
        <td style="height: 18mm; vertical-align: top;">{{ __('receipt.letter.signature') }}</td>
    </tr>
    <tr>
        <td>{{ __('receipt.letter.applicant') }} {!! $filled($fullName) !!}</td>
    </tr>
    <tr>
        {{-- Day and month left blank; read right to left the year comes last. --}}
        <td><bdo dir="ltr">{{ $generatedAt->year }} /&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</bdo></td>
    </tr>
</table>
