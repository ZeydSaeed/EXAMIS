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
        table.ids { margin-top: 1.5mm; border: 3px double #000000; }
        table.ids td { width: 62mm; height: 13mm; border: 0.8px solid #000000; text-align: center; vertical-align: middle; }
        table.ids td.number { font-size: 20pt; }
        table.form-date { margin-top: 1mm; }
        table.form-date td { width: 124mm; height: 7mm; border: 0.8px solid #000000; text-align: center; vertical-align: middle; font-size: 11pt; }
        .form-note { font-size: 10.5pt; line-height: 1.6; margin-top: 1.5mm; padding: 1.5mm 2mm; border: 0.8px solid #000000; }
        {{-- The label stands apart so the note's later lines align under its first. --}}
        table.form-note-body { width: 100%; }
        table.form-note-body td { vertical-align: top; padding: 0; }
        table.form-note-body td.form-note-label { width: {{ $direction === 'rtl' ? '13mm' : '11mm' }}; padding-{{ $direction === 'rtl' ? 'left' : 'right' }}: 1.5mm; }
        .title { text-align: center; font-size: 13.5pt; margin-top: 1.5mm; }
        table.form { width: 100%; margin-top: 2mm; }
        table.form td { border: 0.8px solid #000000; padding: 1mm 1.6mm; height: 7.6mm; vertical-align: middle; }
        td.label { background-color: {{ $shades[0] }}; }
        td.small { font-size: 10pt; }
        td.choice { width: 14mm; height: 12mm; border: 1px solid #000000; background-color: {{ $shades[1] }}; }
        table.form td.nested { padding: 1mm; }
        table.address { width: 100%; }
        table.form table.address td { border: 0.5px solid #808080; padding: 0.6mm 1mm; height: 5mm; text-align: center; }
        table.form table.address td.sub-head { background-color: {{ $shades[1] }}; font-size: 9pt; height: 4mm; }
        {{-- These two look the same on every form, whatever the branch colour. --}}
        table.form td.school-section { background-color: #e6e6e6; color: #8b0000; text-align: center; font-size: 12pt; height: 6.5mm; }
        table.form td.documents-section { background-color: #e6e6e6; color: #000000; text-align: center; font-size: 12pt; height: 6.5mm; }
        table.form td.head { background-color: {{ $shades[0] }}; text-align: center; height: 6mm; }
        table.form td.write { height: 8mm; }
        table.form td.doc { height: 8.4mm; }
        table.form td.sign { height: 9.8mm; }
        table.form td.tall { height: 16.5mm; vertical-align: top; }
        table.form td.tall-centred { height: 16.5mm; vertical-align: top; text-align: center; }
        table.form.line { margin-top: 0; }
        table.form.line td { border-top: none; }
        table.form td.fit { white-space: nowrap; }
        .ltr { direction: ltr; }
        .note { font-size: 11pt; margin-top: 1.5mm; }
        .page-heading { text-align: center; font-size: 18pt; margin-top: 6mm; }
        .letter-head { font-size: 15pt; line-height: 2; color: #000000; padding-top: 16mm; margin-right: 8mm; }
        .letter-subject { font-size: 15pt; color: #000000; text-align: center; margin-top: 12mm; }
        .letter-greeting { margin-bottom: 4mm; }
        .letter { font-size: 14pt; line-height: 2.4; margin: 10mm 8mm 0; }
        table.letter-signature td { font-size: 14pt; padding: 1.5mm 0; text-align: center; }
        table.roomy td { font-size: 13pt; height: 13mm; }
        {{-- Shorter rows, so the documents page fits under the page head. --}}
        table.folders td { font-size: 13pt; height: 9mm; }
        {{-- One size across the whole student information page. --}}
        .info { font-size: 13pt; }
        .info-centred { font-size: 13pt; text-align: center; margin-top: 3mm; }
        table.info-signatures td { font-size: 13pt; text-align: center; padding: 0; }
        .section-title { font-size: 15pt; text-decoration: underline; margin-top: 2mm; }
        .documents { font-size: 12.5pt; line-height: 1.9; margin-top: 3mm; }
        table.card { width: 80%; }
    </style>
</head>
<body>
    @include('pdf.receipt.page-head', ['rule' => false])
    <table class="form-date" align="center">
        <tr>
            <td>{{ __('receipt.created_at') }} <bdo dir="ltr">{{ ($application->submitted_at ?? now())->timezone(config('app.display_timezone', 'Asia/Baghdad'))->format('d/m/Y h:i:s A') }}</bdo></td>
        </tr>
    </table>
    @include('pdf.receipt.page-rule')

    <div class="title">
        {{ __('receipt.title') }} {{ __('receipt.academic_year') }} <bdo dir="ltr">{{ $academicYear }}</bdo><br>
        ({{ __('receipt.branch') }}: {!! $value($branch) !!}، {{ __('receipt.specialization') }}: {!! $value($specialization) !!})
    </div>

    {{-- The student's information, as submitted, on a six-column grid. --}}
    <table class="form">
        <tr>
            @foreach ([24, 30, 10, 12, 11, 13] as $width)
                <td style="width: {{ $width }}%; height: 0; padding: 0; border: none;"></td>
            @endforeach
        </tr>
        <tr>
            <td class="label">{{ __('receipt.full_name') }}</td>
            <td>{!! $value($fullName) !!}</td>
            <td class="label">{{ __('receipt.gender') }}</td>
            <td colspan="3">{!! $value($application->gender !== null ? __('applications.gender.'.$application->gender->value) : null) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.mother_name') }}</td>
            <td>{!! $value($motherName) !!}</td>
            <td class="label">{{ __('receipt.birth_date') }}</td>
            <td style="text-align: center;"><bdo dir="ltr">{!! $value($application->birth_date?->format('d/m/Y')) !!}</bdo></td>
            <td class="label">{{ __('receipt.birth_place') }}</td>
            <td>{!! $value($application->birthPlace()) !!}</td>
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
        {{-- The form no longer asks for this; only older applications have it. --}}
        @if (filled($application->other_info))
            <tr>
                <td class="label">{{ __('receipt.other_info') }}</td>
                <td colspan="5">{!! $value($application->other_info) !!}</td>
            </tr>
        @endif
    </table>

    {{-- Left blank for the school to fill in by hand. Every label starts on the
         same column at the same width, on one six-column grid. --}}
    @php
        $label = fn (string $key) => rtrim(__('receipt.'.$key), ': ');
    @endphp
    <table class="form">
        <tr>
            @foreach ([34, 20, 8, 8, 12, 18] as $width)
                <td style="width: {{ $width }}%; height: 0; padding: 0; border: none;"></td>
            @endforeach
        </tr>
        <tr>
            <td colspan="6" class="school-section">{{ __('receipt.school_section') }}</td>
        </tr>
        @foreach (['last_school_name', 'graduated_school'] as $field)
            <tr>
                <td class="label small write fit" nowrap="nowrap">{{ $label($field) }}</td>
                <td colspan="5">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="label small write fit" nowrap="nowrap">{{ $label('last_grade') }}</td>
            <td>{!! $value($application->last_grade) !!}</td>
            <td class="label small fit" nowrap="nowrap">{{ $label('result') }}</td>
            <td>&nbsp;</td>
            <td class="label small fit" nowrap="nowrap">{{ $label('school_year') }}</td>
            <td>&nbsp;</td>
        </tr>
        @foreach (['failed_years', 'dropout_years'] as $field)
            <tr>
                <td class="label small write fit" nowrap="nowrap">{{ $label($field) }}</td>
                <td colspan="5">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    {{-- Documents issued to the student, in a table of their own. --}}
    <table class="form">
        <tr>
            <td colspan="3" class="documents-section">{{ $label('documents') }}</td>
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

    {{-- One grid, so the principal's name starts where the fingerprint box does. --}}
    <table class="form">
        <tr>
            @foreach ([13, 37, 17, 33] as $width)
                <td style="width: {{ $width }}%; height: 0; padding: 0; border: none;"></td>
            @endforeach
        </tr>
        <tr>
            <td colspan="2" class="tall-centred">{{ $label('student_signature') }}</td>
            <td colspan="2" class="tall-centred">{{ $label('student_fingerprint') }}</td>
        </tr>
        <tr>
            <td class="label small sign fit" nowrap="nowrap">{{ $label('school_name') }}</td>
            <td>&nbsp;</td>
            <td class="label small fit" nowrap="nowrap">{{ $label('principal_name') }}</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td colspan="2" class="tall">{{ __('receipt.school_stamp') }}</td>
            {{-- Day and month left blank; read right to left the year comes last. --}}
            <td colspan="2" class="tall">{{ __('receipt.principal_signature') }}<br><br>{{ __('receipt.date') }} <bdo dir="ltr">{{ $generatedAt->year }} /&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</bdo></td>
        </tr>
    </table>

    <div class="note">{{ __('receipt.stamp_note') }}</div>

    <div class="form-note">
        <table class="form-note-body">
            <tr>
                <td class="form-note-label">{{ __('receipt.form_note_label') }}</td>
                <td>{{ __('receipt.form_note') }}</td>
            </tr>
        </table>
    </div>

    <pagebreak />
    @include('pdf.receipt.request-letter')

    <pagebreak />
    @include('pdf.receipt.student-information')

    <pagebreak />
    @include('pdf.receipt.required-documents')

    @if ($testCentre !== null)
        <pagebreak />
        @include('pdf.receipt.test-form')
    @endif

    <pagebreak />
    @include('pdf.receipt.pledge')
</body>
</html>
