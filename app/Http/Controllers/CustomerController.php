<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('type');

        $items = Customer::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")->orWhere('code', $q)))
            ->when(in_array($type, ['reguler', 'reseller'], true), fn ($w) => $w->where('type', $type))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('master/customers', [
            'items' => $items,
            'filters' => ['q' => $q, 'type' => $type],
            'canDelete' => $request->user()->isOwner(),
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(['reguler', 'reseller'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $c = Customer::create($request->validate($this->rules()));

        return back()->with('success', "Customer {$c->name} ditambahkan.");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validate($this->rules()));
        Audit::changes('customer.update', $customer, ['type']);

        return back()->with('success', 'Perubahan disimpan.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($why = $customer->deletionBlocker()) {
            return back()->withErrors(['delete' => $why]);
        }
        $customer->delete();
        Audit::log('customer.delete', $customer, $customer->only('code', 'name'));

        return back()->with('success', "Customer {$customer->name} dihapus.");
    }
}
