<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Exports\ProductosExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Services\ProductoService;

class ProductoController extends Controller
{
    protected ProductoService $productoService;

    public function __construct(protected Producto $producto, ProductoService $productoService)
    {
        $this->productoService = $productoService;
    }

    public function index(Request $request)
    {
        $query = $this->producto->newQuery();

        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        if ($request->filled('sku')) {
            $query->where('sku', $request->sku);
        }

        if ($request->filled('precio_min')) {
            $query->where('precio', '>=', $request->precio_min);
        }

        if ($request->filled('precio_max')) {
            $query->where('precio', '<=', $request->precio_max);
        }

        $productos = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('Productos/Index', [
            'productos' => $productos,
            'filters' => $request->all(),
        ]);
    }

    public function store(StoreProductoRequest $request)
    {
        $validated = $request->validated();

        try {

            $this->productoService->create($validated);

            return redirect()->back()->with('success', 'Producto creado');
        } catch (\Exception $e) {
            Log::error("Error al crear producto: " . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear el producto');
        }
    }

    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $validated = $request->validated();


        try {

            $this->productoService->update($producto, $validated);
            
            return redirect()->back()->with('success', 'Producto actualizado');
        } catch (\Exception $e) {
            Log::error("Error actualizando producto ID {$producto->id}: " . $e->getMessage());
            return redirect()->back()->with('error', 'Error actualizando el producto');
        }
    }

    public function destroy(Producto $producto)
    {
        try {
            $producto->delete();
            return redirect()->back()->with('success', 'Producto eliminado');
        } catch (\Exception $e) {
            Log::error("Error eliminando producto ID {$producto->id}: " . $e->getMessage());
            return redirect()->back()->with('error', 'Error eliminando el producto');
        }
    }

    public function checkSku($sku)
    {
        if (!is_numeric($sku)) {
            return response()->json(['error' => 'SKU inválido'], 400);
        }

        $exists = $this->producto->where('sku', $sku)->exists();

        return response()->json(['exists' => $exists]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['nombre', 'sku', 'precio_min', 'precio_max']);
        $exportType = $request->get('export_type', 'csv');

        $validatedFilters = validator($filters, [
            'nombre' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'integer'],
            'precio_min' => ['nullable', 'numeric', 'min:0'],
            'precio_max' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $query = $this->producto->newQuery()
            ->when($validatedFilters['nombre'] ?? false, fn($q, $nombre) => $q->where('nombre', 'like', "%{$nombre}%"))
            ->when($validatedFilters['sku'] ?? false, fn($q, $sku) => $q->where('sku', $sku))
            ->when($validatedFilters['precio_min'] ?? false, fn($q, $precio) => $q->where('precio', '>=', $precio))
            ->when($validatedFilters['precio_max'] ?? false, fn($q, $precio) => $q->where('precio', '<=', $precio));

        if ($exportType === 'csv') {
            $fileName = 'productos_' . now()->format('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ];

            $callback = function () use ($query) {
                $file = fopen('php://output', 'w');

                fputcsv($file, ['ID', 'SKU', 'Nombre', 'Descripción', 'Precio', 'Cantidad', 'Total']);

                $query->chunk(200, function ($products) use ($file) {
                    foreach ($products as $product) {
                        fputcsv($file, [
                            $product->id,
                            $product->sku,
                            $product->nombre,
                            $product->descripcion,
                            $product->precio,
                            $product->cantidad,
                            $product->precio * $product->cantidad,
                        ]);
                    }
                });

                fclose($file);
            };

            return Response::stream($callback, 200, $headers);
        }

        return Excel::download(new ProductosExport($query->get()), 'productos_' . now()->format('Y-m-d') . '.xlsx');
    }
}
