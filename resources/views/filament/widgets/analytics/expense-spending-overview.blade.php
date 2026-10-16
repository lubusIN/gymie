@php
/** @var string $heading */
/** @var string $totalExpense */
/** @var \Illuminate\Support\Collection<int, array{label: string, total: float, color: string, flex: float}> $segments */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :heading="$heading" class="fi-wi-chart h-full">
        <div class="grid gap-4">
            <div class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                {{ $totalExpense }}
            </div>

            <div class="grid gap-8">
                <div class="h-8 w-full overflow-hidden rounded-md bg-gray-100 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                    @if ($segments->isEmpty())
                        <div class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('app.widgets.no_expenses_in_period') }}
                        </div>
                    @else
                        <div class="flex h-full w-full gap-[1px]">
                            @foreach ($segments as $segment)
                                <div
                                    class="h-full"
                                    style="flex: {{ $segment['flex'] }}; background-color: {{ $segment['color'] }};"
                                    title="{{ $segment['label'] }}"></div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($segments->isNotEmpty())
                    <div class="grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-3">
                        @foreach ($segments as $segment)
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-1 h-3 w-3 shrink-0 rounded-full"
                                    style="background-color: {{ $segment['color'] }};"></span>

                                <div class="grid gap-1">
                                    <div class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ $segment['label'] }}
                                    </div>
                                    <div class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ \App\Helpers\Helpers::formatCurrency($segment['total']) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
