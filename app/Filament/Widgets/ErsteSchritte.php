<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MeinEintrag;
use App\Filament\Pages\MeineRollen;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Dashboard: „Erste Schritte“ passend zu den Rollen der angemeldeten Person.
 * Erklärt, wie ein Beitrag online kommt und was bei Fotos gilt, mit
 * direkten Links; wer freigeben darf, sieht die wartenden Beiträge.
 */
class ErsteSchritte extends Widget
{
    protected string $view = 'filament.widgets.erste-schritte';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->roleAssignments->isNotEmpty();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $freigeben = app(PostPolicy::class)->publish($user);

        $bereich = array_filter([
            PageResource::canCreate() || $user->darf('vorstandsseiten') ? ['Seiten pflegen', PageResource::getUrl('index'), match (true) {
                $user->darfUeberall('seiten') => 'Alle Seiten der Website bearbeiten, anlegen und veröffentlichen.',
                PageResource::canCreate() => 'Seiten deiner Stufe bzw. deines Fachbereichs bearbeiten und veröffentlichen.',
                default => 'Die Vorstandsseite und ihre Unterseiten bearbeitest du selbst.',
            }] : null,
            EventResource::canCreate() ? ['Veranstaltung anlegen', EventResource::getUrl('create'), 'Mit Online-Anmeldung, Teilnahmebeitrag und Teilnehmerliste.'] : null,
            UserResource::canViewAny() && ! $user->darfUeberall('autoren') ? ['Autor:innen freischalten', UserResource::getUrl('index'), 'Wer für deinen Bereich schreiben soll, bekommt von dir die Rolle Autor:in.'] : null,
        ]);

        return [
            'freigeben' => $freigeben,
            'wartend' => $freigeben ? PostResource::getEloquentQuery()->where('status', 'review')->count() : 0,
            'freigabeUrl' => PostResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'review']]]),
            'neuerBeitrag' => PostResource::getUrl('create'),
            'mediathek' => MediaResource::getUrl('index'),
            'bereich' => $bereich,
            'rollen' => MeineRollen::canAccess() ? MeineRollen::getUrl() : null,
            'eintrag' => MeinEintrag::canAccess() ? MeinEintrag::getUrl() : null,
            'eigeneEntwuerfe' => Post::query()->where('author_id', $user->id)->where('status', 'draft')->count(),
            'eigeneUrl' => PostResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'draft']]]),
        ];
    }
}
