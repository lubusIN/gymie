@extends('emails.invoices.layout')

@section('content')
    <div style="font-size: 14px; color: #111827; line-height: 1.5;">
        {!! nl2br(e($messageBody)) !!}
    </div>

    @if (filled($note))
        <div style="margin-top: 14px; padding: 12px 14px; border: 1px solid #d1fae5; background: #ecfdf5;">
            <div style="font-size: 11px; letter-spacing: 1px; font-weight: 700; color: #065f46; text-transform: uppercase;">{{ __('app.fields.note') }}</div>
            <div style="margin-top: 6px; font-size: 13px; color: #111827; line-height: 1.4;">
                {{ $note }}
            </div>
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 18px; border-top: 1px solid #e5e7eb;">
        <tr>
            <td style="padding: 14px 0 0; font-size: 13px; color: #6b7280;">{{ __('app.fields.total') }}</td>
            <td style="padding: 14px 0 0; font-size: 13px; color: #111827; font-weight: 700;" align="right">{{ \App\Helpers\Helpers::formatCurrency((float) ($invoice->total_amount ?? 0)) }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 0 0; font-size: 13px; color: #6b7280;">{{ __('app.fields.paid') }}</td>
            <td style="padding: 10px 0 0; font-size: 13px; color: #111827; font-weight: 700;" align="right">{{ \App\Helpers\Helpers::formatCurrency((float) ($invoice->paid_amount ?? 0)) }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 0 0; font-size: 13px; color: #6b7280;">{{ __('app.fields.due') }}</td>
            <td style="padding: 10px 0 0; font-size: 13px; color: #111827; font-weight: 800;" align="right">{{ \App\Helpers\Helpers::formatCurrency((float) ($invoice->due_amount ?? 0)) }}</td>
        </tr>
        @if (filled($invoice->due_date))
            <tr>
                <td style="padding: 10px 0 0; font-size: 13px; color: #6b7280;">{{ __('app.fields.due_date') }}</td>
                <td style="padding: 10px 0 0; font-size: 13px; color: #111827; font-weight: 700;" align="right">{{ optional($invoice->due_date)->translatedFormat('d M Y') }}</td>
            </tr>
        @endif
    </table>

    <div style="margin-top: 18px; font-size: 12px; color: #6b7280;">
        {{ __('app.emails.reply_to_email') }}
    </div>
@endsection
