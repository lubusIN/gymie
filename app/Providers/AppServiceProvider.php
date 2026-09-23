<?php

namespace App\Providers;

use App\Contracts\SequenceRepository;
use App\Contracts\SettingsRepository;
use App\Contracts\TenantContext;
use App\Helpers\Helpers;
use App\Services\Api\Docs\AddIndexQueryParametersTransformer;
use App\Services\JsonSequenceRepository;
use App\Services\JsonSettingsRepository;
use App\Services\NullTenantContext;
use App\Support\Data;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class, JsonSettingsRepository::class);
        $this->app->singleton(SequenceRepository::class, JsonSequenceRepository::class);
        $this->app->singletonIf(TenantContext::class, NullTenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Request $request): void
    {
        if (str_starts_with(Data::string(config('app.url')), 'https://') || $request->isSecure()) {
            URL::forceScheme('https');
        }
        $this->configureApiRateLimiting();
        $this->configureScrambleApiDocs();

        FilamentAsset::register([
            Css::make('gymie-styles', __DIR__.'/../../resources/css/custom.css'),
        ]);

        /**
         * Configure form components globally to sync state and natively clear validation errors.
         */
        $resetError = function (LivewireComponent $livewire, SchemaComponent $component): void {
            $livewire->resetValidation($component->getStatePath());
        };

        TextInput::configureUsing(fn (TextInput $field) => $field->live(onBlur: true)->afterStateUpdated($resetError));
        Textarea::configureUsing(fn (Textarea $field) => $field->live(onBlur: true)->afterStateUpdated($resetError));
        Select::configureUsing(fn (Select $field) => $field->live()->afterStateUpdated($resetError));
        DatePicker::configureUsing(fn (DatePicker $field) => $field->live()->afterStateUpdated($resetError));
        DateTimePicker::configureUsing(fn (DateTimePicker $field) => $field->live()->afterStateUpdated($resetError));
        Radio::configureUsing(fn (Radio $field) => $field->live()->afterStateUpdated($resetError));
        Toggle::configureUsing(fn (Toggle $field) => $field->live()->afterStateUpdated($resetError));
        TagsInput::configureUsing(fn (TagsInput $field) => $field->live()->afterStateUpdated($resetError));

        /**
         * Configure the CreateAction globally to use a specific icon.
         */
        CreateAction::configureUsing(function (CreateAction $action) {
            $action->icon('heroicon-s-plus');
        });

        /**
         * Configure the EditAction and DeleteAction globally to use specific icons.
         */
        EditAction::configureUsing(function (EditAction $action) {
            $action->icon('heroicon-s-pencil-square');
        });

        /**
         * Configure the DeleteAction globally to use a specific icon.
         */
        DeleteAction::configureUsing(function (DeleteAction $action) {
            $action->icon('heroicon-s-trash');
        });

        /**
         * Configure the ViewAction globally to use a specific icon.
         */
        ViewAction::configureUsing(function (ViewAction $action) {
            $action->icon('heroicon-s-eye');
        });

        /**
         * Configure the Table component globally to set default sorting.
         */
        Table::configureUsing(function (Table $table) {
            $table->defaultSort('id', 'desc');
        });

        /**
         * Configure the Select component globally to be searchable, non-native, and preloaded.
         */
        Select::configureUsing(function (Select $select) {
            $select
                ->searchable()
                ->native(false)
                ->preload();
        });

        /**
         * Configure the DatePicker component globally to use a specific format and placeholder.
         */
        DatePicker::configureUsing(function (DatePicker $datePicker) {
            $datePicker
                ->native(false)
                ->placeholder(__('app.placeholders.date_example'))
                ->displayFormat('d/m/Y')
                ->prefixIcon('heroicon-o-calendar-days');
        });

        /**
         * Configure the DateTimePicker component globally to use a specific format and placeholder.
         */
        DateTimePicker::configureUsing(function (DateTimePicker $datePicker) {
            $datePicker
                ->native(false)
                ->placeholder(__('app.placeholders.date_time_example'))
                ->displayFormat('d/m/Y H:i A')
                ->prefixIcon('heroicon-o-calendar-days');
        });

        /**
         * Configure the TextColumn globally to be toggleable and hidden by default.
         */
        TextColumn::configureUsing(function (TextColumn $column) {
            $column->toggleable(isToggledHiddenByDefault: false);
        });

        /**
         * Configure the TextInput component globally to automatically set dynamic phone placeholder on telephone inputs.
         */
        TextInput::configureUsing(function (TextInput $component) {
            $component->placeholder(function (TextInput $component): ?string {
                if ($component->isTel()) {
                    return Helpers::getPhonePlaceholder();
                }

                return null;
            });
        });

        $this->configureDeletionPrevention();
    }

    /**
     * Configure Scramble (OpenAPI) generation for the v1 API.
     *
     * This is guarded so the app remains bootable even when Scramble isn't installed yet.
     */
    private function configureScrambleApiDocs(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        $config = Scramble::configure();

        $config->routes(static function (Route $route): bool {
            return str_starts_with($route->uri, 'api/v1/');
        });

        $config->withOperationTransformers([
            AddIndexQueryParametersTransformer::class,
        ]);

        if (class_exists(SecurityScheme::class)) {
            $config->withDocumentTransformers(static function (mixed $openApi): void {
                if (! is_object($openApi) || ! method_exists($openApi, 'secure')) {
                    return;
                }

                $openApi->secure(SecurityScheme::http('bearer'));
            });
        }
    }

    /**
     * Configure API rate limiters used by the `api` middleware group.
     *
     * Defining these explicitly prevents "throttle:api" from relying on
     * framework defaults that can vary between versions.
     */
    private function configureApiRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by(Data::string($key));
        });

        RateLimiter::for('api-login', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });
    }

    /**
     * Prevent deletion of records that still have related data.
     */
    protected function configureDeletionPrevention(): void
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

        DeleteAction::configureUsing(function (DeleteAction $action) use ($map): DeleteAction {
            return $action
                ->requiresConfirmation(function (Action $action, $record) use ($map) {
                    if (! is_object($record)) {
                        return $action;
                    }

                    $class = get_class($record);
                    $action->modalIcon('heroicon-o-trash');
                    if (isset($map[$class])) {
                        foreach ($map[$class] as $relation) {
                            if ($record->$relation()->exists()) {
                                $count = $record->$relation()->count();
                                $moduleName = class_basename($record);
                                $label = Str::kebab(Data::string($relation));
                                $action
                                    ->modalIcon('heroicon-o-x-mark')
                                    ->modalHeading(__('app.deletion_prevention.cannot_delete_title', ['module' => $moduleName]))
                                    ->modalDescription(__('app.deletion_prevention.cannot_delete_description', ['count' => $count, 'relation' => $label]))
                                    ->modalCancelAction(false)
                                    ->modalSubmitAction(false);
                                break;
                            }
                        }
                    }

                    return $action;
                });
        }, isImportant: true);

        DeleteBulkAction::configureUsing(function (DeleteBulkAction $action) use ($map): DeleteBulkAction {
            return $action
                ->requiresConfirmation(function (DeleteBulkAction $action, Collection $records) use ($map) {
                    foreach ($records as $record) {
                        if (! is_object($record)) {
                            continue;
                        }

                        $class = get_class($record);
                        $action->modalIcon('heroicon-o-trash');
                        if (isset($map[$class])) {
                            foreach ($map[$class] as $relation) {
                                if ($record->$relation()->exists()) {
                                    $count = $record->$relation()->count();
                                    $moduleName = Str::pluralStudly(class_basename($record));
                                    $label = Str::kebab(Data::string($relation));
                                    $action
                                        ->modalIcon('heroicon-o-x-mark')
                                        ->modalHeading(__('app.deletion_prevention.cannot_delete_title', ['module' => $moduleName]))
                                        ->modalDescription(__('app.deletion_prevention.cannot_delete_bulk_description', ['module' => $moduleName, 'count' => $count, 'relation' => $label]))
                                        ->modalCancelAction(false)
                                        ->modalSubmitAction(false);
                                    break 2;
                                }
                            }
                        }
                    }

                    return $action;
                });
        }, isImportant: true);
    }
}
