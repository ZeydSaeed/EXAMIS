{{-- Last page: the student's pledge to bring the intermediate-school certificate. --}}
@include('pdf.receipt.page-head')

<div class="page-heading" style="margin-top: 30mm;">{{ __('receipt.pledge.title') }}</div>

{{-- One empty line between the title and the pledge. --}}
<div class="letter" style="margin-top: 0; text-align: justify;">
    <br>
    {{ __('receipt.pledge.text') }}
</div>

{{-- Name and date low on the left, placed from the page's foot like the request letter. --}}
<div style="position: absolute; bottom: 52mm; left: 22mm; width: 100mm;">
    <table class="letter-signature" style="width: 100%;">
        <tr>
            <td>{!! $value($fullName) !!}</td>
        </tr>
        <tr>
            {{-- Day and month left blank; read right to left the year comes last. --}}
            <td><bdo dir="ltr">{{ $generatedAt->year }} /&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</bdo></td>
        </tr>
    </table>
</div>
