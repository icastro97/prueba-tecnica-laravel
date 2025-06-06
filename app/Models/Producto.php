<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'sku',
        'nombre',
        'descripcion',
        'cantidad',
        'precio',
        'total',
    ];

    protected $casts = [
        'sku' => 'integer',
        'cantidad' => 'integer',
        'precio' => 'decimal:2',
        'total' => 'decimal:2',
    ];
}
