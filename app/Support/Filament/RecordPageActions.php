<?php

namespace App\Support\Filament;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

final class RecordPageActions
{
    private function __construct() {}

    public static function edit(): EditAction
    {
        return self::style(
            EditAction::make()->icon(Heroicon::OutlinedPencilSquare),
            'primary',
        );
    }

    public static function view(): ViewAction
    {
        return self::style(
            ViewAction::make()->icon(Heroicon::OutlinedEye),
            'primary',
        );
    }

    public static function delete(): DeleteAction
    {
        return self::style(
            DeleteAction::make()->icon(Heroicon::OutlinedTrash),
            'danger',
        );
    }

    public static function forceDelete(): ForceDeleteAction
    {
        return self::style(
            ForceDeleteAction::make()->icon(Heroicon::OutlinedTrash),
            'danger',
        );
    }

    public static function restore(): RestoreAction
    {
        return self::style(
            RestoreAction::make()->icon(Heroicon::OutlinedArrowUturnLeft),
            'success',
        );
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    private static function style(Action $action, string $color): Action
    {
        return $action
            ->iconButton()
            ->size('lg')
            ->color($color);
    }
}
