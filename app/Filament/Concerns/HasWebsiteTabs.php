<?php

namespace App\Filament\Concerns;

use App\Support\Websites;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reiter „Alle | dpsg-trier.de | bolivienpartnerschaft.de | …“ über
 * Seiten- und Beitragslisten, damit Inhalte einer Domain getrennt
 * auffindbar sind.
 */
trait HasWebsiteTabs
{
    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['alle' => Tab::make('Alle')];
        foreach (Websites::all() as $key => $domain) {
            $tabs[$key] = Tab::make($domain)
                ->icon('heroicon-m-globe-alt')
                ->badge(fn () => Websites::scope(static::getResource()::getEloquentQuery(), $key)->count())
                ->modifyQueryUsing(fn (Builder $query) => Websites::scope($query, $key));
        }

        return $tabs;
    }
}
