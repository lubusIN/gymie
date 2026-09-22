<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Support\AppConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkInvoiceOverdue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gymie:invoices {--mark-overdue : Mark invoices as overdue based on due date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform operations on invoices (e.g., mark as overdue)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('mark-overdue')) {
            $this->info('No operation selected.');

            return self::SUCCESS;
        }

        $today = Carbon::today(AppConfig::timezone());

        $updatedCount = Invoice::query()
            ->whereIn('status', ['issued', 'partial'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today->toDateString())
            ->where('due_amount', '>', 0)
            ->update(['status' => 'overdue']);

        $this->info("{$updatedCount} invoice(s) marked as overdue.");

        return self::SUCCESS;
    }
}
