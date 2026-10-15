<html dir="{{ $direction }}">
<head>
    <style>
        body { font-family: dejavusans; font-size: 11pt; color: #0b1849; }
        .header { border-bottom: 2px solid #0b1849; padding-bottom: 8px; margin-bottom: 14px; }
        .org { font-size: 15pt; font-weight: bold; color: #0b1849; }
        .title { font-size: 13pt; margin-top: 4px; }
        .reference-box { border: 1.5px solid #e4b028; padding: 10px; text-align: center; margin: 12px 0; }
        .reference-label { font-size: 10pt; color: #4b5563; }
        .reference { font-size: 20pt; font-weight: bold; letter-spacing: 1px; direction: ltr; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data td { border: 0.6px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        table.data td.label { width: 32%; background-color: #f3f4f6; font-weight: bold; }
        .ltr { direction: ltr; }
        .footer { margin-top: 18px; font-size: 9pt; color: #4b5563; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="org">{{ $organization }}</div>
        <div class="title">{{ __('receipt.title') }} — {{ $application->examPeriod->localizedName() }}</div>
    </div>

    <div class="reference-box">
        <div class="reference-label">{{ __('receipt.reference_number') }}</div>
        <div class="reference">{{ $application->reference_number }}</div>
    </div>

    <table class="data">
        <tr><td class="label">{{ __('receipt.full_name') }}</td><td>{{ $application->full_name_ar }}</td></tr>
        @if ($application->full_name_en)
            <tr><td class="label">{{ __('receipt.full_name_en') }}</td><td class="ltr">{{ $application->full_name_en }}</td></tr>
        @endif
        <tr><td class="label">{{ __('receipt.mother_name') }}</td><td>{{ $application->mother_name_ar }}</td></tr>
        <tr><td class="label">{{ __('receipt.birth_date') }}</td><td class="ltr">{{ $application->birth_date?->format('Y-m-d') }}</td></tr>
        <tr><td class="label">{{ __('receipt.governorate') }}</td><td>{{ $labels['governorate'] }}</td></tr>
        <tr><td class="label">{{ __('receipt.school') }}</td><td>{{ $application->school_name }}</td></tr>
        <tr><td class="label">{{ __('receipt.branch') }}</td><td>{{ $labels['branch'] }}</td></tr>
        <tr><td class="label">{{ __('receipt.category') }}</td><td>{{ $labels['category'] }}</td></tr>
        <tr><td class="label">{{ __('receipt.exam_period') }}</td><td>{{ $application->examPeriod->localizedName() }} (<bdo dir="ltr">{{ $application->examPeriod->academic_year }}</bdo>)</td></tr>
        <tr><td class="label">{{ __('receipt.submitted_at') }}</td><td class="ltr">{{ $application->submitted_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('Y-m-d H:i') }}</td></tr>
        <tr><td class="label">{{ __('receipt.status') }}</td><td class="status">{{ $application->status->label() }}</td></tr>
    </table>

    <table style="width: 100%; margin-top: 16px;">
        <tr>
            <td style="width: 70%; vertical-align: top; font-size: 9.5pt;">
                <p>{{ __('receipt.notice') }}</p>
                <p>{{ __('receipt.verify_hint') }}</p>
            </td>
            <td style="width: 30%; text-align: center;">
                <barcode code="{{ $verificationUrl }}" type="QR" size="0.9" error="M" disableborder="1" />
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ __('receipt.generated_at', ['time' => $generatedAt->timezone(config('app.display_timezone', config('app.timezone')))->format('Y-m-d H:i')]) }}
        — {{ __('receipt.point_in_time') }}
    </div>
</body>
</html>
