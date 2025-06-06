<?php

namespace App\Services;

use App\Models\Producto;

class ProductoService
{
    public function create(array $data): Producto
    {
        $data['total'] = $data['cantidad'] * $data['precio'];
        return Producto::create($data);
    }

    public function update(Producto $producto, array $data): bool
    {
        $data['total'] = $data['cantidad'] * $data['precio'];
        return $producto->update($data);
    }
}
