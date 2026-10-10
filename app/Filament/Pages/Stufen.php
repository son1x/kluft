<?php

namespace App\Filament\Pages;

use App\Models\StageAge;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\StufenAlter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Altersangaben der Stufen (Admins): je Grenze bis zu StufenAlter::SPIELRAUM
 * Jahre um den DPSG-Standard, z. B. Wölflinge 7 bis 10 statt 6 bis 10.
 *
 * @property Schema $form
 */
class Stufen extends Page
{
    protected string $view = 'filament.pages.stufen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Stufen';

    protected static ?string $title = 'Altersangaben der Stufen';

    protected static ?string $slug = 'stufen';

    protected static ?int $navigationSort = 46;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(['alter' => collect((array) config('dpsg.stages'))
            ->map(fn (array $s) => ['from' => (int) $s['from'], 'to' => (int) $s['to']])->all()]);
    }

    public function form(Schema $schema): Schema
    {
        $geaendert = StageAge::query()->with('updatedBy')->get()->keyBy('stage');

        return $schema
            ->statePath('data')
            ->components(collect((array) config('dpsg.stages'))->map(function (array $s, string $stage) use ($geaendert) {
                $standard = StufenAlter::standard($stage);
                $eintrag = $geaendert->get($stage);
                $jahre = fn (string $grenze) => collect(StufenAlter::erlaubt($stage, $grenze))->mapWithKeys(fn (int $j) => [$j => $j.' Jahre'])->all();

                return Section::make($s['label'])
                    ->description('DPSG-Standard: '.StufenAlter::text($standard['from'], $standard['to']).'.'
                        .($eintrag ? ' Geändert am '.$eintrag->updated_at?->format('d.m.Y').($eintrag->updatedBy ? ' von '.$eintrag->updatedBy->name : '').'.' : ''))
                    ->columns(2)
                    ->components([
                        Select::make("alter.{$stage}.from")->label('ab')->options($jahre('from'))->required()->native(false)->selectablePlaceholder(false),
                        Select::make("alter.{$stage}.to")->label('bis')->options($jahre('to'))->required()->native(false)->selectablePlaceholder(false),
                    ]);
            })->values()->all());
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $user = Filament::auth()->user();
        $werte = (array) ($this->form->getState()['alter'] ?? []);

        $neu = [];
        foreach ((array) config('dpsg.stages') as $stage => $s) {
            $from = (int) ($werte[$stage]['from'] ?? $s['from']);
            $to = (int) ($werte[$stage]['to'] ?? $s['to']);
            if ($from === (int) $s['from'] && $to === (int) $s['to']) {
                continue;
            }
            if (! StufenAlter::gueltig($stage, $from, $to)) {
                Notification::make()->danger()->title('Nicht gespeichert')->body($s['label'].': „bis“ muss nach „ab“ liegen.')->send();

                return;
            }
            $neu[$stage] = [$from, $to];
        }

        $geaendert = [];
        foreach ($neu as $stage => [$from, $to]) {
            $s = (array) config("dpsg.stages.{$stage}");
            StufenAlter::setzen($stage, $from, $to, $user?->getKey());
            AuditLogger::log('stufe.alter:'.$stage, $user instanceof User ? $user : null, request: request());
            $geaendert[] = $s['label'].': '.StufenAlter::text($from, $to);
        }

        if ($geaendert === []) {
            Notification::make()->title('Nichts geändert.')->send();

            return;
        }
        Notification::make()->success()->title('Gespeichert')->body(implode(' · ', $geaendert).'.')->send();
        $this->mount();
    }
}
