<div x-data="{ copied: false }" class="space-y-3">
    <textarea x-ref="text" readonly rows="10" class="w-full rounded-lg border border-gray-300 bg-white p-3 font-mono text-sm dark:border-white/10 dark:bg-white/5">{{ $text }}</textarea>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button type="button" icon="heroicon-o-clipboard-document"
            x-on:click="navigator.clipboard.writeText($refs.text.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) }, () => $refs.text.select())">
            <span x-show="! copied">Text kopieren</span><span x-show="copied" x-cloak>Kopiert</span>
        </x-filament::button>
        @if ($channelUrl)
            <x-filament::link :href="$channelUrl" target="_blank" icon="heroicon-o-arrow-top-right-on-square">Kanal öffnen</x-filament::link>
        @endif
    </div>
</div>
