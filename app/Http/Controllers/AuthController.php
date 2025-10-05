<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validación de los datos de entrada
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Buscar el usuario por correo electrónico
        $user = User::where('email', $request->email)->first();

        // Verificar si el usuario existe y la contraseña es correcta
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // Generar un token de acceso para el usuario
        $token = $user->createToken('TokenAcceso')->plainTextToken;

        // Retornar el token en la respuesta
        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revocar el token del usuario autenticado
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Sesión cerrada exitosamente'], 200);
    }

    public function profile(Request $request)
    {
        // Obtener el usuario autenticado
        $user = $request->user();

        // Retornar la información del usuario
        return response()->json([
            'success' => true,
            'user' => $user,
        ], 200);
    }

    public function register(Request $request)
{
    // Validación de datos
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8',
    ]);

    // Crear el usuario
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    // Generar el token
    $token = $user->createToken('TokenAcceso')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user,
    ], 201);
}
}