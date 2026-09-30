<div class="flex flex-col items-center gap-5 text-center">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20">
        <img
            src="{{ $qrCode }}"
            alt="{{ __('app.attendance.qr_alt') }}"
            class="size-64"
        >
    </div>

    <div class="flex flex-col gap-2">
        <p class="max-w-sm text-sm text-gray-600 dark:text-gray-300">
            {{ __('app.attendance.qr_description') }}
        </p>

        <p class="break-all rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
            {{ $url }}
        </p>
    </div>

    <div class="flex flex-wrap justify-center gap-3">
        <x-filament::button
            tag="a"
            :href="$url"
            target="_blank"
            rel="noreferrer"
            color="gray"
            icon="heroicon-o-arrow-top-right-on-square"
            outlined
        >
            {{ __('app.actions.open') }}
        </x-filament::button>

        <x-filament::button
            tag="a"
            :href="$qrCode"
            download="attendance-check-in.svg"
            color="gray"
            icon="heroicon-o-arrow-down-tray"
            outlined
        >
            {{ __('app.actions.download_qr') }}
        </x-filament::button>
    </div>
</div>
