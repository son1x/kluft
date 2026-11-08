<?php

namespace Tests\Feature\People;

use App\Filament\Pages\MeinEintrag;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\User;
use App\Support\KontoVorschlag;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Mein Konto → Mein Eintrag auf der Website: Die Verwaltung verknüpft ein
 * Konto mit einem Personen-Eintrag; die Person pflegt Text, Foto und
 * Einwilligung selbst, alles andere bleibt bei der Verwaltung.
 */
class MeinEintragTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake('public');
    }

    private function user(string $email, string $role = 'autor', string $scopeType = 'own'): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType]);

        return $user->fresh();
    }

    private function person(array $attrs = []): Person
    {
        return Person::query()->create(['gremium' => array_key_first((array) config('dpsg.gremien')), 'name' => 'Lea Leitung', 'role' => 'Referentin',
            'bio' => 'Alter Text', 'consent' => false, 'active' => true, 'sort' => 1, ...$attrs]);
    }

    #[TestDox('Die Verwaltung ordnet einem Personen-Eintrag ein Konto zu')]
    public function test_verwaltung_verknuepft(): void
    {
        $lea = $this->user('lea@dpsg-trier.de');
        $person = $this->person();
        $this->actingAs($this->user('buero@dpsg-trier.de', 'verwaltung', 'global'));

        Livewire::test(EditPerson::class, ['record' => $person->getRouteKey()])
            ->fillForm(['user_id' => $lea->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($lea->id, $person->fresh()->user_id);
        $this->assertSame([$person->id], $lea->personen()->pluck('id')->all());
    }

    #[TestDox('Konto-Vorschlag: gleiche E-Mail (auch UPN, Groß/klein egal), sonst gleicher Name; nur eindeutig, nur aktive Personen-Konten')]
    public function test_konto_vorschlag(): void
    {
        $lea = $this->user('lea@dpsg-trier.de');
        $max = User::query()->create(['name' => 'Max Muster', 'email' => 'm.muster@example.org', 'upn' => 'Max.Muster@dpsg-trier.de', 'status' => 'active']);
        $vorschlag = fn (Person $p) => (new KontoVorschlag)->fuer($p);

        $v = $vorschlag($this->person(['email' => 'LEA@dpsg-trier.de']));
        $this->assertSame([$lea->id, KontoVorschlag::EMAIL], [$v['user']->id ?? null, $v['grund'] ?? null]);
        $this->assertSame($max->id, $vorschlag($this->person(['name' => 'Jemand', 'email' => 'max.muster@dpsg-trier.de']))['user']->id ?? null);
        $this->assertSame([$max->id, KontoVorschlag::NAME], [($v = $vorschlag($this->person(['name' => 'max muster', 'email' => 'anders@example.org'])))['user']->id ?? null, $v['grund'] ?? null]);
        $this->assertNull($vorschlag($this->person(['name' => 'Niemand', 'email' => 'niemand@example.org'])));

        // Schon verknüpft: kein Vorschlag.
        $this->assertNull($vorschlag($this->person(['email' => 'lea@dpsg-trier.de', 'user_id' => $lea->id])));

        // Zweideutig: Adresse passt zu E-Mail des einen und UPN des anderen Kontos.
        User::query()->create(['name' => 'Doppelt', 'email' => 'zweit@example.org', 'upn' => 'lea@dpsg-trier.de', 'status' => 'active']);
        $this->assertNull($vorschlag($this->person(['name' => 'Lea Leitung', 'email' => 'lea@dpsg-trier.de'])));

        // Deaktivierte Konten und Rollen-Zugänge (kein Mensch) zählen nicht.
        User::query()->create(['name' => 'Alt', 'email' => 'alt@example.org', 'status' => 'disabled']);
        User::query()->create(['name' => 'Stammesvorstand', 'email' => 'vorstand@example.org', 'status' => 'active', 'kind' => 'rolle']);
        $this->assertNull($vorschlag($this->person(['name' => 'Alt', 'email' => 'alt@example.org'])));
        $this->assertNull($vorschlag($this->person(['name' => 'Stammesvorstand', 'email' => 'vorstand@example.org'])));
    }

    #[TestDox('Konto-Vorschläge prüfen: die Verwaltung hakt ab, nur Angehaktes wird verknüpft, mit Audit-Eintrag; Vorschlag am Feld lässt sich übernehmen')]
    public function test_vorschlaege_bestaetigen(): void
    {
        $lea = $this->user('lea@dpsg-trier.de');
        $tom = $this->user('tom@dpsg-trier.de');
        $leaEintrag = $this->person(['email' => 'lea@dpsg-trier.de']);
        $tomEintrag = $this->person(['name' => 'Tom', 'email' => 'tom@dpsg-trier.de']);
        $this->actingAs($this->user('buero@dpsg-trier.de', 'verwaltung', 'global'));

        Livewire::test(ListPeople::class)
            ->assertActionVisible('kontoVorschlaege')
            ->callAction('kontoVorschlaege', ['personen' => [$leaEintrag->id]])
            ->assertNotified('1 Eintrag zugeordnet');

        $this->assertSame([$lea->id, null], [$leaEintrag->fresh()->user_id, $tomEintrag->fresh()->user_id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'person.konto:zugeordnet')->count());

        // Einzeln: „Übernehmen“ am Feld, dann Speichern.
        Livewire::test(EditPerson::class, ['record' => $tomEintrag->getRouteKey()])
            ->assertSee('Vorschlag: Tom (tom@dpsg-trier.de), gleiche E-Mail')
            ->callAction(TestAction::make('kontoUebernehmen')->schemaComponent('user_id'))
            ->assertFormSet(['user_id' => $tom->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($tom->id, $tomEintrag->fresh()->user_id);
        $this->assertSame(2, AuditLog::query()->where('action', 'person.konto:zugeordnet')->count());

        // Nichts mehr vorzuschlagen.
        Livewire::test(ListPeople::class)->assertActionHidden('kontoVorschlaege');
    }

    #[TestDox('Ohne verknüpften Eintrag gibt es die Seite nicht')]
    public function test_ohne_eintrag(): void
    {
        $this->actingAs($this->user('autor@dpsg-trier.de'));

        $this->assertFalse(MeinEintrag::canAccess());
        $this->get(MeinEintrag::getUrl())->assertForbidden();
    }

    #[TestDox('Text und Einwilligung selbst ändern: sofort sichtbar, Name und Funktion bleiben, Audit-Eintrag')]
    public function test_text_und_einwilligung(): void
    {
        $lea = $this->user('lea@dpsg-trier.de');
        $person = $this->person(['user_id' => $lea->id]);
        $this->actingAs($lea);

        $this->get(MeinEintrag::getUrl())->assertOk()
            ->assertSee('Lea Leitung')->assertSee('Referentin')
            ->assertSee('nein (du hast die Veröffentlichung nicht erlaubt)');

        Livewire::test(MeinEintrag::class)
            ->assertSet('data.bio', 'Alter Text')
            ->set('data.bio', "Ich bin Lea.\n\nIch mag Zeltlager.")
            ->set('data.consent', true)
            // Versuch, Felder der Verwaltung mitzuschicken: wird ignoriert.
            ->set('data.name', 'Jemand anderes')
            ->set('data.role', 'Diözesanvorsitzende')
            ->call('speichern')
            ->assertHasNoErrors()
            ->assertNotified('Gespeichert');

        $person->refresh();
        $this->assertSame(["Ich bin Lea.\n\nIch mag Zeltlager.", true], [$person->bio, $person->consent]);
        $this->assertSame(['Lea Leitung', 'Referentin'], [$person->name, $person->role]);
        $this->assertTrue(Person::query()->visible()->whereKey($person->id)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'person.selbst_bearbeitet')->where('subject_id', $person->id)->exists());

        // Einwilligung zurückziehen: sofort nicht mehr sichtbar.
        Livewire::test(MeinEintrag::class)->set('data.consent', false)->call('speichern');
        $this->assertFalse(Person::query()->visible()->whereKey($person->id)->exists());
    }

    #[TestDox('Foto selbst hochladen: nur mit Bildnachweis und Bestätigung der Bildrechte; landet in der Mediathek als eigener Upload')]
    public function test_foto(): void
    {
        $lea = $this->user('lea@dpsg-trier.de');
        $person = $this->person(['user_id' => $lea->id]);
        $this->actingAs($lea);

        Livewire::test(MeinEintrag::class)
            ->set('data.portrait_upload', [UploadedFile::fake()->image('lea.jpg', 800, 800)])
            ->set('data.portrait_credit', 'Selbst')
            ->call('speichern')
            ->assertHasErrors(['data.bildrechte']);
        $this->assertNull($person->fresh()->photo_media_id);

        Livewire::test(MeinEintrag::class)
            ->set('data.portrait_upload', [UploadedFile::fake()->image('lea.jpg', 800, 800)])
            ->set('data.portrait_credit', 'Selbst')
            ->set('data.bildrechte', true)
            ->call('speichern')
            ->assertHasNoErrors();

        $foto = $person->fresh()->photo;
        $this->assertNotNull($foto);
        $this->assertSame([$lea->id, true, 'Selbst'], [$foto->uploaded_by, $foto->rights_confirmed, $foto->credit]);
        // Eigener Upload: in der Mediathek selbst änderbar (MediaPolicy).
        $this->assertTrue($lea->can('update', $foto));

        Livewire::test(MeinEintrag::class)->call('fotoEntfernen')->assertNotified('Foto entfernt');
        $this->assertNull($person->fresh()->photo_media_id);
    }

    #[TestDox('Fremde Einträge lassen sich nicht wählen oder ändern; mehrere eigene Einträge sind wählbar')]
    public function test_nur_eigene(): void
    {
        $lea = $this->user('lea@dpsg-trier.de');
        $eigen = $this->person(['user_id' => $lea->id]);
        $zweit = $this->person(['user_id' => $lea->id, 'name' => 'Lea Leitung', 'role' => 'Arbeitskreis', 'sort' => 2]);
        $fremd = $this->person(['user_id' => $this->user('max@dpsg-trier.de')->id, 'name' => 'Max', 'bio' => 'Text von Max']);
        $this->actingAs($lea);

        Livewire::test(MeinEintrag::class)
            ->assertSet('personId', $eigen->id)
            ->call('waehlen', $zweit->id)
            ->assertSet('personId', $zweit->id)
            ->call('waehlen', $fremd->id)
            ->assertForbidden();

        Livewire::test(MeinEintrag::class)
            ->set('personId', $fremd->id)
            ->set('data.bio', 'Übernommen')
            ->call('speichern')
            ->assertForbidden();
        $this->assertSame('Text von Max', $fremd->fresh()->bio);
    }
}
