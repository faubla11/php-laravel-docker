<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

        // Ensure user exists in Supabase Auth and get a Supabase session token
        $supabaseSession = null;
        try {
            $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
            $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');
            // Try to get a supabase session (token) via password grant
            $resp = Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer ' . $serviceKey,
                'Content-Type' => 'application/json'
            ])->post($supabaseUrl . '/auth/v1/token?grant_type=password', [
                'email' => $request->email,
                'password' => $request->password,
            ]);

            if ($resp->ok() && isset($resp->json()['access_token'])) {
                $supabaseSession = $resp->json();
            } else {
                // If invalid_credentials, attempt to create or upsert the user via admin API and retry
                $createResp = Http::withHeaders([
                    'apikey' => $serviceKey,
                    'Authorization' => 'Bearer ' . $serviceKey,
                    'Content-Type' => 'application/json'
                ])->post($supabaseUrl . '/auth/v1/admin/users', [
                    'email' => $request->email,
                    'password' => $request->password,
                    'email_confirm' => true,
                    'user_metadata' => ['name' => $user->name ?? '']
                ]);

                // ignore createResp status (user may already exist). Try token endpoint again
                $resp2 = Http::withHeaders([
                    'apikey' => $serviceKey,
                    'Authorization' => 'Bearer ' . $serviceKey,
                    'Content-Type' => 'application/json'
                ])->post($supabaseUrl . '/auth/v1/token?grant_type=password', [
                    'email' => $request->email,
                    'password' => $request->password,
                ]);

                if ($resp2->ok() && isset($resp2->json()['access_token'])) {
                    $supabaseSession = $resp2->json();
                }
            }
        } catch (\Exception $e) {
            \Log::error('Supabase auth sync error: ' . $e->getMessage());
        }
        // Retornar el token en la respuesta (incluyendo sesión de Supabase si está disponible)
        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => $user,
            'supabase_session' => $supabaseSession,
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

    // Try to create user in Supabase Auth via admin API and get a session
    $supabaseSession = null;
    try {
        $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
        $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');
        $createResp = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer ' . $serviceKey,
            'Content-Type' => 'application/json'
        ])->post($supabaseUrl . '/auth/v1/admin/users', [
            'email' => $request->email,
            'password' => $request->password,
            'email_confirm' => true,
            'user_metadata' => ['name' => $request->name ?? '']
        ]);

        // Attempt to get a supabase session via password grant
        $resp = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer ' . $serviceKey,
            'Content-Type' => 'application/json'
        ])->post($supabaseUrl . '/auth/v1/token?grant_type=password', [
            'email' => $request->email,
            'password' => $request->password,
        ]);

        if ($resp->ok() && isset($resp->json()['access_token'])) {
            $supabaseSession = $resp->json();
        }
    } catch (\Exception $e) {
        \Log::error('Supabase create user error: ' . $e->getMessage());
    }

    return response()->json([
        'token' => $token,
        'user' => $user,
        'supabase_session' => $supabaseSession,
    ], 201);
}
}