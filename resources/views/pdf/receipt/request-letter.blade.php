{{-- Page 2: the student's request to the directorate, as an official letter. --}}
@php
    $blank = str_repeat('.', 45);
    $filled = fn ($text) => filled($text) ? e((string) $text) : $blank;
    // Left empty for the student to write in by hand.
    $space = fn (int $characters) => str_repeat('&nbsp;', $characters);
@endphp
<div class="letter-head">
    {{ __('receipt.letter.directorate') }}.<br>
    {{ __('receipt.letter.through') }}
</div>
<div class="letter-subject">{{ __('receipt.letter.subject') }}</div>

<div class="letter">
    <div class="letter-greeting">{{ __('receipt.letter.greeting') }}</div>
    {{-- One paragraph at one size, wrapping only where a line is full. A word stays
         joined to the brackets after it, so no line starts with an empty pair. --}}
    <div style="text-align: justify;">
        {{ __('receipt.letter.request') }} <bdo dir="ltr">{{ $academicYear }}</bdo>،
        {{ __('receipt.letter.branch') }}&nbsp;({!! $filled($branch) !!})
        {{ __('receipt.letter.specialization') }}&nbsp;({!! $filled($specialization) !!})،
        {{ __('receipt.letter.last_grade') }}&nbsp;({!! $space(30) !!})
        {{ __('receipt.letter.school') }}&nbsp;({!! $space(46) !!})
        {{ __('receipt.letter.birth_year') }}&nbsp;({!! $filled($application->birth_date?->format('Y')) !!}).
    </div>
</div>

{{-- Signature block low on the left, placed from the page's foot so the letter above never moves it. --}}
<div style="position: absolute; bottom: 52mm; left: 22mm; width: 100mm;">
    <table class="letter-signature" style="width: 100%;">
        <tr>
            <td style="height: 12mm; vertical-align: top;">{{ __('receipt.letter.signature') }}</td>
        </tr>
        <tr>
            <td>{!! $filled($fullName) !!}</td>
        </tr>
        <tr>
            {{-- Day and month left blank; read right to left the year comes last. --}}
            <td><bdo dir="ltr">{{ $generatedAt->year }} /&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</bdo></td>
        </tr>
    </table>
</div>
