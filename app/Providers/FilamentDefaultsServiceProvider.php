<?php

namespace App\Providers;

use App\Helpers\Helpers;
use App\Support\Data;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class FilamentDefaultsServiceProvider extends ServiceProvider
{
    /** Configure shared Filament assets and component defaults. */
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('gymie-styles', resource_path('css/custom.css')),
        ]);

        $this->configureActions();
        $this->configureFormsAndTables();
        $this->configureDeletionPrevention();
    }

    private function configureActions(): void
    {
        CreateAction::configureUsing(function (CreateAction $action): void {
            $action
                ->label(fn (): string => __('app.actions.new_label'))
                ->icon(Heroicon::OutlinedPlus);
        });

        EditAction::configureUsing(function (EditAction $action): void {
            $action->icon(Heroicon::OutlinedPencilSquare);
        });

        DeleteAction::configureUsing(function (DeleteAction $action): void {
            $action->icon(Heroicon::OutlinedTrash);
        });

        ForceDeleteAction::configureUsing(function (ForceDeleteAction $action): void {
            $action->icon(Heroicon::OutlinedTrash);
        });

        RestoreAction::configureUsing(function (RestoreAction $action): void {
            $action->icon(Heroicon::OutlinedArrowUturnLeft);
        });

        ViewAction::configureUsing(function (ViewAction $action): void {
            $action->icon(Heroicon::OutlinedEye);
        });
    }

    private function configureFormsAndTables(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->defaultSort('id', 'desc');
        });

        Select::configureUsing(function (Select $select): void {
            $select
                ->searchable()
                ->native(false);
        });

        DatePicker::configureUsing(function (DatePicker $datePicker): void {
            $datePicker
                ->native(false)
                ->placeholder(__('app.placeholders.date_example'))
                ->displayFormat('d/m/Y')
                ->prefixIcon('heroicon-o-calendar-days');
        });

        DateTimePicker::configureUsing(function (DateTimePicker $datePicker): void {
            $datePicker
                ->native(false)
                ->placeholder(__('app.placeholders.date_time_example'))
                ->displayFormat('d/m/Y H:i A')
                ->prefixIcon('heroicon-o-calendar-days');
        });

        TextColumn::configureUsing(function (TextColumn $column): void {
            $column->toggleable(isToggledHiddenByDefault: false);
        });

        TextInput::configureUsing(function (TextInput $component): void {
            $component->placeholder(function (TextInput $component): ?string {
                return $component->isTel()
                    ? Helpers::getPhonePlaceholder()
                    : null;
            });
        });
    }

    /** Prevent deletion of records that still have configured related data. */
    private function configureDeletionPrevention(): void
    {
        $map = $this->deletionPreventionMap();

        DeleteAction::configureUsing(function (DeleteAction $action) use ($map): DeleteAction {
            return $action
                ->requiresConfirmation(function (Action $action, mixed $record) use ($map): Action {
                    if (! is_object($record)) {
                        return $action;
                    }

                    $action->modalIcon('heroicon-o-trash');

                    foreach ($map[$record::class] ?? [] as $relation) {
                        if (! $record->{$relation}()->exists()) {
                            continue;
                        }

                        $action
                            ->modalIcon('heroicon-o-x-mark')
                            ->modalHeading(__('app.deletion_prevention.cannot_delete_title', [
                                'module' => class_basename($record),
                            ]))
                            ->modalDescription(__('app.deletion_prevention.cannot_delete_description', [
                                'count' => $record->{$relation}()->count(),
                                'relation' => Str::kebab($relation),
                            ]))
                            ->modalCancelAction(false)
                            ->modalSubmitAction(false);

                        break;
                    }

                    return $action;
                });
        }, isImportant: true);

        DeleteBulkAction::configureUsing(function (DeleteBulkAction $action) use ($map): DeleteBulkAction {
            return $action
                ->requiresConfirmation(function (DeleteBulkAction $action, Collection $records) use ($map): DeleteBulkAction {
                    foreach ($records as $record) {
                        if (! is_object($record)) {
                            continue;
                        }

                        $action->modalIcon('heroicon-o-trash');

                        foreach ($map[$record::class] ?? [] as $relation) {
                            if (! $record->{$relation}()->exists()) {
                                continue;
                            }

                            $moduleName = Str::pluralStudly(class_basename($record));
                            $action
                                ->modalIcon('heroicon-o-x-mark')
                                ->modalHeading(__('app.deletion_prevention.cannot_delete_title', [
                                    'module' => $moduleName,
                                ]))
                                ->modalDescription(__('app.deletion_prevention.cannot_delete_bulk_description', [
                                    'module' => $moduleName,
                                    'count' => $record->{$relation}()->count(),
                                    'relation' => Str::kebab($relation),
                                ]))
                                ->modalCancelAction(false)
                                ->modalSubmitAction(false);

                            break 2;
                        }
                    }

                    return $action;
                });
        }, isImportant: true);
    }

    /** @return array<class-string, list<string>> */
    private function deletionPreventionMap(): array
    {
        $map = [];

        foreach ((array) config('prevent-deletion', []) as $class => $relations) {
            if (! is_string($class) || ! is_array($relations)) {
                continue;
            }

            $map[$class] = array_values(array_filter(array_map(
                static fn (mixed $relation): string => Data::string($relation),
                $relations,
            )));
        }

        return $map;
    }
}
