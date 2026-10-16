<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;

abstract class ListRecordsWithTableTabs extends ListRecords
{
    /** Select a table tab while rejecting values that are not configured for the page. */
    public function selectTableTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->getCachedTabs())) {
            return;
        }

        if ($url = $this->getTableTabUrl($tab)) {
            $this->redirect($url, navigate: true);

            return;
        }

        $this->activeTab = $tab;
        $this->updatedActiveTab();
    }

    /** Resolve a navigation URL when a table tab switches between list pages. */
    protected function getTableTabUrl(string $tab): ?string
    {
        return null;
    }

    /** Keep resource tabs available for filtering while rendering them in the table toolbar. */
    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()->hidden();
    }
}
