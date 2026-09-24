<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Lang;

enum Status: string implements HasColor, HasLabel
{
    case Upcoming = 'upcoming';
    case Pending = 'pending';
    case Done = 'done';
    case Active = 'active';
    case Inactive = 'inactive';
    case Issued = 'issued';
    case Cancelled = 'cancelled';
    case Refund = 'refund';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Partial = 'partial';
    case Ongoing = 'ongoing';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Renewed = 'renewed';
    case Lead = 'lead';
    case FollowUpScheduled = 'follow_up_scheduled';
    case Member = 'member';
    case Lost = 'lost';

    private const COLORS = [
        self::Upcoming->value => 'gray',
        self::Pending->value => 'warning',
        self::Done->value => 'success',
        self::Active->value => 'success',
        self::Inactive->value => 'danger',
        self::Issued->value => 'gray',
        self::Cancelled->value => 'danger',
        self::Refund->value => 'danger',
        self::Overdue->value => 'warning',
        self::Paid->value => 'success',
        self::Partial->value => 'info',
        self::Ongoing->value => 'info',
        self::Expiring->value => 'warning',
        self::Expired->value => 'danger',
        self::Renewed->value => 'success',
        self::Lead->value => 'info',
        self::FollowUpScheduled->value => 'warning',
        self::Member->value => 'success',
        self::Lost->value => 'danger',
    ];

    public function getLabel(): string
    {
        $key = 'app.status.'.$this->value;

        if (Lang::has($key)) {
            return __($key);
        }

        return $this->name;
    }

    public function getColor(): string
    {
        return self::COLORS[$this->value];
    }

    public static function valueOf(?self $status): ?string
    {
        return $status?->value;
    }
}
