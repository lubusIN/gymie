<?php

namespace App\Support\Filament;

use App\Enums\Status;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

final class StatusAction
{
    public static function make(string $name, Status $status): Action
    {
        $icon = match ($status) {
            Status::Active, Status::Done, Status::Paid => Heroicon::OutlinedCheckCircle,
            Status::Pending, Status::Expiring => Heroicon::OutlinedClock,
            Status::Overdue => Heroicon::OutlinedExclamationTriangle,
            Status::Inactive, Status::Expired, Status::Lost, Status::Cancelled => Heroicon::OutlinedXCircle,
            default => throw new InvalidArgumentException("Status [{$status->value}] does not have an action icon."),
        };

        return Action::make($name)
            ->icon($icon)
            ->modalIcon($icon);
    }
}
