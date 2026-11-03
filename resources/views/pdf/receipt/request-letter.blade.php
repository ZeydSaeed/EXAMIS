{{-- Page 2: the student's request to the directorate, as an official letter. --}}
@php
    $blank = str_repeat('.', 45);
    $filled = fn ($text) => filled($text) ? e((string) $text) : $blank;
    // Left empty for the student to write in by hand.
    $space = fn (int $characters) => str_repeat('&nbsp;', $characters);
    $letter = $texts[\App\Services\Receipts\ReceiptTexts::LETTER];
    $values = [
        'العام_الدراسي' => '<bdo dir="ltr">'.e($academicYear).'</bdo>',
        'الفرع' => $filled($branch),
        'الاختصاص' => $filled($specialization),
        'فراغ_الصف' => $space(30),
        'فراغ_المدرسة' => $space(46),
        'سنة_الميلاد' => $filled($application->birth_date?->format('Y')),
    ];
@endphp
<div class="letter-head">
    {{ __('receipt.letter.directorate') }}.<br>
    {{ __('receipt.letter.through') }}
</div>
<div class="letter-subject">{{ $letter['title'] }}</div>

<div class="letter">
    <div class="letter-greeting">{{ __('receipt.letter.greeting') }}</div>
    {{-- Each paragraph at one size, wrapping only where a line is full; the
         student's details fill the placeholders. --}}
    @foreach ($letter['paragraphs'] as $paragraph)
        <div style="text-align: justify;">{!! \App\Services\Receipts\ReceiptTexts::fillLetter($paragraph, $values) !!}</div>
    @endforeach
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
