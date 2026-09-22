<?php

use App\Filament\Resources\Enquiries\Schemas\EnquiryForm;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Users\Schemas\UserForm;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

it('uses the same gender radio field across person forms', function (string $formClass): void {
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $schema = $formClass::configure(Schema::make($livewire));
    $detailsSection = $schema->getComponents()[0] ?? null;

    expect($detailsSection)->toBeInstanceOf(Section::class);

    $gender = $detailsSection->getChildSchema()->getComponent(
        fn (Component $component): bool => $component instanceof Field && $component->getName() === 'gender',
    );

    expect($gender)->toBeInstanceOf(Radio::class)
        ->and($gender->getOptions())->toBe([
            'male' => __('app.options.gender.male'),
            'female' => __('app.options.gender.female'),
            'other' => __('app.options.gender.other'),
        ])
        ->and($gender->getLabel())->toBe(__('app.fields.gender'))
        ->and($gender->getDefaultState())->toBe('male')
        ->and($gender->isInline())->toBeTrue()
        ->and($gender->hasInlineLabel())->toBeFalse()
        ->and($gender->isRequired())->toBeTrue();
})->with([
    'enquiry' => EnquiryForm::class,
    'member' => MemberForm::class,
    'user' => UserForm::class,
]);
