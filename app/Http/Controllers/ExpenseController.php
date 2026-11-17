<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biaya (hanya Owner). Kategori "operasional" & "lain" mengurangi untung bersih.
 * Ongkos bagasi TIDAK dicatat di sini — sudah diisi di Jadwal Bagasi dan masuk modal per kitab.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = CarbonImmutable::parse($month.'-01');

        $rows = DB::table('expenses as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->whereBetween('e.spent_at', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->whereIn('e.category', ['operasional', 'lain'])
            ->orderByDesc('e.spent_at')->orderByDesc('e.id')
            ->get(['e.id', 'e.category', 'e.amount', 'e.spent_at', 'e.note', 'u.name as by']);

        return Inertia::render('expenses/index', [
            'month' => $month,
            'monthLabel' => $start->locale('id')->translatedFormat('F Y'),
            'items' => $rows->map(fn ($e) => ['id' => $e->id, 'category' => $e->category, 'amount' => (int) $e->amount, 'spent_at' => $e->spent_at, 'note' => $e->note, 'by' => $e->by]),
            'totals' => [
                'operasional' => (int) $rows->where('category', 'operasional')->sum('amount'),
                'lain' => (int) $rows->where('category', 'lain')->sum('amount'),
            ],
        ]);
    }

    private function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['operasional', 'lain'])],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'spent_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate($this->rules(), ['note.required' => 'Tulis keterangan biaya (mis. gaji admin, listrik).']);
        $id = DB::table('expenses')->insertGetId($d + ['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::log('expense.create', null, null, $d + ['expense_id' => $id]);

        return back()->with('success', 'Biaya dicatat.');
    }

    public function update(Request $request, int $expense): RedirectResponse
    {
        $d = $request->validate($this->rules());
        $before = (array) DB::table('expenses')->where('id', $expense)->first();
        abort_unless($before, 404);
        DB::table('expenses')->where('id', $expense)->update($d + ['updated_at' => now()]);
        Audit::log('expense.update', null, array_intersect_key($before, $d), $d + ['expense_id' => $expense]);

        return back()->with('success', 'Perubahan disimpan.');
    }

    public function destroy(int $expense): RedirectResponse
    {
        $before = (array) DB::table('expenses')->where('id', $expense)->first();
        abort_unless($before, 404);
        DB::table('expenses')->where('id', $expense)->delete();
        Audit::log('expense.delete', null, $before);

        return back()->with('success', 'Biaya dihapus.');
    }
}
