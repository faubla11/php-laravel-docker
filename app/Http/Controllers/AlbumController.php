<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Album;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AlbumController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|min:2',
            'description' => 'nullable|string',
            'category' => 'required|string',
        ]);

        // Genera un código único de 6 caracteres
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

        $albums = \App\Models\Album::with(['challenges.memories'])
            ->where('user_id', $user->id)
            ->get();

        // Estadísticas generales
        $total_albums = $albums->count();
        $total_retos = $albums->sum(function($a) {
            return $a->challenges->count();
        });
        $total_recuerdos = $albums->sum(function($a) {
            return $a->challenges->flatMap->memories->count();
        });
        $total_likes = 84; // Simulado, ajusta según tu modelo

        return response()->json([
            'albums' => $albums->map(function($album) {
                return [
                    'id' => $album->id,
                    'title' => $album->title,
                    'description' => $album->description,
                    'category' => $album->category,
                    'code' => $album->code,
                    'created_at' => $album->created_at,
                    'retos_count' => $album->challenges->count(),
                    'recuerdos_count' => $album->challenges->flatMap->memories->count(),
                    'bgImage' => $album->bg_image ?? null, // <-- este campo
                ];
            }),
            'stats' => [
                'total_albums' => $total_albums,
                'total_retos' => $total_retos,
                'total_recuerdos' => $total_recuerdos,
                'total_likes' => $total_likes,
            ]
        ]);
    }

    public function findByCode(Request $request)
    {
        $code = $request->input('code');
        
        \Log::info('Buscando álbum con código: ' . $code);
        
        $album = \App\Models\Album::with(['challenges.memories'])->where('code', $code)->first();

        if (!$album) {
            return response()->json(['message' => 'Álbum no encontrado'], 404);
        }

        return response()->json($album);
    }

    public function updateBgImage(Request $request, Album $album)
    {
        if ($request->hasFile('bg_image')) {
            $path = $request->file('bg_image')->store('albums', 'public');
            $album->bg_image = '/storage/' . $path;
            $album->save();
            return response()->json(['success' => true, 'bg_image' => $album->bg_image]);
        }
        return response()->json(['success' => false], 400);
    }
}
