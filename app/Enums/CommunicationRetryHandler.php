<?php

namespace App\Enums;

enum CommunicationRetryHandler: string
{
    case Communication = 'communication';
    case InvoiceIssued = 'invoice_issued';
    case PaymentReminder = 'payment_reminder';
    case PaymentReceipt = 'payment_receipt';
}
