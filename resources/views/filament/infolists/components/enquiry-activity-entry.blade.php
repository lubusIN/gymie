<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    @php($timeline = $getTimeline())

    <div {{ $getExtraAttributeBag() }}>
        @if ($timeline === [])
            <div class="flex min-h-48 flex-col items-center justify-center gap-3 text-center">
                <span class="flex size-10 items-center justify-center rounded-full bg-gray-50 text-gray-400 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-500 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-clock" class="size-5" />
                </span>

                <div class="flex flex-col gap-1">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('app.activity.empty_title') }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('app.activity.empty_description') }}
                    </p>
                </div>
            </div>
        @else
            <ol class="flex flex-col gap-6">
                @foreach ($timeline as $item)
                    <li class="grid grid-cols-[2rem_minmax(0,1fr)] gap-3">
                        <div class="relative flex justify-center">
                            @unless ($loop->last)
                                <span class="absolute inset-y-8 -bottom-6 w-px bg-gray-200 dark:bg-white/10" aria-hidden="true"></span>
                            @endunless

                            <span
                                @class([
                                    'relative z-10 flex size-8 items-center justify-center rounded-full ring-1',
                                    'bg-primary-50 text-primary-600 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20' => $item['color'] === 'primary',
                                    'bg-success-50 text-success-600 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20' => $item['color'] === 'success',
                                    'bg-warning-50 text-warning-600 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/20' => $item['color'] === 'warning',
                                    'bg-danger-50 text-danger-600 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/20' => $item['color'] === 'danger',
                                    'bg-gray-50 text-gray-500 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10' => $item['color'] === 'gray',
                                ])
                            >
                                <x-filament::icon :icon="$item['icon']" class="size-4" />
                            </span>
                        </div>

                        <div class="min-w-0 pt-1">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <p class="text-sm text-gray-700 dark:text-gray-200">
                                    {{ $item['description'] }}
                                </p>

                                <time
                                    class="shrink-0 text-xs text-gray-500 dark:text-gray-400"
                                    title="{{ $item['timestamp'] }}"
                                >
                                    {{ $item['relative_time'] }}
                                </time>
                            </div>

                            @if ($item['body'])
                                <div class="mt-3 whitespace-pre-line rounded-lg bg-gray-50 px-4 py-3 text-sm leading-6 text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">{{ $item['body'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-dynamic-component>
