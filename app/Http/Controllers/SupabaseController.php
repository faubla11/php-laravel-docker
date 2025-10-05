<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SupabaseController extends Controller
{
    /**
     * Return a signed upload URL for Supabase Storage using the service role key.
     * The client can then PUT the file directly to that URL.
     * Request body: { name?: string, content_type?: string }
     */
    public function signUpload(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string',
            'content_type' => 'nullable|string',
        ]);

        $ext = null;
        $name = $request->input('name');
        if ($name) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
        }

        $filename = uniqid() . ($ext ? '.' . $ext : '');

        $contentType = $request->input('content_type') ?? 'application/octet-stream';

        // Supabase storage signed URL endpoint
        // POST /storage/v1/object/sign/{bucket}/{path}
        $signEndpoint = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/sign/' . env('SUPABASE_BUCKET') . '/' . $filename;

        $resp = Http::withHeaders([
            'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
            'Content-Type' => 'application/json',
        ])->post($signEndpoint, [
            'expires_in' => 60 * 15, // 15 minutes
            'transform' => false,
        ]);

        if ($resp->failed()) {
            \Log::error('Error generando signed url en Supabase: ' . $resp->body());
            return response()->json(['message' => 'No se pudo generar signed url', 'detail' => $resp->json()], 500);
        }

        $data = $resp->json();

        // public url for the object (may not be accessible for private buckets)
        $publicUrl = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/public/' . env('SUPABASE_BUCKET') . '/' . $filename;

        return response()->json([
            'upload_url' => $data['signed_url'] ?? null,
            'public_url' => $publicUrl,
            'path' => $filename,
            'expires_in' => $data['expires_in'] ?? 900,
        ]);
    }
}
