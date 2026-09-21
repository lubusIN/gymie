<?php

use App\Contracts\SettingsRepository;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Helpers\Helpers;
use App\Models\Plan;
use App\Models\User;
use App\Support\Filament\DiscountSelect;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('uses a compact inline discount form', function (): void {
    app()->setLocale('en');

    $select = DiscountSelect::make('discount');
    $schema = Schema::make()->components([$select]);
    $schema->getComponents();
    $action = $select->getCreateOptionAction();
    $form = $select->getCreateOptionActionForm($schema);
    $percentageField = is_array($form) ? $form[0] ?? null : null;

    expect($action?->getModalWidth())->toBe(Width::Small)
        ->and($percentageField)->toBeInstanceOf(TextInput::class)
        ->and($percentageField?->getLabel())->toBe('Discount percent');
});

it('keeps one invoice item when the selected plan changes repeatedly', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');

    $firstPlan = Plan::factory()->create(['amount' => 100]);
    $secondPlan = Plan::factory()->create(['amount' => 250]);

    $subscriptionKey = (string) Str::uuid();
    $invoiceKey = (string) Str::uuid();

    $component = Livewire::test(CreateMember::class)
        ->set('data.subscriptions', [
            $subscriptionKey => [
                'start_date' => now()->toDateString(),
                'invoices' => [
                    $invoiceKey => [
                        'discount_amount' => 0,
                        'paid_amount' => 0,
                        'payment_method' => 'cash',
                    ],
                ],
            ],
        ]);
    $subscriptions = $component->get('data.subscriptions');

    expect($subscriptions)->toHaveCount(1);

    $invoiceItems = $subscriptions[$subscriptionKey]['invoices'];

    expect($invoiceItems)->toHaveCount(1);

    foreach ([
        [$firstPlan, 100.0],
        [$secondPlan, 250.0],
        [$firstPlan, 100.0],
    ] as [$plan, $expectedFee]) {
        $component->set("data.subscriptions.{$subscriptionKey}.plan_id", $plan->getKey());

        $invoiceItems = $component->get("data.subscriptions.{$subscriptionKey}.invoices");

        expect($invoiceItems)
            ->toHaveCount(1)
            ->toHaveKey($invoiceKey)
            ->and(data_get($invoiceItems, "{$invoiceKey}.subscription_fee"))->toEqual($expectedFee);
    }
});

it('shows plan duration in the member plan selector', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
    app()->setLocale('en');

    $plan = Plan::factory()->create([
        'code' => 'MONTHLY',
        'name' => 'Monthly',
        'amount' => 100,
        'days' => 30,
    ]);

    $component = Livewire::test(CreateMember::class);
    $planSelect = $component->instance()->form->getComponent(
        fn (Component $field): bool => $field instanceof Select && $field->getName() === 'plan_id',
        withHidden: true,
    );

    expect($planSelect)
        ->toBeInstanceOf(Select::class)
        ->and($planSelect->getOptionLabelFromRecord($plan))
        ->toBe(sprintf('MONTHLY – Monthly (%s100 | 30 days)', Helpers::getCurrencySymbol()));
});

it('creates and selects a discount without leaving the member form', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');

    $repository = new class implements SettingsRepository
    {
        /** @var array<string, mixed> */
        public array $settings = [
            'charges' => [
                'discounts' => [10],
            ],
        ];

        public function get(): array
        {
            return $this->settings;
        }

        public function put(array $settings): void
        {
            $this->settings = $settings;
        }
    };

    app()->instance(SettingsRepository::class, $repository);

    $subscriptionKey = (string) Str::uuid();
    $invoiceKey = (string) Str::uuid();
    $discountPath = "subscriptions.{$subscriptionKey}.invoices.{$invoiceKey}.discount";

    $component = Livewire::test(CreateMember::class)
        ->set('data.subscriptions', [
            $subscriptionKey => [
                'start_date' => now()->toDateString(),
                'invoices' => [
                    $invoiceKey => [
                        'discount_amount' => 0,
                        'paid_amount' => 0,
                        'payment_method' => 'cash',
                    ],
                ],
            ],
        ])
        ->callFormComponentAction($discountPath, 'createOption', [
            'percentage' => 101,
        ])
        ->assertHasFormComponentActionErrors([
            'percentage' => 'max',
        ])
        ->unmountFormComponentAction();

    expect(data_get($repository->settings, 'charges.discounts'))->toBe([10]);

    $component
        ->callFormComponentAction($discountPath, 'createOption', [
            'percentage' => 15,
        ]);

    expect($component->get("data.{$discountPath}"))->toBe('15')
        ->and(data_get($repository->settings, 'charges.discounts'))->toBe([10, 15]);

    $component->callFormComponentAction($discountPath, 'createOption', [
        'percentage' => 15,
    ]);

    expect(data_get($repository->settings, 'charges.discounts'))->toBe([10, 15]);
});
