<?php

namespace App\Http\Controllers;

// Requests personalizados → validación de productos
use App\Http\Requests\ProductoRequest;
// Modelos → representan tablas de la BD
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/*
|--------------------------------------------------------------------------
| ProductoController
|--------------------------------------------------------------------------
| Controlador principal para manejar productos (CRUD).
| CRUD = Crear, Leer, Actualizar, Eliminar.
|
| Conecta con rutas definidas en:
|   - web.php → Route::resource('productos', ProductoController::class)
|
| Vistas relacionadas:
|   - resources/views/productos/*
*/
class ProductoController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX → Mostrar lista de productos con filtros, orden, stock y paginación
    |--------------------------------------------------------------------------
    | Conecta con: GET /productos → productos.index
    | Vista: resources/views/productos/index.blade.php
    */
    public function index(Request $request)
    {
        // 📌 Filtros de precios (mínimo y máximo)
        $precioMin = $request->precio_min ? (int) str_replace(['.', ','], '', $request->precio_min) : null;
        $precioMax = $request->precio_max ? (int) str_replace(['.', ','], '', $request->precio_max) : null;

        // 📌 Consultar productos desde la BD con filtros dinámicos
        $productosTodos = Producto::with('categoriaRelacion') // traer relación con categoría
            ->when($request->search, function ($q) use ($request) {
                // Filtro: buscar por nombre
                $q->where('nombre', 'like', '%'.$request->search.'%');
            })
            ->when($request->filled('categorias') || $request->filled('categoria'), function ($q) use ($request) {
                // Filtro: categorías seleccionadas
                $cats = $request->filled('categorias')
                    ? (array) $request->categorias   // Ejemplo: ['Ropa','Electrónica']
                    : [$request->categoria];         // Ejemplo: 'Ropa'
                $q->whereIn('categoria', $cats);
            })
            ->when($precioMin, function ($q) use ($precioMin) {
                // Filtro: precio >= precioMin
                $q->where('precio', '>=', $precioMin);
            })
            ->when($precioMax, function ($q) use ($precioMax) {
                // Filtro: precio <= precioMax
                $q->where('precio', '<=', $precioMax);
            })
            ->when($request->stock, function ($q, $stock) {
                // 📌 Filtro: stock disponible o agotado
                $stock = (array) $stock;

                if (in_array('disponibles', $stock) && !in_array('agotados', $stock)) {
                    $q->where('cantidad', '>', 0); // solo disponibles
                }

                if (in_array('agotados', $stock) && !in_array('disponibles', $stock)) {
                    $q->where('cantidad', '=', 0); // solo agotados
                }
            })
            ->when($request->ordenar, function ($q) use ($request) {
                // 📌 Orden dinámico según lo seleccionado
                switch ($request->ordenar) {
                    case 'nombre_asc':  $q->orderBy('nombre', 'asc'); break;
                    case 'nombre_desc': $q->orderBy('nombre', 'desc'); break;
                    case 'precio_asc':  $q->orderBy('precio', 'asc'); break;
                    case 'precio_desc': $q->orderBy('precio', 'desc'); break;
                    case 'stock_asc':   $q->orderBy('cantidad', 'asc'); break;
                    case 'stock_desc':  $q->orderBy('cantidad', 'desc'); break;
                    default:            $q->orderBy('id', 'asc'); break; // por defecto ID asc
                }
            }, function ($q) {
                // Si no se selecciona nada → ordenar por ID ascendente
                $q->orderBy('id', 'asc');
            })
            ->get();

        // 📌 Numeración consecutiva global (para mostrar en la tabla)
        $contador = 1;
        foreach ($productosTodos as $producto) {
            $producto->numero_fijo = $contador++;
        }

        // 📌 Paginación y opción "ver todo"
        $pagina = $request->get('page', 1);          // página actual
        $verTodo = $request->get('verTodo', false);  // si pidió "Ver todo"

        if ($verTodo) {
            // Mostrar todos los productos sin paginar
            $productosPagina = $productosTodos;
            $productos = new LengthAwarePaginator(
                $productosPagina,
                $productosTodos->count(),
                $productosTodos->count(),
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            // Paginación normal → 10 productos por página
            $porPagina = 10;
            $productosPagina = $productosTodos->forPage($pagina, $porPagina);

            $productos = new LengthAwarePaginator(
                $productosPagina,
                $productosTodos->count(),
                $porPagina,
                $pagina,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // 📌 Calcular stock total y valor total de la página actual
        $pageStockTotal = $productosPagina->sum('cantidad');
        $pageValorTotal = $productosPagina->sum(fn($p) => $p->cantidad * $p->precio);

        // 📌 Todas las categorías disponibles
        $categorias = Categoria::all();

        // Retornar vista index con variables compactadas
        return view('productos.index', compact(
            'productos',
            'categorias',
            'pageStockTotal',
            'pageValorTotal'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE → Mostrar formulario para crear un producto
    |--------------------------------------------------------------------------
    | Conecta con: GET /productos/create → productos.create
    | Vista: resources/views/productos/create.blade.php
    */
    public function create()
    {
        $categorias = Categoria::all(); // cargar categorías para seleccionar
        return view('productos.create', compact('categorias'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE → Guardar un nuevo producto en la base de datos
    |--------------------------------------------------------------------------
    | Conecta con: POST /productos → productos.store
    */
    public function store(ProductoRequest $request)
    {
        $data = $request->validated(); // validar datos con ProductoRequest
        $data['precio'] = (float) str_replace('.', '', $request->precio); // limpiar precio

        // Asignar consecutivo automático
        $ultimoConsecutivo = Producto::max('consecutivo') ?? 0;
        $data['consecutivo'] = $ultimoConsecutivo + 1;

        // Guardar producto en la BD
        Producto::create($data);

        // Redirigir a index con mensaje de éxito
        return redirect()->route('productos.index')->with('success', 'Producto creado con éxito.');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT → Mostrar formulario para editar un producto existente
    |--------------------------------------------------------------------------
    | Conecta con: GET /productos/{id}/edit → productos.edit
    | Vista: resources/views/productos/edit.blade.php
    */
    public function edit(Producto $producto)
    {
        $categorias = Categoria::all(); // cargar categorías
        return view('productos.edit', compact('producto', 'categorias'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE → Actualizar un producto en la base de datos
    |--------------------------------------------------------------------------
    | Conecta con: PUT/PATCH /productos/{id} → productos.update
    */
    public function update(ProductoRequest $request, Producto $producto)
    {
        $data = $request->validated(); // validar con ProductoRequest
        $data['precio'] = (float) str_replace('.', '', $request->precio); // limpiar precio

        // Actualizar datos del producto
        $producto->update($data);

        // Mantener la misma página de la paginación
        $pagina = $request->input('page', 1);

        return redirect()
            ->route('productos.index', ['page' => $pagina])
            ->with('success', 'Producto actualizado con éxito.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY → Eliminar un producto
    |--------------------------------------------------------------------------
    | Conecta con: DELETE /productos/{id} → productos.destroy
    */
    public function destroy(Producto $producto)
    {
        $producto->delete(); // borrar de la BD
        return redirect()->route('productos.index')->with('success', 'Producto eliminado con éxito.');
    }
}