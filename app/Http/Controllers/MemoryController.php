<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Memory;

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
            $filePath = $request->file('file')->store('memories', 'public');
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
