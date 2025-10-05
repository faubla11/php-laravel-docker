<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\SupabaseController;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user-profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'index']);

    // Rutas protegidas para usuarios
    Route::get('/user', [UsuarioController::class,'index']);          // Obtener total de usuarios
    Route::get('/user/{id}', [UsuarioController::class,'registroUser']); // Buscar usuario por ID
    Route::post('/user', [UsuarioController::class,'store']);         // Registrar un nuevo usuario
    Route::put('/user/{id}', [UsuarioController::class, 'update']);   // Actualizar usuario por ID
    Route::delete('/user/{id}', [UsuarioController::class, 'destroy']); // Eliminar usuario por 

    //Rutas para albumes y challenges y memories
    Route::post('/albums', [AlbumController::class, 'store']);
    Route::post('/albums/{album}/challenges', [ChallengeController::class, 'store']);
    Route::post('/challenges/{challenge}/memories', [MemoryController::class, 'store']);
    Route::get('/albums/{album}/challenges', [ChallengeController::class, 'index']);
    Route::get('/challenges/{challenge}', [ChallengeController::class, 'show']);
    Route::put('/challenges/{challenge}', [ChallengeController::class, 'update']);
    Route::delete('/challenges/{challenge}', [ChallengeController::class, 'destroy']);

    Route::get('/albums', [AlbumController::class, 'index']);
    Route::post('/albums/{album}/bg-image', [AlbumController::class, 'updateBgImage']);
    // Endpoint para solicitar signed upload URL para Supabase Storage
    Route::post('/supabase/sign-upload', [SupabaseController::class, 'signUpload']);
});
    Route::post('/albums/find-by-code', [AlbumController::class, 'findByCode']);
    Route::post('/challenges/{challenge}/validate', [ChallengeController::class, 'validateAnswer']);
