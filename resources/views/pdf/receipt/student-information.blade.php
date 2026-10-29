{{-- Page 3: the student information form for the external examinations. --}}
<table style="width: 100%;">
    <tr>
        <td style="width: 50%;">{{ __('receipt.letter.directorate') }}</td>
        <td style="width: 50%; text-align: left;">{{ __('receipt.info.department') }}</td>
    </tr>
</table>

<div class="page-heading" style="font-size: 16pt;">{{ __('receipt.info.title') }}</div>
<div class="year">{{ __('receipt.info.academic_year') }} <bdo dir="ltr">{{ $academicYear }}</bdo></div>
<table style="width: 100%; margin-top: 2mm;">
    <tr>
        <td class="page-heading" style="width: 50%;">{{ __('receipt.info.branch') }} {!! $value($branch) !!}</td>
        <td class="page-heading" style="width: 50%;">{{ __('receipt.info.specialization') }} {!! $value($specialization) !!}</td>
    </tr>
</table>

<table class="form roomy" style="margin-top: 14mm;">
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

<table class="form roomy" style="margin-top: 14mm;">
    <tr>
        <td class="label" style="width: 50%;">{{ __('receipt.birth_date') }}</td>
        <td style="text-align: center;"><bdo dir="ltr">{!! $value($application->birth_date?->format('d/m/Y')) !!}</bdo></td>
    </tr>
    <tr>
        <td class="label">{{ __('receipt.gender') }}</td>
        <td style="text-align: center;">{!! $value($application->gender !== null ? __('applications.gender.'.$application->gender->value) : null) !!}</td>
    </tr>
</table>

<table class="form roomy" style="margin-top: 14mm;">
    <tr>
        <td class="label" style="width: 50%;">{{ __('receipt.info.mother_name') }}</td>
        <td style="text-align: center;">{!! $value($motherName) !!}</td>
    </tr>
    <tr>
        <td class="label">{{ __('receipt.phone') }}</td>
        <td class="ltr" style="text-align: center;">{!! $value($application->phone) !!}</td>
    </tr>
</table>

{{-- The committee's signatures, pushed down to the bottom of the page. --}}
<div style="padding-top: 68mm;"></div>
<table class="form roomy">
    <tr>
        @foreach (['member', 'member', 'member', 'rapporteur'] as $role)
            <td class="label" style="width: 25%; text-align: center;">{{ __('receipt.info.'.$role) }}</td>
        @endforeach
    </tr>
    <tr>
        @for ($column = 0; $column < 4; $column++)
            <td style="height: 30mm;">&nbsp;</td>
        @endfor
    </tr>
</table>
