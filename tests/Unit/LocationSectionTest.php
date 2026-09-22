<?php

use App\Support\Filament\LocationSection;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

/**
 * @return array{Section, Closure(string): Field}
 */
function locationSectionFields(Section $section): array
{
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $schema = Schema::make($livewire)->components([$section]);
    $schema->getComponents();

    $field = function (string $name) use ($schema): Field {
        $component = $schema->getComponent(
            fn (Component $component): bool => $component instanceof Field && $component->getName() === $name,
        );

        expect($component)->toBeInstanceOf(Field::class);

        return $component;
    };

    return [$section, $field];
}

it('builds the shared location fields', function (): void {
    [$section, $field] = locationSectionFields(LocationSection::make());

    expect($section->getHeading())->toBe(__('app.ui.location'))
        ->and($field('address'))->toBeInstanceOf(Textarea::class)
        ->and($field('country'))->toBeInstanceOf(Select::class)
        ->and($field('state'))->toBeInstanceOf(Select::class)
        ->and($field('city'))->toBeInstanceOf(Select::class)
        ->and($field('pincode'))->toBeInstanceOf(TextInput::class)
        ->and($field('address')->isRequired())->toBeTrue()
        ->and($field('country')->isRequired())->toBeTrue();
});

it('preserves resource-specific location settings', function (): void {
    [$section, $field] = locationSectionFields(LocationSection::make(
        pincodeRequired: true,
        sectionColumns: 2,
        fieldGroupColumns: 2,
        addressRows: 5,
        countrySearchable: true,
        countryPreloaded: true,
        regionSearchable: true,
    ));

    $country = $field('country');
    $state = $field('state');
    $city = $field('city');

    expect($section->getColumns('lg'))->toBe(2)
        ->and($field('address')->getRows())->toBe(5)
        ->and($field('pincode')->isRequired())->toBeTrue()
        ->and($country)->toBeInstanceOf(Select::class)
        ->and($country->isSearchable())->toBeTrue()
        ->and($country->isPreloaded())->toBeTrue()
        ->and($state)->toBeInstanceOf(Select::class)
        ->and($state->isSearchable())->toBeTrue()
        ->and($city)->toBeInstanceOf(Select::class)
        ->and($city->isSearchable())->toBeTrue();
});
