<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StaffLeaveType: string implements HasLabel
{
    case Casual = 'casual';
    case Sick = 'sick';
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("app.staff_leave.types.{$this->value}");
    }
}
