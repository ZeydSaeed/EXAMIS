{{-- Page 5, for the computer assembly and maintenance specialization only: the test form; the decision is filled in by the committees. --}}
<div style="padding-top: 20mm;"></div>
<table class="head" style="width: 100%;">
    <tr>
        <td style="font-size: 14pt; vertical-align: middle;">
            {{ __('receipt.test_form.centre') }}: {{ __('receipt.test_form.centre_name') }}
        </td>
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

<div class="page-heading">{{ __('receipt.test_form.title') }}</div>

<div class="letter" style="margin-top: 10mm;">
    {{ __('receipt.test_form.student') }} ({!! $value($fullName) !!})
    {{ __('receipt.test_form.qualified') }} (<bdo dir="ltr">{{ $academicYear }}</bdo>)<br>
    {{ __('receipt.test_form.branch') }} {!! $value($branch) !!} /
    {{ __('receipt.test_form.specialization') }} ({!! $value($specialization) !!})
</div>

<table style="margin-top: 8mm;" align="center">
    <tr>
        <td style="font-size: 14pt; padding: 0 3mm;">{{ __('receipt.test_form.yes') }}</td>
        <td class="choice">&nbsp;</td>
        <td style="width: 40mm;">&nbsp;</td>
        <td style="font-size: 14pt; padding: 0 3mm;">{{ __('receipt.test_form.no') }}</td>
        <td class="choice">&nbsp;</td>
    </tr>
</table>

@foreach (['subcommittee' => ['head_of_department', 'technical_assistant', 'school_principal'], 'main_committee' => ['student_affairs', 'deputy_director', 'director']] as $committee => $signers)
    <div class="section-title" style="text-decoration: none; margin-top: 10mm;">{{ __('receipt.test_form.'.$committee) }}</div>
    <table class="form roomy">
        <tr>
            @foreach ($signers as $signer)
                <td class="label" style="width: 33.33%; text-align: center;">{{ __('receipt.test_form.'.$signer) }}</td>
            @endforeach
        </tr>
        <tr>
            @foreach ($signers as $signer)
                <td style="height: 24mm;">&nbsp;</td>
            @endforeach
        </tr>
    </table>
@endforeach
