<x-filament-panels::page>
    <x-filament::section heading="Ordner in Nextcloud" description="Je Veranstaltung mit „Zuschuss beantragen“ legt Kluft hier einen Ordner „JJJJ-MM-TT Titel“ an, mit Teilnahmeliste, Förderschätzung, den Anträgen der gewählten Zuschussgeber und einem leeren Unterordner „Belege“ für Rechnungen. Abgleich unter „Teilnehmende → Zuschuss → In Nextcloud ablegen“ und nachts automatisch (14 Tage vor Beginn bis 90 Tage nach Ende). Kluft überschreibt nur seine eigenen Dateien und löscht in Nextcloud nichts.">
        <form wire:submit="speichern" style="display: grid; gap: 1rem; justify-items: start;">
            {{ $this->form }}
            <x-filament::button type="submit">Speichern</x-filament::button>
        </form>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Die Unterlagen enthalten Namen, Anschriften und Geburtsdaten. Bitte einen Ordner wählen, den nur Vorstand und Kasse sehen, und dort nach der Aufbewahrungsfrist (5 Jahre) aufräumen.</p>
    </x-filament::section>
</x-filament-panels::page>
