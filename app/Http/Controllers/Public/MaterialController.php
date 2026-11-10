<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\MaterialMail;
use App\Models\Material;
use App\Support\Materialverleih;
use App\Support\SpamGuard;
use App\Support\Spamverdacht;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Materialverleih auf der Website (docs/materialverleih.md): Liste des
 * verleihbaren Materials und Anfrage per Formular. Die Anfrage landet als
 * „angefragt“ im Backend; die Materialwart*innen bekommen eine Mail.
 * Spamschutz wie beim Kontaktformular.
 */
class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Materialverleih::oeffentlich(), 404);
        $material = Material::query()->where('aktiv', true)->orderBy('kategorie')->orderBy('name')->get();

        return view('material.index', [
            'gruppen' => $material->groupBy(fn (Material $m) => $m->kategorie ?: 'Sonstiges'),
            'material' => $material,
            'gewaehlt' => $request->integer('material') ?: old('material_id'),
        ]);
    }

    public function anfrage(Request $request): RedirectResponse
    {
        abort_unless(Materialverleih::oeffentlich(), 404);
        $zurueck = fn (string $status) => redirect()->to(route('material.index').'#anfrage')->with('material_status', $status);

        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? $zurueck('gesendet') : back()->withInput()->with('material_status', 'abgelaufen');
        }

        $data = $request->validate([
            'material_id' => ['required', 'integer', 'exists:materialien,id'],
            'menge' => ['nullable', 'integer', 'min:1', 'max:999'],
            'von' => ['required', 'date', 'after_or_equal:today'],
            'bis' => ['required', 'date', 'after_or_equal:von', 'before:'.now()->addYears(2)->toDateString()],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'telefon' => ['nullable', 'string', 'max:40'],
            'gruppierung' => ['nullable', 'string', 'max:150'],
            'zweck' => ['nullable', 'string', 'max:500'],
        ], [
            'material_id.required' => 'Bitte wähle, was du ausleihen möchtest.',
            'von.after_or_equal' => 'Der Zeitraum muss in der Zukunft liegen.',
            'bis.after_or_equal' => '„Bis“ darf nicht vor „Von“ liegen.',
        ]);

        $material = Material::query()->where('aktiv', true)->findOrFail($data['material_id']);
        $nachweis = $request->input('altcha');
        $verdacht = Spamverdacht::grund(['name' => $data['name'], 'nachricht' => (string) ($data['zweck'] ?? '')], is_string($nachweis) ? $nachweis : null);

        $reservierung = $material->reservierungen()->create([
            ...$data,
            'menge' => min((int) ($data['menge'] ?? 1), $material->menge),
            'status' => 'angefragt',
            'notiz' => $verdacht ? 'Spamverdacht: '.$verdacht : null,
        ]);

        if ($verdacht === null) {
            foreach (Materialverleih::empfaenger() as $user) {
                Mail::to($user->email, $user->name)->send(new MaterialMail($reservierung, 'anfrage'));
            }
        }

        return $zurueck('gesendet');
    }
}
