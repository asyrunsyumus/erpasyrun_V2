<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'address', 'city', 'kelurahan', 'kecamatan', 'kode_pos', 'type', 'notes'];

    protected static function booted(): void
    {
        static::creating(function (Customer $c) {
            if (! $c->code) {
                $next = (int) static::withTrashed()->max('id') + 1;
                $c->code = 'C'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function deletionBlocker(): ?string
    {
        return \DB::table('orders')->where('customer_id', $this->id)->exists()
            ? 'Customer ini sudah punya pesanan, jadi tidak boleh dihapus.'
            : null;
    }
}
