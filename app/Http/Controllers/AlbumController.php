<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Album;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AlbumController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|min:2',
            'description' => 'nullable|string',
            'category' => 'required|string',
        ]);

        do {
            $code = strtoupper(Str::random(6));
        } while (Album::where('code', $code)->exists());

        $album = Album::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'code' => $code,
        ]);

        $share_url = url("/album/{$album->id}");

        return response()->json([
            'message' => 'Álbum creado exitosamente',
            'album' => [
                'id' => $album->id,
                'title' => $album->title,
                'description' => $album->description,
                'category' => $album->category,
                'code' => $album->code,
                'share_url' => $share_url,
                'retos_count' => 0,
                'veces_resuelto' => 0,
            ],
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $albums = Album::with(['challenges.memories'])
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'albums' => $albums->map(function ($album) {
                // Normalize bg_image to an absolute URL when it's a local storage path
                $bg = $album->bg_image ?? null;
                if ($bg && Str::startsWith($bg, '/')) {
                    $bg = url($bg);
                }

                return [
                    'id' => $album->id,
                    'title' => $album->title,
                    'description' => $album->description,
                    'category' => $album->category,
                    'code' => $album->code,
                    'created_at' => $album->created_at,
                    'retos_count' => $album->challenges->count(),
                    'recuerdos_count' => $album->challenges->flatMap->memories->count(),
                    'bgImage' => $bg,
                ];
            }),
            'stats' => [
                'total_albums' => $albums->count(),
                'total_retos' => $albums->sum(fn($a) => $a->challenges->count()),
                'total_recuerdos' => $albums->sum(fn($a) => $a->challenges->flatMap->memories->count()),
                'total_likes' => 84,
            ]
        ]);
    }

    public function findByCode(Request $request)
    {
        $code = $request->input('code');
        \Log::info("Buscando álbum con código: {$code}");

        $album = Album::with(['challenges.memories'])->where('code', $code)->first();

        if (!$album) {
            return response()->json(['message' => 'Álbum no encontrado'], 404);
        }

        // Normalize bg_image to absolute URL if necessary
        if ($album->bg_image && Str::startsWith($album->bg_image, '/')) {
            $album->bg_image = url($album->bg_image);
        }

        return response()->json($album);
    }

    public function updateBgImage(Request $request, Album $album)
    {
        // Prefer client-supplied URL (when the client uploaded directly to Supabase)
        if ($request->filled('bg_image_url')) {
            $album->bg_image = $request->input('bg_image_url');
            $album->save();

            return response()->json([
                'success' => true,
                'bg_image' => $album->bg_image
            ]);
        }

        // Fallback: allow server-side upload if a file is provided
        if ($request->hasFile('bg_image')) {
            try {
                $file = $request->file('bg_image');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $fileContent = file_get_contents($file->getRealPath());

                $response = Http::withHeaders([
                    'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
                    'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
                    'Content-Type' => $file->getMimeType(),
                ])->put(
                    rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/' . env('SUPABASE_BUCKET') . '/' . $filename,
                    $fileContent
                );

                if ($response->failed()) {
                    \Log::error('Error al subir imagen a Supabase: ' . $response->body());
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudo subir la imagen',
                        'error' => $response->json()
                    ], 500);
                }

                $publicUrl = rtrim(env('SUPABASE_URL'), '/') .
                    '/storage/v1/object/public/' .
                    env('SUPABASE_BUCKET') . '/' . $filename;

                $album->bg_image = $publicUrl;
                $album->save();

                return response()->json([
                    'success' => true,
                    'bg_image' => $album->bg_image
                ]);
            } catch (\Exception $e) {
                \Log::error('Excepción al subir imagen: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error interno',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'No se recibió ningún archivo ni bg_image_url'
        ], 400);
    }
}
