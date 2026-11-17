<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Notifikasi per orang: dibaca satu orang tidak hilang di rekan seperannya (perbaikan sistem lama). */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/index', [
            'items' => $request->user()->notifications()->limit(100)->get()->map(fn ($n) => [
                'id' => $n->id,
                'message' => $n->data['message'] ?? '',
                'href' => $n->data['href'] ?? null,
                'read' => $n->read_at !== null,
                'at' => $n->created_at->locale('id')->diffForHumans(),
            ]),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $href = $n->data['href'] ?? '/dashboard';

        return redirect(str_starts_with($href, '/') ? $href : '/dashboard');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
