<?php

namespace App\Support\Filament;

use App\Enums\Status;
use Illuminate\Contracts\Support\Htmlable;

trait HasStatusHeading
{
    public function getHeading(): string|Htmlable|null
    {
        $heading = $this->getTitle();
        $status = $this->getRecord()->getAttribute('status');

        if (! $status instanceof Status) {
            return $heading;
        }

        return StatusBadge::heading((string) $heading, $status);
    }
}
