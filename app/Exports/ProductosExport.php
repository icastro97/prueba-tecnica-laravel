<?php

namespace App\Exports;

use App\Models\Producto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function collection()
    {
        return $this->products;
    }

    public function headings(): array
    {
        return [
            'ID',
            'SKU',
            'Nombre',
            'Descripción',
            'Precio',
            'Cantidad',
            'Total'
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->sku,
            $product->nombre,
            $product->descripcion,
            $product->precio,
            $product->cantidad,
            $product->precio * $product->cantidad
        ];
    }
}
