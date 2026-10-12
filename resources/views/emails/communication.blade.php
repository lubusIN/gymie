@extends('emails.invoices.layout')

@section('content')
    <div style="font-size: 14px; line-height: 1.7; color: #111827;">
        {!! str($messageBody)->markdown([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ])->sanitizeHtml() !!}
    </div>
@endsection
