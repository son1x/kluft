<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\WpImportRun;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Migrationsbericht der WordPress-Übernahme (Pflichtenheft F-MIG-08):
 * letzte Importläufe und Nacharbeitsliste der importierten Beiträge.
 */
class WordPressImport extends Page
{
    protected string $view = 'filament.pages.wordpress-import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'WordPress-Import';

    protected static ?string $title = 'WordPress-Import';

    protected static ?string $slug = 'wordpress-import';

    protected static ?int $navigationSort = 91;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isVerwaltung();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $imported = Post::query()->whereNotNull('wp_id')->orderByDesc('published_at')->get();

        return [
            'runs' => WpImportRun::query()->latest('id')->limit(10)->get(),
            'open' => $imported->where('import_reviewed', false),
            'reviewedCount' => $imported->where('import_reviewed', true)->count(),
            'publishedCount' => $imported->where('status', 'published')->count(),
            'editUrl' => fn (Post $post) => PostResource::getUrl('edit', ['record' => $post]),
        ];
    }
}
