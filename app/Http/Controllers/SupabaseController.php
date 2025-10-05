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

        $headers = [
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer ' . $serviceKey,
            'Content-Type' => 'application/json',
        ];

        // Sanity check: ensure the bucket exists in Supabase Storage. This will
        // help diagnose if the bucket name is wrong or the service key lacks
        // permissions.
        try {
            $bucketEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/bucket/' . $supabaseBucket;
            \Log::info('Checking Supabase bucket exists', ['endpoint' => $bucketEndpoint]);
            $bucketResp = Http::withHeaders($headers)->get($bucketEndpoint);
            if ($bucketResp->failed()) {
                $bstatus = $bucketResp->status();
                $bbody = $bucketResp->body();
                \Log::error('Bucket check failed', ['status' => $bstatus, 'body' => $bbody]);
                return response()->json(['message' => 'Bucket no encontrado o error al consultar Supabase', 'status' => $bstatus, 'detail' => $bbody], 502);
            }
            \Log::info('Bucket exists according to Supabase', ['bucket' => $supabaseBucket]);
        } catch (\Exception $e) {
            \Log::error('Excepción al verificar bucket en Supabase: ' . $e->getMessage());
            // continue to try signing; but surface a helpful message
        }

        // Prepare headers for signing attempts
        $headers = [
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer ' . $serviceKey,
            'Content-Type' => 'application/json',
        ];

        $attempts = [];

        // First: try the body-based signing endpoint (some clusters support POST /storage/v1/object/sign with body)
        try {
            $bodySignEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/sign';
            $bodyPayload = [
                'bucket' => $supabaseBucket,
                'path' => $filename,
                'expiresIn' => 60 * 15,
                'method' => 'PUT',
                'content_type' => $contentType,
            ];

            \Log::info('Calling Supabase sign (body-based)', ['endpoint' => $bodySignEndpoint, 'payload' => $bodyPayload]);
            $bodyResp = Http::withHeaders($headers)->post($bodySignEndpoint, $bodyPayload);
            $attempts['body'] = ['endpoint' => $bodySignEndpoint, 'status' => $bodyResp->status(), 'body' => $bodyResp->body(), 'headers' => $bodyResp->headers()];
            \Log::info('Supabase sign (body) response', ['status' => $bodyResp->status(), 'body' => $bodyResp->body()]);
            if ($bodyResp->successful()) {
                $data = $bodyResp->json();
                \Log::info('Signed URL generated (body-based)', ['signed_url' => $data['signed_url'] ?? null]);
                $publicUrl = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/public/' . env('SUPABASE_BUCKET') . '/' . $filename;
                return response()->json([
                    'upload_url' => $data['signed_url'] ?? null,
                    'public_url' => $publicUrl,
                    'path' => $filename,
                    'expires_in' => $data['expires_in'] ?? 900,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Excepción en intento de sign endpoint (body-based): ' . $e->getMessage());
            $attempts['body'] = ['endpoint' => $bodySignEndpoint ?? null, 'error' => $e->getMessage()];
        }

        // Second: try the url-based signing endpoint: POST /storage/v1/object/sign/{bucket}/{path}
        try {
            $urlSignEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/sign/' . $supabaseBucket . '/' . $filename;
            $urlPayload = [
                'expiresIn' => 60 * 15,
                'method' => 'PUT',
                'content_type' => $contentType,
            ];

            \Log::info('Calling Supabase sign (url-based)', ['endpoint' => $urlSignEndpoint, 'payload' => $urlPayload]);
            $urlResp = Http::withHeaders($headers)->post($urlSignEndpoint, $urlPayload);
            $attempts['url'] = ['endpoint' => $urlSignEndpoint, 'status' => $urlResp->status(), 'body' => $urlResp->body(), 'headers' => $urlResp->headers()];
            \Log::info('Supabase sign (url) response', ['status' => $urlResp->status(), 'body' => $urlResp->body()]);
            if ($urlResp->successful()) {
                $data = $urlResp->json();
                \Log::info('Signed URL generated (url-based)', ['signed_url' => $data['signed_url'] ?? null]);
                $publicUrl = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/public/' . env('SUPABASE_BUCKET') . '/' . $filename;
                return response()->json([
                    'upload_url' => $data['signed_url'] ?? null,
                    'public_url' => $publicUrl,
                    'path' => $filename,
                    'expires_in' => $data['expires_in'] ?? 900,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Excepción en intento de sign endpoint (url-based): ' . $e->getMessage());
            $attempts['url'] = ['endpoint' => $urlSignEndpoint ?? null, 'error' => $e->getMessage()];
        }

        // Third: try a GET variant (some proxies or older clusters expose a GET-based signing)
        try {
            $getSignEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/sign/' . $supabaseBucket . '/' . $filename;
            $query = http_build_query(['method' => 'PUT', 'expiresIn' => 60 * 15]);
            $getUrl = $getSignEndpoint . '?' . $query;
            \Log::info('Calling Supabase sign (GET-variant)', ['endpoint' => $getUrl]);
            $getResp = Http::withHeaders($headers)->get($getUrl);
            $attempts['get'] = ['endpoint' => $getUrl, 'status' => $getResp->status(), 'body' => $getResp->body(), 'headers' => $getResp->headers()];
            \Log::info('Supabase sign (get) response', ['status' => $getResp->status(), 'body' => $getResp->body()]);
            if ($getResp->successful()) {
                $data = $getResp->json();
                $publicUrl = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/public/' . env('SUPABASE_BUCKET') . '/' . $filename;
                return response()->json([
                    'upload_url' => $data['signed_url'] ?? null,
                    'public_url' => $publicUrl,
                    'path' => $filename,
                    'expires_in' => $data['expires_in'] ?? 900,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Excepción en intento de sign endpoint (get-variant): ' . $e->getMessage());
            $attempts['get'] = ['endpoint' => $getSignEndpoint ?? null, 'error' => $e->getMessage()];
        }

        // If we reached here both attempts failed. Log attempts and return detailed info for debugging.
        \Log::error('Both signing attempts failed', ['attempts' => $attempts]);
        // Normalize response body snippets for client consumption
        $detail = $attempts;
        return response()->json(['message' => 'No se pudo generar signed url', 'status' => 400, 'detail' => $detail], 502);

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
