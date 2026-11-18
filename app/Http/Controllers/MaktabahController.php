<?php

namespace App\Http\Controllers;

use App\Models\Maktabah;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaktabahController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $items = Maktabah::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('area', 'like', "%{$q}%")))
            ->withCount(['products' => fn ($p) => $p])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('master/maktabahs', [
            'items' => $items,
            'filters' => ['q' => $q],
            'canEdit' => $request->user()->isOwner(),
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'gmaps_url' => ['nullable', 'url', 'max:500', 'regex:#^https?://#i'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $m = Maktabah::create($request->validate($this->rules()));
        Audit::log('maktabah.create', $m, null, $m->only('name'));

        return back()->with('success', "Maktabah {$m->name} ditambahkan.");
    }

    public function update(Request $request, Maktabah $maktabah): RedirectResponse
    {
        $maktabah->update($request->validate($this->rules()));

        return back()->with('success', 'Perubahan disimpan.');
    }

    public function destroy(Maktabah $maktabah): RedirectResponse
    {
        if ($why = $maktabah->deletionBlocker()) {
            return back()->withErrors(['delete' => $why]);
        }
        $maktabah->delete();
        Audit::log('maktabah.delete', $maktabah, $maktabah->only('name'));

        return back()->with('success', "Maktabah {$maktabah->name} dihapus.");
    }
}
