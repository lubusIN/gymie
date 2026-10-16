@props(['page'])

<div
    class="min-w-0 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    data-table-tabs>
    <x-filament::tabs class="min-w-max border-0 ring-0 shadow-none">
        @foreach ($page->getCachedTabs() as $tabKey => $tab)
        @php
        $tabKey = (string) $tabKey;
        $badge = $tab->getBadge();
        @endphp

        <x-filament::tabs.item
            :active="(string) $page->activeTab === $tabKey"
            :badge="$badge"
            :badge-color="$tab->getBadgeColor($badge)"
            :badge-tooltip="$tab->getBadgeTooltip($badge)"
            :icon="$tab->getIcon()"
            :icon-position="$tab->getIconPosition()"
            wire:click="selectTableTab('{{ $tabKey }}')"
            wire:loading.attr="disabled"
            wire:key="{{ $page->getId() }}.table-tab.{{ $tabKey }}">
            {{ $tab->getLabel() }}
        </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
</div>