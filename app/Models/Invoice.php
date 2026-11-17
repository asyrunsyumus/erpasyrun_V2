<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'due_at' => 'date'];
    }
}
