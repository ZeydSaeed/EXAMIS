@php
    $value = fn ($text) => filled($text) ? e((string) $text) : '&nbsp;';
@endphp
<html dir="{{ $direction }}">
<head>
    <style>
        body { font-family: {{ $font }}; font-size: 11pt; font-weight: bold; color: #000000; }
        table { border-collapse: collapse; }
        .head td { vertical-align: top; }
        .committee { text-align: center; font-size: 13pt; line-height: 1.9; }
        .head td.photo { width: 28mm; height: 36mm; padding: 0; border: 1px solid #70ad47; text-align: center; vertical-align: middle; font-size: 10pt; }
        .title { text-align: center; font-size: 15pt; margin-top: 1mm; }
        .year { text-align: center; font-size: 14pt; margin: 1mm 0 1mm; }
        table.form { width: 100%; margin-top: 2mm; }
        table.form td { border: 0.8px solid #000000; padding: 1mm 1.6mm; height: 7.6mm; vertical-align: middle; }
        td.label { background-color: {{ $shades[0] }}; }
        td.small { font-size: 10pt; }
        td.choice { width: 14mm; height: 12mm; border: 1px solid #000000; background-color: {{ $shades[1] }}; }
        table.form td.nested { padding: 1mm; }
        table.address { width: 100%; }
        table.form table.address td { border: 0.5px solid #808080; padding: 0.6mm 1mm; height: 5mm; text-align: center; }
        table.form table.address td.sub-head { background-color: {{ $shades[1] }}; font-size: 9pt; height: 4mm; }
        table.form td.section { background-color: {{ $shades[0] }}; text-align: center; font-size: 12pt; height: 6.5mm; }
        table.form td.head { background-color: {{ $shades[0] }}; text-align: center; height: 6mm; }
        table.form td.write { height: 8.2mm; }
        table.form td.doc { height: 8.4mm; }
        table.form td.sign { height: 9.8mm; }
        table.form td.tall { height: 18mm; vertical-align: top; }
        table.form.line { margin-top: 0; }
        table.form.line td { border-top: none; }
        table.form td.fit { white-space: nowrap; }
        .ltr { direction: ltr; }
        .note { font-size: 11pt; margin-top: 1.5mm; }
        .page-heading { text-align: center; font-size: 18pt; margin-top: 6mm; }
        .letter { font-size: 14pt; line-height: 2.4; margin: 24mm 0 0; }
        table.letter-signature td { font-size: 14pt; padding: 1.5mm 0; text-align: center; }
        table.roomy td { font-size: 13pt; height: 13mm; }
        .section-title { font-size: 15pt; text-decoration: underline; margin-top: 2mm; }
        .documents { font-size: 12.5pt; line-height: 1.9; margin-top: 3mm; }
        table.card { width: 80%; }
        table.receipt { width: 100%; margin-top: 2mm; border-top: 0.6px solid #9ca3af; }
        table.receipt td { font-size: 8.5pt; color: #374151; vertical-align: middle; padding-top: 1.5mm; }
    </style>
</head>
<body>
    {{-- Committee on the right, logo centred on the page, photo on the left. --}}
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

    <div class="title">{{ __('receipt.title') }}</div>
    <div class="year">{{ __('receipt.academic_year') }} <bdo dir="ltr">{{ $academicYear }}</bdo></div>

    {{-- The student's information, as submitted, on a six-column grid. --}}
    <table class="form">
        <tr>
            <td class="label" style="width: 24%;">{{ __('receipt.branch') }}</td>
            <td style="width: 30%;">{!! $value($branch) !!}</td>
            <td colspan="2" class="label" style="width: 20%;">{{ __('receipt.specialization') }}</td>
            <td colspan="2" style="width: 26%;">{!! $value($specialization) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.full_name') }}</td>
            <td>{!! $value($fullName) !!}</td>
            <td colspan="2" class="label">{{ __('receipt.gender') }}</td>
            <td colspan="2">{!! $value($application->gender !== null ? __('applications.gender.'.$application->gender->value) : null) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.mother_name') }}</td>
            <td>{!! $value($motherName) !!}</td>
            <td class="label" style="width: 8%;">{{ __('receipt.birth_date') }}</td>
            <td style="width: 12%; text-align: center;"><bdo dir="ltr">{!! $value($application->birth_date?->format('d/m/Y')) !!}</bdo></td>
            <td class="label" style="width: 11%;">{{ __('receipt.birth_place') }}</td>
            <td style="width: 15%;">{!! $value($application->birthPlace()) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.address') }}</td>
            <td colspan="3" class="nested">
                <table class="address">
                    <tr>
                        <td class="sub-head" style="width: 32%;">{{ __('receipt.district') }}</td>
                        <td class="sub-head" style="width: 22.66%;">{{ __('receipt.mahalla') }}</td>
                        <td class="sub-head" style="width: 22.66%;">{{ __('receipt.alley') }}</td>
                        <td class="sub-head" style="width: 22.68%;">{{ __('receipt.house') }}</td>
                    </tr>
                    <tr>
                        <td>{!! $value($application->district) !!}</td>
                        <td class="ltr">{!! $value($application->mahalla) !!}</td>
                        <td class="ltr">{!! $value($application->alley) !!}</td>
                        <td class="ltr">{!! $value($application->house_number) !!}</td>
                    </tr>
                </table>
            </td>
            <td class="label">{{ __('receipt.phone') }}</td>
            <td class="ltr" style="text-align: center;">{!! $value($application->phone) !!}</td>
        </tr>
        <tr>
            <td class="label" style="font-size: 9.5pt;">{{ __('receipt.last_school') }}</td>
            <td>{!! $value($application->school_name) !!}</td>
            <td colspan="2" class="label">{{ __('receipt.school_governorate') }}</td>
            <td colspan="2">{!! $value($schoolGovernorate) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.other_info') }}</td>
            <td colspan="5">{!! $value($application->other_info) !!}</td>
        </tr>
    </table>

    {{-- Left blank for the school to fill in by hand, on a six-column grid. --}}
    @php
        $label = fn (string $key) => rtrim(__('receipt.'.$key), ': ');
    @endphp
    <table class="form">
        <tr>
            @for ($column = 0; $column < 6; $column++)
                <td style="width: 16.66%; height: 0; padding: 0; border: none;"></td>
            @endfor
        </tr>
        <tr>
            <td colspan="6" class="section">{{ __('receipt.school_section') }}</td>
        </tr>
        @foreach (['last_school_name', 'graduated_school'] as $field)
            <tr>
                <td colspan="6" class="write">{{ __('receipt.'.$field) }}</td>
            </tr>
        @endforeach
    </table>
    {{-- One table per row so each label is only as wide as its text. --}}
    <table class="form line">
        <tr>
            <td class="label small write fit" nowrap="nowrap">{{ $label('last_grade') }}</td>
            <td style="width: 20%;">{!! $value($application->last_grade) !!}</td>
            <td class="label small fit" nowrap="nowrap">{{ $label('result') }}</td>
            <td style="width: 20%;">&nbsp;</td>
            <td class="label small fit" nowrap="nowrap">{{ $label('school_year') }}</td>
            <td style="width: 20%;">&nbsp;</td>
        </tr>
    </table>
    <table class="form line">
        <tr>
            <td class="label small write fit" nowrap="nowrap">{{ $label('failed_years') }}</td>
            <td style="width: 32%;">&nbsp;</td>
            <td class="label small fit" nowrap="nowrap">{{ $label('dropout_years') }}</td>
            <td style="width: 32%;">&nbsp;</td>
        </tr>
    </table>
    <table class="form line">
        <tr>
            <td class="label small write fit" nowrap="nowrap">{{ $label('day_school') }}</td>
            <td style="width: 68%;">&nbsp;</td>
        </tr>
    </table>

    {{-- Documents issued to the student, in a table of their own. --}}
    <table class="form">
        <tr>
            <td colspan="3" class="section">{{ $label('documents') }}</td>
        </tr>
        <tr>
            <td class="head" style="width: 33.33%;">{{ __('receipt.document_number') }}</td>
            <td class="head" style="width: 33.33%;">{{ __('receipt.document_date') }}</td>
            <td class="head" style="width: 33.34%;">{{ __('receipt.document_addressee') }}</td>
        </tr>
        @for ($row = 0; $row < 3; $row++)
            <tr>
                <td class="doc">&nbsp;</td>
                <td class="doc">&nbsp;</td>
                <td class="doc">&nbsp;</td>
            </tr>
        @endfor
    </table>

    <table class="form">
        <tr>
            <td class="tall" style="width: 50%;">{{ __('receipt.student_signature') }}</td>
            <td class="tall" style="width: 50%;">{{ __('receipt.student_fingerprint') }}</td>
        </tr>
    </table>
    <table class="form line">
        <tr>
            <td class="label small sign fit" nowrap="nowrap">{{ $label('school_name') }}</td>
            <td style="width: 38%;">&nbsp;</td>
            <td class="label small fit" nowrap="nowrap">{{ $label('principal_name') }}</td>
            <td style="width: 38%;">&nbsp;</td>
        </tr>
    </table>
    <table class="form line">
        <tr>
            <td class="tall" style="width: 50%;">{{ __('receipt.school_stamp') }}</td>
            <td class="tall" style="width: 50%;">{{ __('receipt.principal_signature') }}<br><br>{{ __('receipt.date') }}</td>
        </tr>
    </table>

    <div class="note">{{ __('receipt.stamp_note') }}</div>

    <table class="receipt">
        <tr>
            <td style="width: 78%;">
                {{ __('receipt.status') }}: {{ $application->status->label() }}
                — {{ __('receipt.submitted_at') }}: <bdo dir="ltr">{{ $application->submitted_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('Y-m-d H:i') }}</bdo><br>
                {{ __('receipt.verify_hint') }}
                {!! __('receipt.generated_at', ['time' => '<bdo dir="ltr">'.e($generatedAt->timezone(config('app.display_timezone', config('app.timezone')))->format('Y-m-d H:i')).'</bdo>']) !!}
                — {{ __('receipt.point_in_time') }}
            </td>
            <td style="width: 22%; text-align: left;">
                <barcode code="{{ $verificationUrl }}" type="QR" size="0.55" error="M" disableborder="1" />
            </td>
        </tr>
    </table>

    <pagebreak />
    @include('pdf.receipt.request-letter')

    <pagebreak />
    @include('pdf.receipt.student-information')

    <pagebreak />
    @include('pdf.receipt.required-documents')

    @if ($hasTestForm)
        <pagebreak />
        @include('pdf.receipt.test-form')
    @endif

    <pagebreak />
    @include('pdf.receipt.student-card')
</body>
</html>
