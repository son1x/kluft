<?php

namespace App\Filament\Resources\Posts\Actions;

use App\Jobs\PublishToInstagram;
use App\Models\Post;
use App\Services\Instagram\InstagramClient;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * „Auf Instagram teilen“ im Blog-Editor (F-IG-01/05/06).
 */
class ShareToInstagramAction
{
    public const MAX_CAPTION = 2200;

    public static function make(): Action
    {
        return Action::make('instagram')
            ->label(fn (Post $record) => $record->socialPosts()->where('status', 'published')->exists() ? 'Erneut auf Instagram teilen' : 'Auf Instagram teilen')
            ->icon('heroicon-o-camera')
            ->color('gray')
            ->visible(fn (Post $record) => (bool) Filament::auth()->user()?->can('shareToInstagram', $record))
            ->disabled(fn (Post $record) => self::blocker($record) !== null)
            ->tooltip(fn (Post $record) => self::blocker($record))
            ->modalHeading('Auf Instagram teilen')
            ->modalDescription(fn (Post $record) => self::history($record))
            ->modalSubmitActionLabel('Jetzt veröffentlichen')
            ->fillForm(fn (Post $record) => ['caption' => self::defaultCaption($record)])
            ->schema([
                Textarea::make('caption')
                    ->label('Bildunterschrift')
                    ->required()
                    ->rows(8)
                    ->maxLength(self::MAX_CAPTION)
                    ->helperText('Max. 2.200 Zeichen. Links sind auf Instagram nicht anklickbar – am besten auf die Website bzw. den Link in der Bio verweisen.'),
                Checkbox::make('rights')
                    ->label('Bildrechte und Einwilligungen der abgebildeten Personen gelten auch für Instagram.')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Bitte bestätige die Bildrechte.']),
            ])
            ->action(function (Post $record, array $data) {
                $user = Filament::auth()->user();
                abort_unless($user?->can('shareToInstagram', $record) && self::blocker($record) === null, 403);

                $social = $record->socialPosts()->create([
                    'platform' => 'instagram',
                    'caption' => $data['caption'],
                    'status' => 'pending',
                    'requested_by' => $user->id,
                ]);
                AuditLogger::log('instagram.publish', $user, Post::class, $record->id, request());

                PublishToInstagram::dispatch($social);

                $social->refresh();
                match ($social->status) {
                    'published' => Notification::make()->title('Auf Instagram veröffentlicht')->success()
                        ->body($social->permalink)->send(),
                    'failed' => Notification::make()->title('Instagram-Veröffentlichung fehlgeschlagen')->danger()
                        ->body($social->error)->persistent()->send(),
                    default => Notification::make()->title('Wird im Hintergrund auf Instagram veröffentlicht')->info()->send(),
                };
            });
    }

    /**
     * Grund, warum gerade nicht geteilt werden kann (für Tooltip).
     */
    public static function blocker(Post $post): ?string
    {
        return match (true) {
            ! app(InstagramClient::class)->isConfigured() => 'Instagram ist noch nicht verbunden (Backend → Integrationen).',
            $post->cover_media_id === null => 'Für Instagram braucht der Beitrag ein Titelbild.',
            $post->socialPosts()->where('status', 'pending')->where('created_at', '>', now()->subMinutes(10))->exists() => 'Eine Veröffentlichung läuft gerade.',
            default => null,
        };
    }

    public static function defaultCaption(Post $post): string
    {
        $caption = trim($post->title."\n\n".($post->teaser ?? ''))
            ."\n\nMehr dazu auf dpsg-trier.de (Link in der Bio)\n\n#dpsg #pfadfinden #dpsgtrier";

        return Str::limit($caption, self::MAX_CAPTION, '');
    }

    private static function history(Post $post): ?string
    {
        $last = $post->socialPosts()->first();

        return match ($last?->status) {
            'published' => 'Zuletzt veröffentlicht am '.$last->posted_at?->format('d.m.Y H:i').'.',
            'failed' => 'Letzter Versuch fehlgeschlagen: '.$last->error,
            default => null,
        };
    }
}
