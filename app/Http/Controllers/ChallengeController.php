<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Challenge;
use Illuminate\Support\Facades\Http;
class ChallengeController extends Controller
{
    public function store(Request $request, $albumId)
    {
        $request->validate([
            'question' => 'required|string|min:5',
            'answer_type' => 'required|in:text,date,exact',
            'answer' => 'required|string',
        ]);

        $challenge = Challenge::create([
            'album_id' => $albumId,
            'question' => $request->question,
            'answer_type' => $request->answer_type,
            'answer' => $request->answer,
        ]);

        return response()->json([
            'message' => 'Reto creado exitosamente',
            'challenge' => $challenge,
        ], 201);
    }

    public function index($albumId)
    {
        $album = \App\Models\Album::with([
            'challenges.memories'
        ])->findOrFail($albumId);

        $totalRetos = $album->challenges->count();
        $totalRecuerdos = $album->challenges->flatMap->memories->count();
        $totalTexto = $album->challenges->where('answer_type', 'text')->count();
        $totalFecha = $album->challenges->where('answer_type', 'date')->count();
        $totalFrase = $album->challenges->where('answer_type', 'exact')->count();

        return response()->json([
            'album' => [
                'id' => $album->id,
                'title' => $album->title,
                'description' => $album->description,
                'category' => $album->category,
                'code' => $album->code,
                'challenges' => $album->challenges->map(function($challenge) {
                    return [
                        'id' => $challenge->id,
                        'question' => $challenge->question,
                        'answer_type' => $challenge->answer_type,
                        'created_at' => $challenge->created_at,
                        'memories' => $challenge->memories->map(function($memory) {
                            return [
                                'id' => $memory->id,
                                'type' => $memory->type,
                                'file_path' => $memory->file_path,
                                'note' => $memory->note,
                            ];
                        }),
                    ];
                }),
                'summary' => [
                    'total_retos' => $totalRetos,
                    'total_recuerdos' => $totalRecuerdos,
                    'total_texto' => $totalTexto,
                    'total_fecha' => $totalFecha,
                    'total_frase' => $totalFrase,
                ]
            ]
        ]);
    }

    public function show($id)
    {
        $challenge = \App\Models\Challenge::with('memories')->findOrFail($id);
        return response()->json($challenge);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'question' => 'required|string|min:5',
            'answer_type' => 'required|in:text,date,exact',
            'answer' => 'required|string',
        ]);

        $challenge = \App\Models\Challenge::findOrFail($id);
        $challenge->update($request->only(['question', 'answer_type', 'answer']));
        return response()->json(['message' => 'Reto actualizado', 'challenge' => $challenge]);
    }

    public function destroy($id)
    {
        $challenge = \App\Models\Challenge::findOrFail($id);
        $challenge->delete();
        return response()->json(['message' => 'Reto eliminado']);
    }

    public function validateAnswer(Request $request, $challengeId)
    {
        try {
            $challenge = \App\Models\Challenge::with('memories')->findOrFail($challengeId);
            $answer = $request->input('answer');

            $isCorrect = false;
            if ($challenge->answer_type === 'exact' || $challenge->answer_type === 'text') {
                $isCorrect = strtolower(trim($challenge->answer)) === strtolower(trim((string)$answer));
            } elseif ($challenge->answer_type === 'date') {
                $isCorrect = $challenge->answer === $answer;
            }

            if ($isCorrect) {
                // Si la respuesta es correcta, verificar si este es el ultimo reto del album
                $album = $challenge->album()->with('challenges')->first();

                if ($album) {
                    $totalRetos = $album->challenges->count();

                    // Contar recuerdos del album
                    $totalRecuerdos = $album->challenges->flatMap->memories->count();

                    // Si el album ya tiene recuerdos iguales al total de retos, lo marcamos como completado para el usuario
                    if ($totalRecuerdos >= $totalRetos) {
                        try {
                            $user = $request->user();
                            if ($user) {
                                \App\Models\CompletedAlbum::updateOrCreate(
                                    ['user_id' => $user->id, 'album_id' => $album->id],
                                    ['completed_at' => now()]
                                );
                            } else {
                                \Log::info('Usuario no autenticado al intentar marcar album como completado');
                            }
                        } catch (\Exception $e) {
                            \Log::warning('No se pudo marcar album como completado: ' . $e->getMessage());
                        }
                    }
                } else {
                    \Log::warning('Album relacionado no encontrado para challenge id ' . $challengeId);
                }

                return response()->json([
                    'correct' => true,
                    'memories' => $challenge->memories,
                    'challenge' => $challenge
                ]);
            } else {
                return response()->json(['correct' => false], 200);
            }
        } catch (\Throwable $e) {
            \Log::error('validateAnswer exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Error interno', 'error' => $e->getMessage()], 500);
        }
    }
}
