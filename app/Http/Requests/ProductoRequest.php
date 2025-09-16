<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;  // Clase base para validaciones de formularios
use Illuminate\Validation\Rule;              // Clase para reglas avanzadas de validación

/*
|--------------------------------------------------------------------------
| ProductoRequest
|--------------------------------------------------------------------------
| Este archivo define las reglas de validación para crear o actualizar
| un producto. Se utiliza en el ProductoController (store y update).
| 
| Conecta con:
|   - ProductoController@store
|   - ProductoController@update
|
| Sirve para asegurar que los datos ingresados (nombre, cantidad, precio,
| categoría) cumplan las condiciones antes de guardarse en la base de datos.
*/
class ProductoRequest extends FormRequest
{
    // Autoriza que cualquier usuario autenticado pueda usar este request
    public function authorize(): bool
    {
        return true;
    }

    // RULES → Definir reglas de validación
    // Conecta con: ProductoController (al guardar/editar productos)
    public function rules(): array
    {
        return [
            // Nombre obligatorio, texto, máximo 255 caracteres
            'nombre'    => 'required|string|max:255',

            // Cantidad obligatoria, número entero, no negativo
            'cantidad'  => 'required|integer|min:0',

            // Precio obligatorio, número, no negativo
            'precio'    => 'required|numeric|min:0',

            // Categoría obligatoria, debe existir en la tabla "categorias"
            'categoria' => [
                'required',
                'string',
                'max:255',
                Rule::exists('categorias', 'id'), // conecta con tabla "categorias"
            ],
        ];
    }

    // MESSAGES → Mensajes personalizados de error
    // Se muestran cuando la validación falla
    public function messages(): array
    {
        return [
            'nombre.required'    => 'El nombre es obligatorio.',
            'cantidad.required'  => 'La cantidad es obligatoria.',
            'precio.required'    => 'El precio es obligatorio.',
            'categoria.required' => 'Debes seleccionar una categoría.',
            'categoria.exists'   => 'La categoría seleccionada no es válida.',
        ];
    }

    // prepareForValidation → Normaliza el campo "precio" antes de validarlo
    // Conecta con: ProductoController (al guardar/editar productos)
    // Ejemplo: convierte "1.234,50" → "1234.50"
    protected function prepareForValidation()
    {
        if ($this->has('precio')) {
            $valor = (string) $this->input('precio');

            // Quitar caracteres que no sean dígitos, puntos o comas
            $valor = preg_replace('/[^\d\.,]/', '', $valor);

            if (str_contains($valor, ',')) {
                // Formato europeo: "1.234.567,89"
                $valor = str_replace('.', '', $valor);   // quitar separadores de miles
                $valor = str_replace(',', '.', $valor);  // usar punto como decimal
            } else {
                // Formato americano: "1234.56"
                if (preg_match('/\.\d{1,2}$/', $valor)) {
                    // Si el punto es decimal → separar parte entera y decimal
                    $pos = strrpos($valor, '.');
                    $ent = substr($valor, 0, $pos);
                    $dec = substr($valor, $pos);
                    $ent = str_replace('.', '', $ent); // quitar puntos de miles
                    $valor = $ent.$dec;
                } else {
                    // Solo puntos como miles → quitarlos
                    $valor = str_replace('.', '', $valor);
                }
            }

            // Reemplazar el valor normalizado en el request
            $this->merge([
                'precio' => $valor,
            ]);
        }
    }
}