@extends('emails.invoices.layout')

@section('content')
    <div style="font-size: 18px; color: #111827; font-weight: 700;">
        {{ __('app.emails.test_heading') }}
    </div>

    <div style="font-size: 14px; color: #374151; margin-top: 12px; line-height: 1.6;">
        {{ __('app.emails.test_body', ['gym_name' => $gymName]) }}
    </div>
@endsection
