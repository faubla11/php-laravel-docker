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
        $file = $request->file('file');
        $fileName = uniqid() . '_' . $file->getClientOriginalName();

        $supabase = new \Supabase\Storage\StorageClient(
            env('SUPABASE_URL'),
            env('SUPABASE_KEY')
        );

        $bucket = env('SUPABASE_BUCKET', 'memories'); // bucket memories
        $fileStream = fopen($file->getRealPath(), 'r');

        $supabase->from($bucket)->upload($fileName, $fileStream, [
            'contentType' => $file->getMimeType()
        ]);

        // URL pública
        $filePath = env('SUPABASE_URL') . "/storage/v1/object/public/{$bucket}/{$fileName}";
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
