<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CommunicationDeliveryStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return __("app.communication.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }
}
