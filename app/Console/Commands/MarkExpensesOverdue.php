<?php

namespace App\Console\Commands;

use App\Enums\Status;
use App\Models\Expense;
use App\Support\AppConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('gymie:expenses')]
#[Description('Mark unpaid expenses as overdue based on their due date')]
class MarkExpensesOverdue extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today(AppConfig::timezone())->toDateString();

        $updatedCount = Expense::query()
            ->where('status', Status::Pending->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->update(['status' => Status::Overdue->value]);

        $this->info("{$updatedCount} expense(s) marked as overdue.");

        return self::SUCCESS;
    }
}
