<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Arr;

enum CommunicationTemplateKey: string implements HasLabel
{
    case InvoiceIssued = 'invoice_issued';
    case PaymentReminder = 'payment_reminder';
    case PaymentReceipt = 'payment_receipt';
    case MemberWelcome = 'member_welcome';
    case MembershipExpiring = 'membership_expiring';
    case MembershipRenewed = 'membership_renewed';
    case MembershipExpired = 'membership_expired';
    case InvoiceOverdue = 'invoice_overdue';
    case MemberInactive = 'member_inactive';
    case EnquiryFollowUp = 'enquiry_follow_up';

    public function getLabel(): string
    {
        return __("app.communication.templates.{$this->value}");
    }

    public function getDescription(): string
    {
        return __("app.communication.events.{$this->value}");
    }

    /** Determine whether this event can be dispatched automatically. */
    public function isAutomatic(): bool
    {
        return $this !== self::PaymentReminder;
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Invoice {{ invoice_number }} - {{ status }}',
            self::PaymentReminder => 'Payment reminder - Invoice {{ invoice_number }}',
            self::PaymentReceipt => 'Payment received - {{ invoice_number }}',
            self::MemberWelcome => 'Welcome to {{ gym_name }}',
            self::MembershipExpiring => 'Your membership ends on {{ end_date }}',
            self::MembershipRenewed => 'Your membership has been renewed',
            self::MembershipExpired => 'Your membership has expired',
            self::InvoiceOverdue => 'Payment overdue - Invoice {{ invoice_number }}',
            self::MemberInactive => 'We miss seeing you at {{ gym_name }}',
            self::EnquiryFollowUp => 'Following up from {{ gym_name }}',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Hi {{ member_name }}, your invoice {{ invoice_number }} is {{ status }}. Total: {{ total }}. Amount due: {{ due }}.',
            self::PaymentReminder => 'Hi {{ member_name }}, this is a reminder that {{ due }} remains due for invoice {{ invoice_number }}.',
            self::PaymentReceipt => 'Hi {{ member_name }}, we received your payment of {{ payment_amount }} for invoice {{ invoice_number }}. Remaining due: {{ due }}.',
            self::MemberWelcome => 'Hi {{ member_name }}, welcome to {{ gym_name }}. Your member ID is {{ member_code }}.',
            self::MembershipExpiring => 'Hi {{ member_name }}, your {{ plan_name }} membership at {{ gym_name }} ends on {{ end_date }}.',
            self::MembershipRenewed => 'Hi {{ member_name }}, your {{ plan_name }} membership at {{ gym_name }} has been renewed from {{ start_date }} until {{ end_date }}.',
            self::MembershipExpired => 'Hi {{ member_name }}, your {{ plan_name }} membership at {{ gym_name }} expired on {{ end_date }}. Please contact us to renew.',
            self::InvoiceOverdue => 'Hi {{ member_name }}, payment of {{ due }} for invoice {{ invoice_number }} was due on {{ due_date }}.',
            self::MemberInactive => 'Hi {{ member_name }}, we have not seen you at {{ gym_name }} for {{ inactive_days }} days. We would love to welcome you back.',
            self::EnquiryFollowUp => 'Hi {{ enquiry_name }}, we are following up about your interest in {{ interested_in }}. Please reply if you would like more information.',
        };
    }

    /** @return list<string> */
    public function variables(): array
    {
        return match ($this) {
            self::InvoiceIssued => ['member_name', 'invoice_number', 'status', 'total', 'paid', 'due', 'gym_name'],
            self::PaymentReminder => ['member_name', 'invoice_number', 'status', 'total', 'paid', 'due', 'due_date', 'gym_name'],
            self::PaymentReceipt => ['member_name', 'invoice_number', 'status', 'total', 'paid', 'due', 'payment_amount', 'gym_name'],
            self::MemberWelcome => ['member_name', 'member_code', 'gym_name'],
            self::MembershipExpiring => ['member_name', 'member_code', 'plan_name', 'end_date', 'gym_name'],
            self::MembershipRenewed => ['member_name', 'member_code', 'plan_name', 'start_date', 'end_date', 'gym_name'],
            self::MembershipExpired => ['member_name', 'member_code', 'plan_name', 'end_date', 'gym_name'],
            self::InvoiceOverdue => ['member_name', 'invoice_number', 'status', 'total', 'paid', 'due', 'due_date', 'gym_name'],
            self::MemberInactive => ['member_name', 'member_code', 'inactive_days', 'last_visit_date', 'gym_name'],
            self::EnquiryFollowUp => ['enquiry_name', 'interested_in', 'follow_up_date', 'lead_owner', 'gym_name'],
        };
    }

    /**
     * Return safe example values for previews and test deliveries.
     *
     * @return array<string, string>
     */
    public function sampleVariables(): array
    {
        return Arr::only([
            'member_name' => 'Taylor Member',
            'member_code' => 'MEM-001',
            'invoice_number' => 'INV-1001',
            'status' => 'Issued',
            'total' => '1,500.00',
            'paid' => '500.00',
            'due' => '1,000.00',
            'due_date' => now()->addDays(7)->format('d M Y'),
            'payment_amount' => '500.00',
            'gym_name' => 'Demo Gym',
            'plan_name' => 'Premium',
            'start_date' => now()->format('d M Y'),
            'end_date' => now()->addDays(30)->format('d M Y'),
            'inactive_days' => '14',
            'last_visit_date' => now()->subDays(14)->format('d M Y'),
            'enquiry_name' => 'Alex Morgan',
            'interested_in' => 'Strength training',
            'follow_up_date' => now()->addDays(2)->format('d M Y'),
            'lead_owner' => 'Sam Staff',
        ], $this->variables());
    }
}
