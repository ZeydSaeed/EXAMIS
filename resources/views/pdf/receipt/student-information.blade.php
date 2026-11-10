{{-- Page 3: the student information form for the external examinations. --}}
@include('pdf.receipt.page-head')

<table style="width: 100%; margin-top: 5mm;">
    <tr>
        <td class="info" style="width: 50%;">{{ __('receipt.letter.directorate') }}</td>
        <td class="info" style="width: 50%; text-align: left;">{{ __('receipt.info.department') }}</td>
    </tr>
</table>

<div class="info-centred">{{ __('receipt.info.title') }}</div>
<div class="info-centred">{{ __('receipt.info.academic_year') }} <bdo dir="ltr">{{ $academicYear }}</bdo></div>
<table style="width: 100%; margin-top: 3mm;">
    <tr>
        <td class="info" style="width: 50%; text-align: center;">{{ __('receipt.info.branch') }} {!! $value($branch) !!}</td>
        <td class="info" style="width: 50%; text-align: center;">{{ __('receipt.info.specialization') }} {!! $value($specialization) !!}</td>
    </tr>
</table>

<table class="form roomy" style="margin-top: 8mm;">
    <tr>
        @foreach (['first_name', 'father_name', 'grandfather_name', 'great_grandfather_name'] as $part)
            <td class="label" style="width: 25%; text-align: center;">{{ __('receipt.info.'.$part) }}</td>
        @endforeach
    </tr>
    <tr>
        @foreach (\App\Models\Application::NAME_PARTS as $part)
            <td style="text-align: center;">{!! $value($application->{$part}) !!}</td>
        @endforeach
    </tr>
</table>

<table class="form roomy" style="margin-top: 8mm;">
    <tr>
        <td class="label" style="width: 50%;">{{ __('receipt.birth_date') }}</td>
        <td style="text-align: center;"><bdo dir="ltr">{!! $value($application->birth_date?->format('d/m/Y')) !!}</bdo></td>
    </tr>
    <tr>
        <td class="label">{{ __('receipt.gender') }}</td>
        <td style="text-align: center;">{!! $value($application->gender !== null ? __('applications.gender.'.$application->gender->value) : null) !!}</td>
    </tr>
</table>

<table class="form roomy" style="margin-top: 8mm;">
    <tr>
        <td class="label" style="width: 50%;">{{ __('receipt.info.mother_name') }}</td>
        <td style="text-align: center;">{!! $value($motherName) !!}</td>
    </tr>
    <tr>
        <td class="label">{{ __('receipt.phone') }}</td>
        <td class="ltr" style="text-align: center;">{!! $value($application->phone) !!}</td>
    </tr>
</table>

{{-- Low on the page: the admissions committee's approval at the start of the line,
     then its signatures. --}}
<div style="position: absolute; bottom: 42mm; left: 12mm; right: 12mm;">
    <div class="info">{{ __('receipt.info.acceptance') }}</div>
    <table class="info-signatures" style="width: 100%; margin-top: 14mm;">
        <tr>
            @foreach (['member', 'member', 'chair'] as $role)
                <td style="width: 33%;">{{ __('receipt.info.'.$role) }}</td>
            @endforeach
        </tr>
    </table>
</div>
