{{-- The barcode holds the student number: staff scan it to open the application. --}}
<table class="ids" align="center">
    <tr>
        <td>
            @if ($student->student_number !== null)
                <barcode code="{{ $student->student_number }}" type="C128A" size="0.8" height="0.85" />
            @endif
        </td>
        <td class="number"><bdo dir="ltr">{!! $value($student->student_number) !!}</bdo></td>
    </tr>
</table>
