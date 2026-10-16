<?php

namespace App\Support\Filament;

use App\Enums\Status;
use Filament\Support\Enums\Size;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

final class StatusBadge
{
    private function __construct() {}

    public static function render(
        Status|string|null $status,
        Size|string $size = Size::Large,
        ?string $class = null,
    ): HtmlString|string {
        $statusEnum = match (true) {
            $status instanceof Status => $status,
            is_string($status) => Status::tryFrom($status),
            default => null,
        };

        if (! $statusEnum) {
            return is_string($status) ? ucfirst($status) : '';
        }

        return new HtmlString(
            Blade::render(
                '<x-filament::badge :color="$color" :size="$size" :class="$class">{{ $label }}</x-filament::badge>',
                [
                    'class' => $class,
                    'color' => $statusEnum->getColor(),
                    'label' => $statusEnum->getLabel(),
                    'size' => $size,
                ],
            ),
        );
    }

    public static function heading(string $heading, Status $status): HtmlString
    {
        return new HtmlString(
            '<span class="inline-flex flex-wrap items-center gap-3">'
                .'<span>'.e($heading).'</span>'
                .self::render($status, Size::ExtraLarge, 'gymie-record-status-badge')
                .'</span>',
        );
    }
}
