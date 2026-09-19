<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div class="flex justify-end items-center space-x-4">
            <x-filament::button type="submit" form="save">
                {{ __('app.settings.actions.save_settings') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
