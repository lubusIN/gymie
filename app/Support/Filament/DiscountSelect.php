<?php

namespace App\Support\Filament;

use App\Contracts\SettingsRepository;
use App\Helpers\Helpers;
use App\Support\Data;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;

final class DiscountSelect extends Select
{
    /**
     * Configure the shared discount selector and its inline create action.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('app.fields.discount'))
            ->options(fn (): array => Helpers::getDiscounts())
            ->live()
            ->placeholder(__('app.placeholders.select_discount'))
            ->createOptionForm([
                TextInput::make('percentage')
                    ->label(__('app.fields.discount_percent'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required(),
            ])
            ->createOptionAction(
                fn (Action $action): Action => $action->modalWidth(Width::Small),
            )
            ->createOptionUsing(fn (array $data): string => $this->storePercentage(
                Data::int($data['percentage'] ?? null),
            ));
    }

    private function storePercentage(int $percentage): string
    {
        $repository = app(SettingsRepository::class);
        $settings = $repository->get();
        $discounts = collect(Data::array(data_get($settings, 'charges.discounts')))
            ->map(fn (mixed $discount): int => Data::int($discount))
            ->filter(fn (int $discount): bool => $discount >= 1 && $discount <= 100)
            ->push($percentage)
            ->unique()
            ->sort()
            ->values()
            ->all();

        data_set($settings, 'charges.discounts', $discounts);
        $repository->put($settings);

        return (string) $percentage;
    }
}
