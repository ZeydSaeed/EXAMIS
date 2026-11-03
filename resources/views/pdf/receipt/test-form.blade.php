{{-- Page 5, for specializations with a test centre: the test form; the decision is filled in by the committees. --}}
@include('pdf.receipt.page-head')

<div style="font-size: 14pt; margin-top: 5mm;">{{ __('receipt.test_form.centre') }}: {{ $testCentre }}</div>

<div class="page-heading">{{ __('receipt.test_form.title') }}</div>

<div class="letter" style="margin-top: 10mm; text-align: justify;">
    {{ __('receipt.test_form.student') }} ({!! $value($fullName) !!})
    {{ __('receipt.test_form.qualified') }} (<bdo dir="ltr">{{ $academicYear }}</bdo>)
    {{ __('receipt.test_form.branch') }} {!! $value($branch) !!} /
    {{ __('receipt.test_form.specialization') }} ({!! $value($specialization) !!})
</div>

<table style="margin-top: 8mm;" align="center">
    <tr>
        <td class="choice" style="background-color: {{ $testShades[1] }};">&nbsp;</td>
        <td style="font-size: 14pt; padding: 0 3mm;">{{ __('receipt.test_form.accepted') }}</td>
        <td style="width: 40mm;">&nbsp;</td>
        <td class="choice" style="background-color: {{ $testShades[1] }};">&nbsp;</td>
        <td style="font-size: 14pt; padding: 0 3mm;">{{ __('receipt.test_form.rejected') }}</td>
    </tr>
</table>

@foreach (['subcommittee' => ['head_of_department', 'technical_assistant', 'school_principal'], 'main_committee' => ['student_affairs', 'deputy_director', 'director']] as $committee => $signers)
    <div class="section-title" style="text-decoration: none; margin-top: 10mm;">{{ __('receipt.test_form.'.$committee) }}</div>
    <table class="form roomy">
        <tr>
            @foreach ($signers as $signer)
                <td style="width: 33.33%; text-align: center; background-color: {{ $testShades[0] }};">{{ __('receipt.test_form.'.$signer) }}</td>
            @endforeach
        </tr>
        <tr>
            @foreach ($signers as $signer)
                <td style="height: 24mm;">&nbsp;</td>
            @endforeach
        </tr>
    </table>
@endforeach
