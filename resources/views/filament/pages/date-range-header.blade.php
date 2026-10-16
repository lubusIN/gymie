@php
/** @var \Filament\Pages\Page $page */
/** @var \Filament\Schemas\Schema $dateRangeForm */

$heading = $page->getHeading();
$breadcrumbs = filament()->hasBreadcrumbs() ? $page->getBreadcrumbs() : [];
$subheading = $page->getSubheading();
$period = (string) ($page->filters['period'] ?? '7days');
$actions = $page->getCachedHeaderActions();
$actionsAlignment = $page->getHeaderActionsAlignment();

$beforeActions = \Filament\Support\Facades\FilamentView::renderHook(
\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
scopes: $page->getRenderHookScopes(),
);
$afterActions = \Filament\Support\Facades\FilamentView::renderHook(
\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER,
scopes: $page->getRenderHookScopes(),
);
@endphp

<header @class(['fi-header', 'fi-header-has-breadcrumbs'=> $breadcrumbs])>
    <div>
        @if ($breadcrumbs)
        <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        @if (filled($heading))
        <h1 class="fi-header-heading">
            {{ $heading }}
        </h1>
        @endif

        @if (filled($subheading))
        <p class="fi-header-subheading">
            {{ $subheading }}
        </p>
        @endif
    </div>

    <div class="fi-header-actions-ctn flex-wrap" wire:init="ensureDefaultFilters">
        {{ $beforeActions }}

        <div class="flex flex-wrap items-center justify-end gap-2">
            {{ $dateRangeForm }}

            @if ($period === 'custom')
            <x-filament::button color="gray" outlined size="sm" wire:click="applyCustomRangeFromFilters">
                {{ __('app.dashboard.actions.apply') }}
            </x-filament::button>
            @endif
        </div>

        @if ($actions)
        <x-filament::actions
            :actions="$actions"
            :alignment="$actionsAlignment" />
        @endif

        {{ $afterActions }}
    </div>
</header>