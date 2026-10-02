<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Models\CalendarEvent;
use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Aus „Kalendertermine“ (?kalendertermin=ID): Formular mit Titel, Zeit,
     * Ort und Stufe des Nextcloud-Termins vorausfüllen. Gespeichert wird
     * erst beim Klick auf „Erstellen“.
     */
    protected function afterFill(): void
    {
        $termin = CalendarEvent::query()->with('calendar')->find(request()->integer('kalendertermin'));
        if (! $termin) {
            return;
        }

        $ends = $termin->lastDay();
        $stage = $termin->calendar?->stage;

        $this->data = [
            ...$this->data,
            'title' => $termin->title,
            'slug' => $this->uniqueSlug($termin),
            'location' => $termin->location,
            'starts_at' => $termin->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $termin->all_day ? $ends?->copy()->setTime(23, 59)->format('Y-m-d H:i:s') : $ends?->format('Y-m-d H:i:s'),
            'teaser' => filled($termin->description) ? Str::limit(Str::squish($termin->description), 490) : null,
            'stage' => $stage && array_key_exists($stage, EventForm::stageOptions()) ? $stage : ($this->data['stage'] ?? null),
            'calendar_uid' => $termin->uid,
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Filament::auth()->id();

        return $data;
    }

    private function uniqueSlug(CalendarEvent $termin): string
    {
        $base = Str::slug($termin->title.' '.$termin->starts_at->format('Y'), '-', 'de') ?: 'veranstaltung';
        $slug = $base;
        for ($i = 2; Event::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
