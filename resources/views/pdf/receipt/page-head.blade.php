{{-- The head of every page but the request letter: committee on the right,
     vocational logo in the middle, photo on the left, then the student number. --}}
<table class="head" style="width: 100%;">
    <tr>
        <td class="committee" style="width: 72mm;">
            {{ __('receipt.committee') }}<br>
            {{ __('receipt.preliminary_exams') }}<br>
            {{ __('receipt.department') }}
        </td>
        <td style="width: 42mm; text-align: center;">
            @if ($logo)
                <img src="{{ $logo }}" style="height: 26mm;">
            @endif
        </td>
        <td style="width: 40mm;">&nbsp;</td>
        <td class="photo">
            @if ($hasPhoto)
                <img src="var:photo" style="width: 28mm; height: 36mm;">
            @else
                {{ __('receipt.photo') }}
            @endif
        </td>
        <td style="width: 4mm;">&nbsp;</td>
    </tr>
</table>

@include('pdf.receipt.student-ids')
{{-- Page 1 draws the rule lower, under the form date. --}}
@if ($rule ?? true)
    @include('pdf.receipt.page-rule')
@endif
