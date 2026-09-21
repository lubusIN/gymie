<?php

namespace App\Support\Filament;

use App\Contracts\SettingsRepository;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Support\Data;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ExpenseCategorySelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('app.fields.category'))
            ->options(fn (): array => Helpers::getExpenseCategoryOptions())
            ->searchable()
            ->createOptionForm([
                TextInput::make('name')
                    ->label(__('app.fields.category'))
                    ->required()
                    ->maxLength(100),
            ])
            ->createOptionAction(
                fn (Action $action): Action => $action
                    ->modalWidth(Width::Small)
                    ->authorize(fn (): bool => Gate::allows('create', Expense::class)),
            )
            ->createOptionUsing(fn (array $data): string => $this->storeCategory(
                Data::string($data['name'] ?? null),
            ));
    }

    private function storeCategory(string $category): string
    {
        Gate::authorize('create', Expense::class);

        $category = (string) Str::of($category)->squish();
        $key = Str::slug($category);

        if ($key === '') {
            throw ValidationException::withMessages([
                'name' => __('validation.required', ['attribute' => __('app.fields.category')]),
            ]);
        }

        $repository = app(SettingsRepository::class);
        $settings = $repository->get();
        $categories = collect(Helpers::getExpenseCategories())
            ->push($category)
            ->map(fn (mixed $value): string => (string) Str::of(Data::string($value))->squish())
            ->filter(fn (string $value): bool => Str::slug($value) !== '')
            ->unique(fn (string $value): string => Str::slug($value))
            ->values()
            ->all();

        data_set($settings, 'expenses.categories', $categories);
        $repository->put($settings);

        return $key;
    }
}
