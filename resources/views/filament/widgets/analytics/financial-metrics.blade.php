@php
/** @var string $heading */
/** @var array<int, array{label: string, value: string, icon: string, trend: array{label: string, icon: string, color: 'gray' |'success'|'danger'}, trendTitle: string}> $metrics */
    @endphp

    <x-filament-widgets::widget>
        <x-filament::section
            :heading="$heading"
            class="h-full"
            data-financial-metrics>
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($metrics as $metric)
                <div class="flex items-center justify-between gap-6 py-5 first:pt-0 last:pb-0">
                    <div class="flex min-w-0 items-center gap-3 text-gray-600 dark:text-gray-300">
                        <x-filament::icon
                            :icon="$metric['icon']"
                            class="size-5 shrink-0 text-gray-500" />

                        <span class="truncate text-sm font-medium sm:text-sm text-gray-500">
                            {{ $metric['label'] }}
                        </span>
                    </div>

                    <div class="flex shrink-0 items-baseline gap-3 flex-row">
                        <span class="text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                            {{ $metric['value'] }}
                        </span>

                        <span
                            @class([ 'inline-flex items-center gap-1 text-xs font-semibold' , 'text-gray-500 dark:text-gray-400'=> $metric['trend']['color'] === 'gray',
                            'text-success-600 dark:text-success-400' => $metric['trend']['color'] === 'success',
                            'text-danger-600 dark:text-danger-400' => $metric['trend']['color'] === 'danger',
                            ])
                            title="{{ $metric['trendTitle'] }}"
                            data-financial-trend
                            >
                            {{ $metric['trend']['label'] }}
                            <x-filament::icon :icon="$metric['trend']['icon']" class="size-3.5" />
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </x-filament::section>
    </x-filament-widgets::widget>
