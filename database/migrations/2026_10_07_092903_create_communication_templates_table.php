<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('event');
            $table->string('name');
            $table->string('channel')->default('email');
            $table->string('subject', 150);
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        DB::table('communication_templates')->insert([
            [
                'key' => 'invoice_issued',
                'event' => 'invoice_issued',
                'name' => 'Invoice issued',
                'channel' => 'email',
                'subject' => 'Invoice {{ invoice_number }} - {{ status }}',
                'body' => 'Hi {{ member_name }}, your invoice {{ invoice_number }} is {{ status }}. Total: {{ total }}. Amount due: {{ due }}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_reminder',
                'event' => 'payment_reminder',
                'name' => 'Payment reminder',
                'channel' => 'email',
                'subject' => 'Payment reminder - Invoice {{ invoice_number }}',
                'body' => 'Hi {{ member_name }}, this is a reminder that {{ due }} remains due for invoice {{ invoice_number }}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_receipt',
                'event' => 'payment_receipt',
                'name' => 'Payment receipt',
                'channel' => 'email',
                'subject' => 'Payment received - {{ invoice_number }}',
                'body' => 'Hi {{ member_name }}, we received your payment of {{ payment_amount }} for invoice {{ invoice_number }}. Remaining due: {{ due }}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'member_welcome',
                'event' => 'member_welcome',
                'name' => 'Member welcome',
                'channel' => 'email',
                'subject' => 'Welcome to {{ gym_name }}',
                'body' => 'Hi {{ member_name }}, welcome to {{ gym_name }}. Your member ID is {{ member_code }}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'membership_expiring',
                'event' => 'membership_expiring',
                'name' => 'Membership expiring',
                'channel' => 'email',
                'subject' => 'Your membership ends on {{ end_date }}',
                'body' => 'Hi {{ member_name }}, your {{ plan_name }} membership at {{ gym_name }} ends on {{ end_date }}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'membership_renewed',
                'event' => 'membership_renewed',
                'name' => 'Membership renewed',
                'channel' => 'email',
                'subject' => 'Your membership has been renewed',
                'body' => 'Hi {{ member_name }}, your {{ plan_name }} membership at {{ gym_name }} has been renewed from {{ start_date }} until {{ end_date }}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'membership_expired',
                'event' => 'membership_expired',
                'name' => 'Membership expired',
                'channel' => 'email',
                'subject' => 'Your membership has expired',
                'body' => 'Hi {{ member_name }}, your {{ plan_name }} membership at {{ gym_name }} expired on {{ end_date }}. Please contact us to renew.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'invoice_overdue',
                'event' => 'invoice_overdue',
                'name' => 'Invoice overdue',
                'channel' => 'email',
                'subject' => 'Payment overdue - Invoice {{ invoice_number }}',
                'body' => 'Hi {{ member_name }}, payment of {{ due }} for invoice {{ invoice_number }} was due on {{ due_date }}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'member_inactive',
                'event' => 'member_inactive',
                'name' => 'Inactive member',
                'channel' => 'email',
                'subject' => 'We miss seeing you at {{ gym_name }}',
                'body' => 'Hi {{ member_name }}, we have not seen you at {{ gym_name }} for {{ inactive_days }} days. We would love to welcome you back.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'enquiry_follow_up',
                'event' => 'enquiry_follow_up',
                'name' => 'Enquiry follow-up',
                'channel' => 'email',
                'subject' => 'Following up from {{ gym_name }}',
                'body' => 'Hi {{ enquiry_name }}, we are following up about your interest in {{ interested_in }}. Please reply if you would like more information.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('communication_automations', function (Blueprint $table) {
            $table->id();
            $table->string('event')->unique();
            $table->foreignId('communication_template_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_automations');
        Schema::dropIfExists('communication_templates');
    }
};
