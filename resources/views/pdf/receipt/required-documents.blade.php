{{-- Page 4: the required documents, the folder colour of each branch and the stages of applying. --}}
<div class="section-title" style="padding-top: 12mm;">{{ __('receipt.documents_page.title') }}</div>

<div class="documents">
    @foreach (__('receipt.documents_page.items') as $index => $item)
        <div>{{ $index + 1 }}- {{ $item }}</div>
    @endforeach
</div>

<table class="form roomy" style="width: 75%; margin-top: 6mm;" align="center">
    <tr>
        <td class="label" style="width: 60%; text-align: center;">{{ __('receipt.documents_page.branch') }}</td>
        <td class="label" style="width: 40%; text-align: center;">{{ __('receipt.documents_page.folder_colour') }}</td>
    </tr>
    @foreach (__('receipt.documents_page.branches') as $code => [$name, $colour])
        <tr>
            <td>{{ $name }}</td>
            <td style="text-align: center; background-color: {{ $branchShades[$code][0] ?? '#ffffff' }};">{{ $colour }}</td>
        </tr>
    @endforeach
</table>

<div class="section-title" style="margin-top: 7mm;">{{ __('receipt.documents_page.stages_title') }}</div>

<div class="documents">
    @foreach (__('receipt.documents_page.stages') as $index => $stage)
        <div>{{ $index + 1 }}- {{ $stage }}</div>
    @endforeach
</div>
