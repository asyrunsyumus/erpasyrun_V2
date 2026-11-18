<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Maktabah extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'area', 'phone', 'notes', 'gmaps_url'];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'primary_maktabah_id');
    }

    /** Alasan tidak boleh dihapus, atau null bila aman. */
    public function deletionBlocker(): ?string
    {
        if (Product::where('primary_maktabah_id', $this->id)->exists()) {
            return 'Maktabah ini masih menjadi toko utama beberapa kitab. Pindahkan dulu kitabnya ke maktabah lain.';
        }
        if (\DB::table('purchases')->where('maktabah_id', $this->id)->exists()) {
            return 'Maktabah ini sudah punya riwayat belanja, jadi tidak boleh dihapus.';
        }

        return null;
    }
}
