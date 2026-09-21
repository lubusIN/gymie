<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\Status;
use App\Filament\Resources\Services\Schemas\ServiceForm;
use App\Helpers\Helpers;
use App\Models\Service;
use App\Support\Data;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class PlanForm
{
    /**
     * Configure the plan form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Fieldset::make()
                    ->label(function (Get $get): HtmlString {
                        $rawStatus = $get('status');
                        $status = Status::tryFrom(Data::string($rawStatus, Status::Active->value)) ?? Status::Active;
                        $html = Blade::render(
                            '<x-filament::badge class="inline-flex ml-2" :color="$color">
                                {{ $label }}
                            </x-filament::badge>',
                            [
                                'color' => $status->getColor(),
                                'label' => $status->getLabel(),
                            ]
                        );

                        return new HtmlString($html);
                    })
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.fields.name'))
                            ->placeholder(__('app.placeholders.plan_name'))
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->placeholder(__('app.placeholders.plan_code'))
                            ->label(__('app.fields.code'))
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Select::make('service_id')
                            ->label(__('app.fields.service'))
                            ->relationship(name: 'service', titleAttribute: 'name')
                            ->placeholder(__('app.placeholders.select_service'))
                            ->required()
                            ->createOptionModalHeading(__('app.actions.new', ['resource' => __('app.resources.services.singular')]))
                            ->createOptionForm(fn(Schema $schema): Schema => ServiceForm::configure($schema))
                            ->createOptionAction(fn(Action $action): Action => $action
                                ->authorize(fn(): bool => Gate::allows('create', Service::class)))
                            ->createOptionUsing(function (array $data): int {
                                Gate::authorize('create', Service::class);

                                return Data::int(Service::query()->create($data)->getKey());
                            })
                            ->createOptionAction(
                                fn(Action $action): Action => $action->modalWidth(Width::Small),
                            )
                            ->columnSpan(2),
                        TextInput::make('days')
                            ->required()
                            ->placeholder(__('app.placeholders.plan_days'))
                            ->numeric()
                            ->label(__('app.fields.days'))
                            ->columnSpan(1),
                        TextInput::make('amount')
                            ->placeholder(__('app.placeholders.plan_amount'))
                            ->numeric()
                            ->prefix(Helpers::getCurrencySymbol())
                            ->label(__('app.fields.amount'))
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('description')
                            ->placeholder(__('app.placeholders.plan_description'))
                            ->label(__('app.fields.description'))
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }
}
