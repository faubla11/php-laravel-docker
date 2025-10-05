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
        // Log caller info for debugging (avoid logging secrets)
        try {
            $caller = $request->user()?->id ?? $request->ip();
        } catch (\Exception $_) {
            $caller = $request->ip();
        }
        \Log::info('signUpload requested', ['caller' => $caller, 'name' => $request->input('name'), 'content_type' => $request->input('content_type')]);

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

        // Validate environment
        $supabaseUrl = env('SUPABASE_URL');
        $supabaseBucket = env('SUPABASE_BUCKET');
        $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');

        if (!$supabaseUrl || !$supabaseBucket || !$serviceKey) {
            \Log::error('Faltan variables de entorno SUPABASE_URL/SUPABASE_BUCKET/SUPABASE_SERVICE_ROLE_KEY');
            return response()->json(['message' => 'Configuración de Supabase incompleta en el servidor'], 500);
        }

        // Supabase storage signed URL endpoint
        // POST /storage/v1/object/sign/{bucket}/{path}
        $signEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/sign/' . $supabaseBucket . '/' . $filename;

        $headers = [
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer ' . $serviceKey,
            'Content-Type' => 'application/json',
        ];

        try {
            // Request a signed URL specifically for PUT (upload). Include content type
            // so the signed URL is generated for an upload of that MIME type.
            $resp = Http::withHeaders($headers)->post($signEndpoint, [
                'expiresIn' => 60 * 15, // 15 minutes
                'method' => 'PUT',
                'content_type' => $contentType,
            ]);
        } catch (\Exception $e) {
            \Log::error('Excepción al llamar a Supabase sign endpoint (url-based): ' . $e->getMessage());
            // Try alternative form: POST /storage/v1/object/sign with body { bucket, path }
            try {
                $altEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/sign';
                // Use body-based signing and request method PUT as well.
                $resp = Http::withHeaders($headers)->post($altEndpoint, [
                    'bucket' => $supabaseBucket,
                    'path' => $filename,
                    'expiresIn' => 60 * 15,
                    'method' => 'PUT',
                    'content_type' => $contentType,
                ]);
                \Log::info('Intento alternativo de sign-upload (body-based) ejecutado', ['endpoint' => $altEndpoint]);
            } catch (\Exception $e2) {
                \Log::error('Excepción en intento alternativo sign endpoint: ' . $e2->getMessage());
                return response()->json(['message' => 'Error llamando a Supabase', 'error' => $e2->getMessage()], 500);
            }
        }

        if ($resp->failed()) {
            $status = $resp->status();
            $body = $resp->body();
            \Log::error("Error generando signed url en Supabase: status={$status} body={$body}");
            $json = null;
            try { $json = $resp->json(); } catch (\Exception $_) { $json = null; }
            return response()->json(['message' => 'No se pudo generar signed url', 'status' => $status, 'detail' => $json, 'raw' => $body], 502);
        }

        $data = $resp->json();
    \Log::info('Signed URL generated', ['signed_url' => $data['signed_url'] ?? null, 'expires_in' => $data['expires_in'] ?? null]);

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
