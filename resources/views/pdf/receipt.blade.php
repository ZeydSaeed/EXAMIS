@php
    $value = fn ($text) => filled($text) ? e((string) $text) : '&nbsp;';
@endphp
<html dir="{{ $direction }}">
<head>
    <style>
        body { font-family: {{ $font }}; font-size: 11pt; color: #000000; }
        table { border-collapse: collapse; }
        .head td { vertical-align: top; }
        .committee { font-size: 13pt; font-weight: bold; line-height: 1.9; }
        .photo { width: 35mm; height: 45mm; padding: 0; border: 1.2px solid #70ad47; text-align: center; vertical-align: middle; font-weight: bold; font-size: 11pt; }
        .title { text-align: center; font-size: 15pt; font-weight: bold; margin-top: 1mm; }
        .year { text-align: center; font-size: 14pt; font-weight: bold; margin: 1mm 0 1mm; }
        table.form { width: 100%; margin-top: 1.8mm; }
        table.form td { border: 0.8px solid #000000; padding: 1mm 1.6mm; height: 7.6mm; vertical-align: middle; }
        td.label { background-color: #ffc000; font-weight: bold; }
        td.head { background-color: #ffc000; font-weight: bold; text-align: center; height: 4.6mm; }
        td.center { text-align: center; }
        .ltr { direction: ltr; }
        .note { font-size: 11pt; font-weight: bold; margin-top: 1.5mm; }
        table.receipt { width: 100%; margin-top: 3mm; border-top: 0.6px solid #9ca3af; }
        table.receipt td { font-size: 8.5pt; color: #374151; vertical-align: middle; padding-top: 1.5mm; }
        .reference { font-size: 12pt; font-weight: bold; color: #000000; direction: ltr; }
    </style>
</head>
<body>
    <table class="head" style="width: 100%;">
        <tr>
            <td style="width: 52%;" class="committee">
                {{ __('receipt.committee') }}<br>
                {{ __('receipt.preliminary_exams') }}<br>
                {{ __('receipt.department') }}
            </td>
            <td style="width: 22%; text-align: center;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height: 28mm;">
                @endif
            </td>
            <td style="width: 26%; text-align: left;">
                <table style="margin-left: 0;">
                    <tr>
                        <td class="photo">
                            @if ($hasPhoto)
                                <img src="var:photo" style="width: 35mm; height: 45mm;">
                            @else
                                {{ __('receipt.photo') }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="title">{{ __('receipt.title') }}</div>
    <div class="year">{{ __('receipt.academic_year') }} <bdo dir="ltr">{{ $application->examPeriod->academic_year }}</bdo></div>

    <table class="form">
        <tr>
            <td class="label" style="width: 40%;">{{ __('receipt.last_school') }}</td>
            <td>{!! $value($application->school_name) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.intermediate_school') }}</td>
            <td>{!! $value($application->intermediate_school_name) !!}</td>
        </tr>
    </table>

    <table class="form">
        <tr>
            <td class="label" style="width: 18%;">{{ __('receipt.full_name') }}</td>
            @if ($nameParts)
                @foreach ($nameParts as $part)
                    <td class="center" style="width: 16.4%;">{{ $part }}</td>
                @endforeach
            @else
                <td colspan="4" class="center">{!! $value($application->full_name_ar) !!}</td>
            @endif
            <td class="center" style="width: 16.4%;">{!! $value($application->surname) !!}</td>
        </tr>
    </table>

    <table class="form">
        <tr>
            <td class="label" rowspan="2" style="width: 22%; text-align: center; font-size: 12pt;">{{ __('receipt.birth_date') }}</td>
            <td class="head" style="width: 26%;">{{ __('receipt.day') }}</td>
            <td class="head" style="width: 26%;">{{ __('receipt.month') }}</td>
            <td class="head" style="width: 26%;">{{ __('receipt.year') }}</td>
        </tr>
        <tr>
            <td class="center ltr">{!! $value($application->birth_date?->format('d')) !!}</td>
            <td class="center ltr">{!! $value($application->birth_date?->format('m')) !!}</td>
            <td class="center ltr">{!! $value($application->birth_date?->format('Y')) !!}</td>
        </tr>
    </table>

    <table class="form" style="width: 50%;">
        <tr>
            <td class="label" style="width: 44%;">{{ __('receipt.gender') }}</td>
            <td class="center">{!! $value($application->gender !== null ? __('applications.gender.'.$application->gender->value) : null) !!}</td>
        </tr>
    </table>

    <table class="form">
        <tr>
            <td class="label" style="width: 22%;">{{ __('receipt.mother_name') }}</td>
            @if ($motherNameParts)
                @foreach ($motherNameParts as $part)
                    <td class="center" style="width: 26%;">{{ $part }}</td>
                @endforeach
            @else
                <td colspan="3" class="center">{!! $value($application->mother_name_ar) !!}</td>
            @endif
        </tr>
    </table>

    <table class="form">
        <tr>
            <td class="label" rowspan="2" style="width: 18%; text-align: center;">{{ __('receipt.address') }}</td>
            <td class="head" style="width: 20.5%;">{{ __('receipt.district') }}</td>
            <td class="head" style="width: 20.5%;">{{ __('receipt.mahalla') }}</td>
            <td class="head" style="width: 20.5%;">{{ __('receipt.alley') }}</td>
            <td class="head" style="width: 20.5%;">{{ __('receipt.house') }}</td>
        </tr>
        <tr>
            <td class="center">{!! $value($application->district) !!}</td>
            <td class="center ltr">{!! $value($application->mahalla) !!}</td>
            <td class="center ltr">{!! $value($application->alley) !!}</td>
            <td class="center ltr">{!! $value($application->house_number) !!}</td>
        </tr>
        <tr>
            <td class="label" style="text-align: center;">{{ __('receipt.phone') }}</td>
            <td colspan="4" class="ltr">{!! $value($application->phone) !!}</td>
        </tr>
    </table>

    <table class="form">
        <tr>
            <td class="label" style="width: 27%;">{{ __('receipt.last_grade') }}</td>
            <td>{!! $value($application->last_grade) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.failed_years') }}</td>
            <td>{!! $value($application->failed_years) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.left_years') }}</td>
            <td>{!! $value($application->left_years) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ __('receipt.other_info') }}</td>
            <td>{!! $value($application->other_info) !!}</td>
        </tr>
    </table>

    <table class="form">
        <tr>
            <td class="label" rowspan="5" style="width: 9%; text-align: center; font-size: 9.5pt;">{{ __('receipt.issued_documents') }}</td>
            <td class="head" style="width: 14%;">{{ __('receipt.document_number') }}</td>
            <td class="head" style="width: 16%;">{{ __('receipt.document_date') }}</td>
            <td class="head">{{ __('receipt.document_recipient') }}</td>
        </tr>
        @foreach ($issuedDocuments as $document)
            <tr>
                <td class="center ltr" style="height: 6.6mm;">{!! $value($document['number'] ?? null) !!}</td>
                <td class="center ltr" style="height: 6.6mm;">{!! $value($document['date'] ?? null) !!}</td>
                <td style="height: 6.6mm;">{!! $value($document['recipient'] ?? null) !!}</td>
            </tr>
        @endforeach
    </table>

    <table class="form">
        <tr>
            <td class="label" style="width: 17%; height: 11mm;">{{ __('receipt.student_signature') }}</td>
            <td style="width: 29%;">&nbsp;</td>
            <td class="label" style="width: 21%;">{{ __('receipt.student_fingerprint') }}</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td class="label" style="height: 11mm;">{{ __('receipt.school_name') }}</td>
            <td>&nbsp;</td>
            <td class="label">{{ __('receipt.principal_name') }}</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td class="label" style="height: 11mm;">{{ __('receipt.school_stamp') }}</td>
            <td>&nbsp;</td>
            <td class="label">{{ __('receipt.principal_signature') }}</td>
            <td>&nbsp;</td>
        </tr>
    </table>

    <div class="note">{{ __('receipt.stamp_note') }}</div>

    <table class="receipt">
        <tr>
            <td style="width: 78%;">
                {{ __('receipt.reference_number') }}: <span class="reference">{{ $application->reference_number }}</span>
                — {{ __('receipt.status') }}: <b>{{ $application->status->label() }}</b>
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
</body>
</html>
