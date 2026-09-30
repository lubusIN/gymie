<?php

namespace App\Filament\Resources\Enquiries\Schemas;

use App\Filament\Resources\FollowUps\Schemas\FollowUpForm;
use App\Models\Enquiry;
use App\Models\Service;
use App\Models\User;
use App\Support\Filament\DuplicateContactLink;
use App\Support\Filament\LocationSection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;

class EnquiryForm
{
    /**
     * Configure the enquiry form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.ui.details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('app.placeholders.example_full_name')),
                        TextInput::make('email')
                            ->label(__('app.fields.email'))
                            ->email()
                            ->required()
                            ->live(onBlur: true)
                            ->placeholder(__('app.placeholders.example_email'))
                            ->belowContent(fn (Get $get, ?Enquiry $record): array => DuplicateContactLink::make(
                                field: 'email',
                                value: $get->string('email', isNullable: true),
                                record: $record,
                            )),
                        TextInput::make('contact')
                            ->label(__('app.fields.contact'))
                            ->tel()
                            ->required()
                            ->live(onBlur: true)
                            ->belowContent(fn (Get $get, ?Enquiry $record): array => DuplicateContactLink::make(
                                field: 'contact',
                                value: $get->string('contact', isNullable: true),
                                record: $record,
                            )),
                        DatePicker::make('dob')
                            ->required()
                            ->label(__('app.fields.dob')),
                        DatePicker::make('date')
                            ->label(__('app.fields.date'))
                            ->default(now()),
                        Radio::make('gender')
                            ->options([
                                'male' => __('app.options.gender.male'),
                                'female' => __('app.options.gender.female'),
                                'other' => __('app.options.gender.other'),
                            ])
                            ->label(__('app.fields.gender'))
                            ->default('male')
                            ->inline()
                            ->inlineLabel(false)
                            ->required(),
                        Select::make('user_id')
                            ->label(__('app.fields.lead_owner'))
                            ->placeholder(__('app.placeholders.select_assignee'))
                            ->relationship('user', 'name')
                            ->required()
                            ->getOptionLabelFromRecordUsing(function (User $record): string {
                                $name = html_entity_decode($record->name, ENT_QUOTES, 'UTF-8');
                                $url = ! empty($record->photo) ? e($record->photo) : "https://ui-avatars.com/api/?background=000&color=fff&name={$name}";

                                return Blade::render(
                                    '<div class="flex items-center gap-2 h-9">
                                    <x-filament::avatar src="{{ $url }}" alt="{{ $name }}" size="sm" />
                                    <span class="ml-2">{{ $name }}</span>
                                 </div>',
                                    compact('url', 'name')
                                );
                            })
                            ->allowHtml(),
                        DatePicker::make('start_by')
                            ->label(__('app.fields.start_by'))
                            ->minDate(now())
                            ->placeholder(now()->format('d-m-Y')),
                    ])->columns(3)->columnSpanFull(),
                LocationSection::make(
                    pincodeRequired: true,
                    regionSearchable: true,
                ),
                Section::make(__('app.ui.preferences'))
                    ->schema([
                        Select::make('interested_in')
                            ->label(__('app.fields.interested_in'))
                            ->multiple()
                            ->placeholder(__('app.placeholders.select_services'))
                            ->options(fn () => Service::pluck('name', 'name')->toArray()),
                        Select::make('source')
                            ->options([
                                'promotions' => __('app.options.source.promotions'),
                                'word_of_mouth' => __('app.options.source.word_of_mouth'),
                                'others' => __('app.options.source.others'),
                            ])->default('promotions')
                            ->label(__('app.fields.source'))
                            ->selectablePlaceholder(false),
                        Select::make('goal')
                            ->options([
                                'fitness' => __('app.options.goal.fitness'),
                                'body_building' => __('app.options.goal.body_building'),
                                'fatloss' => __('app.options.goal.fatloss'),
                                'weightgain' => __('app.options.goal.weightgain'),
                                'others' => __('app.options.goal.others'),
                            ])->default('fitness')
                            ->label(__('app.fields.goal'))
                            ->selectablePlaceholder(false),
                    ])->columns(3),
                Section::make(__('app.ui.follow_details'))
                    ->schema([
                        Repeater::make('followUps')
                            ->relationship('followUps')
                            ->itemLabel('')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'new_enquiry_follow_up'])
                            ->schema(FollowUpForm::scheduleFields())
                            ->columns(2)
                            ->maxItems(1)
                            ->deletable(false),
                    ])
                    ->hiddenOn('edit'),
            ]);
    }
}
