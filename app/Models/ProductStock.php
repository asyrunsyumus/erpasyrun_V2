<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $table = 'product_stock';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    const CREATED_AT = null;

    protected $fillable = ['product_id', 'qty', 'avg_cost_idr'];
}
