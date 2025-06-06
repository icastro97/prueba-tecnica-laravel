<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use Illuminate\Support\Str;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $cantidad = rand(1, 20);
            $precio = rand(1000, 5000);

            Producto::create([
                'sku' => 1000 + $i,
                'nombre' => 'Producto ' . $i,
                'descripcion' => 'Descripción del producto ' . $i,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'total' => $cantidad * $precio,
            ]);
        }
    }
}
