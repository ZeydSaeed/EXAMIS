{{-- Page 4: the required documents, the folder colour of each branch and the stages of applying. --}}
@include('pdf.receipt.page-head')

<div class="section-title" style="margin-top: 5mm;">{{ $texts[\App\Services\Receipts\ReceiptTexts::DOCUMENTS]['title'] }}</div>

<div class="documents">
    @foreach ($texts[\App\Services\Receipts\ReceiptTexts::DOCUMENTS]['paragraphs'] as $index => $item)
        <div>{{ $index + 1 }}- {{ $item }}</div>
    @endforeach
</div>

<table class="form folders" style="width: 75%; margin-top: 6mm;" align="center">
    <tr>
        {{-- Grey, whatever the student's branch: the rows below carry the colours. --}}
        <td style="width: 60%; text-align: center; background-color: #d9d9d9;">{{ __('receipt.documents_page.branch') }}</td>
        <td style="width: 40%; text-align: center; background-color: #d9d9d9;">{{ __('receipt.documents_page.folder_colour') }}</td>
    </tr>
    @foreach (__('receipt.documents_page.branches') as $code => [$name, $colour])
        <tr>
            <td>{{ $name }}</td>
            <td style="text-align: center; background-color: {{ $branchShades[$code][0] ?? '#ffffff' }};">{{ $colour }}</td>
        </tr>
    @endforeach
</table>

<div class="section-title" style="margin-top: 7mm;">{{ $texts[\App\Services\Receipts\ReceiptTexts::STAGES]['title'] }}:</div>

<div class="documents">
    @foreach ($texts[\App\Services\Receipts\ReceiptTexts::STAGES]['paragraphs'] as $index => $stage)
        <div>{{ $index + 1 }}- {{ $stage }}</div>
    @endforeach
</div>
