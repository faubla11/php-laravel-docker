<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Memory;
use Illuminate\Support\Facades\Http;

class MemoryController extends Controller
{
    public function store(Request $request, $challengeId)
    {
        $request->validate([
            'type' => 'required|in:photo,video,note',
            'file' => 'nullable|file',
            'note' => 'nullable|string',
        ]);

        $filePath = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $fileContent = file_get_contents($file->getRealPath());

            // Subir a Supabase Storage
            $response = Http::withHeaders([
                'apikey' => env('SUPABASE_KEY'),
                'Authorization' => 'Bearer ' . env('SUPABASE_KEY'),
                'Content-Type' => $file->getMimeType(),
            ])->put(
                rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/' . env('SUPABASE_BUCKET') . '/' . $filename,
                $fileContent
            );

            if ($response->failed()) {
                \Log::error('Error al subir archivo a Supabase: ' . $response->body());
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo subir el archivo'
                ], 500);
            }

            // Generar URL pública
            $filePath = rtrim(env('SUPABASE_URL'), '/') .
                '/storage/v1/object/public/' .
                env('SUPABASE_BUCKET') . '/' . $filename;
        }

        // Guardar en BD
        $memory = Memory::create([
            'challenge_id' => $challengeId,
            'type' => $request->type,
            'file_path' => $filePath,
            'note' => $request->note,
        ]);

        return response()->json([
            'message' => 'Recuerdo guardado exitosamente',
            'memory' => $memory,
        ], 201);
    }
}
