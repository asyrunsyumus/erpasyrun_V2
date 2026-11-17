<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Log audit append-only (prinsip P8). */
class Audit
{
    public static function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null): void
    {
        $req = request();

        DB::table('audit_logs')->insert([
            'user_id' => $req?->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before ? json_encode($before) : null,
            'after' => $after ? json_encode($after) : null,
            'ip' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    /** Catat hanya kolom yang berubah. */
    public static function changes(string $action, Model $model, array $fields): void
    {
        $before = [];
        $after = [];
        foreach ($fields as $f) {
            if ($model->wasChanged($f)) {
                $before[$f] = $model->getOriginal($f);
                $after[$f] = $model->getAttribute($f);
            }
        }
        if ($after) {
            self::log($action, $model, $before, $after);
        }
    }
}
