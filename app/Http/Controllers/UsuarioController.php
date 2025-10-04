<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UsuarioController extends Controller
{
    // Devuelve todos los registros de la tabla User
    public function index()
    {
        $users = User::all(); // Cambié la variable a plural
        return response()->json($users);
    }

    // Devuelve un registro de la tabla usuarios por ID
    public function registroUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user);
    }

    // Guardar registros en la tabla
    public function store(Request $request)
    {
        // Validación de la información
        $request->validate([
            'name' => 'required', // 
            'email' => 'required|email|unique:users', // 
        ]);

        // Inserción o creación del registro
        $user = User::create([
            'name' => $request->name, // 
            'email' => $request->email,
            'password' => bcrypt($request->password), // Asegúrate de que estás hasheando la contraseña
        ]);

        return response()->json($user, 201); // Respuesta con código 201 para indicar creación exitosa
    }

    // Actualizar registros
    public function update(Request $request, $id)
    {
        // Validación de la información
        $request->validate([
            'name' => 'required', 
            'email' => 'required|email|unique:users,email,' . $id, 
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Actualización del registro
        $user->update([
            'name' => $request->name, // Cambié 'name' a 'nombre'
            'email' => $request->email,
        ]);

        return response()->json($user);
    }

    // Eliminar registros
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Eliminación del registro
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado con éxito'], 200); // Agregado el código 200
    }
}
