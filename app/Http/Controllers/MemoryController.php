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
            'file_url' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $filePath = null;

        // If the client already uploaded the file directly to Supabase, it should
        // send `file_url` (public or signed). In that case we don't re-upload.
        if ($request->filled('file_url')) {
            $filePath = $request->input('file_url');
        } elseif ($request->hasFile('file')) {
            try {
                $file = $request->file('file');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $fileContent = file_get_contents($file->getRealPath());

                // Use the service role key on the backend when uploading server-side
                // (do not expose this key to clients).
                $response = Http::withHeaders([
                    'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
                    'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
                    'Content-Type' => $file->getMimeType(),
                ])->put(
                    rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/' . env('SUPABASE_BUCKET') . '/' . $filename,
                    $fileContent
                );

                if ($response->failed()) {
                    \Log::error('Error al subir archivo a Supabase: ' . $response->body());
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudo subir el archivo',
                        'error' => $response->json()
                    ], 500);
                }

                $filePath = rtrim(env('SUPABASE_URL'), '/') .
                    '/storage/v1/object/public/' .
                    env('SUPABASE_BUCKET') . '/' . $filename;
            } catch (\Exception $e) {
                \Log::error('Excepción al subir archivo: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error interno',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

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
