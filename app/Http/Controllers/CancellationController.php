<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CancellationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Owner & Admin (route). Yang menyetujui boleh orang yang sama dengan yang mengajukan (PRD). */
class CancellationController extends Controller
{
    public function index(): Response
    {
        $rows = DB::table('cancellations as x')
            ->join('orders as o', 'o.id', '=', 'x.order_id')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->join('users as r', 'r.id', '=', 'x.requested_by')
            ->leftJoin('users as d', 'd.id', '=', 'x.decided_by')
            ->orderByRaw("CASE WHEN x.status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('x.id')
            ->limit(100)
            ->get(['x.*', 'o.number', 'o.total', 'o.payment_status', 'o.paid_at', 'c.name as customer', 'r.name as requested_by_name', 'd.name as decided_by_name'])
            ->map(fn ($x) => [
                'id' => $x->id,
                'order_id' => $x->order_id,
                'number' => $x->number,
                'customer' => $x->customer,
                'total' => (int) $x->total,
                'paid' => $x->payment_status === 'paid',
                'reason' => $x->reason,
                'status' => $x->status,
                'status_before' => $x->status_before,
                'requested_by' => $x->requested_by_name,
                'requested_at' => CarbonImmutable::parse($x->created_at)->locale('id')->translatedFormat('j M Y, H.i'),
                'decided_by' => $x->decided_by_name,
                'decided_at' => $x->decided_at ? CarbonImmutable::parse($x->decided_at)->locale('id')->translatedFormat('j M Y') : null,
                'decision_note' => $x->decision_note,
                'refund_amount' => $x->refund_amount !== null ? (int) $x->refund_amount : null,
                'refund_method' => $x->refund_method,
                'has_proof' => (bool) $x->refund_proof_path,
            ]);

        return Inertia::render('cancellations/index', ['items' => $rows]);
    }

    public function store(Request $request, Order $order, CancellationService $svc): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Tulis alasan pembatalan.', 'reason.min' => 'Alasan terlalu singkat.']);
        $svc->request($order, $data['reason'], $request->user());

        return back()->with('success', 'Pengajuan pembatalan terkirim. Putuskan di menu Pembatalan.');
    }

    public function approve(Request $request, int $cancellation, CancellationService $svc): RedirectResponse
    {
        $data = $request->validate([
            'refund_amount' => ['required', 'integer', 'min:0'],
            'refund_method' => ['nullable', 'string', 'max:120', 'required_unless:refund_amount,0'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], ['refund_method.required_unless' => 'Isi metode refund (mis. transfer BSI).']);

        $path = $request->file('proof')?->store('refund-proofs', 'local');
        try {
            $svc->approve($cancellation, (int) $data['refund_amount'], $data['refund_method'] ?? null, $path, $request->user());
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        return back()->with('success', 'Pembatalan disetujui. Kitab yang sudah ada kembali ke stok Indonesia.');
    }

    public function reject(Request $request, int $cancellation, CancellationService $svc): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $svc->reject($cancellation, $data['note'] ?? null, $request->user());

        return back()->with('success', 'Pengajuan ditolak. Pesanan kembali ke tahap sebelumnya dan boleh diajukan lagi bila perlu.');
    }

    public function proof(int $cancellation): StreamedResponse
    {
        $path = DB::table('cancellations')->where('id', $cancellation)->value('refund_proof_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
