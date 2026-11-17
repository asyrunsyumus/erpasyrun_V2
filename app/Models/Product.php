<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /** Kolom yang boleh diubah Admin. Harga hanya lewat PRICE_FIELDS (khusus Owner). */
    public const INFO_FIELDS = ['title_ar', 'title_latin', 'author', 'publisher', 'category_id', 'weight_grams', 'primary_maktabah_id', 'is_active'];

    public const PRICE_FIELDS = ['price_egp', 'price_regular', 'price_reseller'];

    protected $fillable = ['sku', ...self::INFO_FIELDS, ...self::PRICE_FIELDS];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function primaryMaktabah(): BelongsTo
    {
        return $this->belongsTo(Maktabah::class, 'primary_maktabah_id');
    }

    public function altMaktabahs(): BelongsToMany
    {
        return $this->belongsToMany(Maktabah::class, 'product_maktabah');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Product $p) {
            if (! $p->sku) {
                $next = (int) static::withTrashed()->max('id') + 1;
                $p->sku = 'K'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function deletionBlocker(): ?string
    {
        return \DB::table('order_items')->where('product_id', $this->id)->exists()
            ? 'Kitab ini sudah ada di pesanan, jadi tidak boleh dihapus. Nonaktifkan saja agar tidak muncul lagi.'
            : null;
    }
}
