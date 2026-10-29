{{-- Last page: the student's sign-in card (ADR-0009); the QR code carries only a random id. --}}
<div style="padding-top: 110mm;"></div>
<table class="form card" align="center">
    <tr>
        <td colspan="2" class="label" style="text-align: center; font-size: 14pt;">{{ __('receipt.card.title') }}</td>
    </tr>
    <tr>
        <td style="width: 62%; vertical-align: top; line-height: 2;">
            {{ __('receipt.card.student_number') }}: <bdo dir="ltr" style="font-size: 16pt;">{!! $value($student->student_number) !!}</bdo><br>
            {{ __('receipt.card.username') }}: {!! $value($student->username) !!}<br>
            {{ __('receipt.card.mobile') }}: <bdo dir="ltr">{!! $value($student->phone) !!}</bdo>
        </td>
        <td style="width: 38%; text-align: center;">
            <barcode code="{{ $studentQrUrl }}" type="QR" size="0.7" error="M" disableborder="1" />
        </td>
    </tr>
    <tr>
        <td colspan="2" style="font-size: 9pt;">{{ __('receipt.card.hint') }}</td>
    </tr>
</table>
