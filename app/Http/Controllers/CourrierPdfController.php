<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourrierPdfController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', $path);

        if (
            str_contains($path, '..')
            || str_starts_with($path, '/')
            || ! preg_match('#^(courrierarrivee|courrierdepart)/.+\.pdf$#i', $path)
        ) {
            abort(404);
        }

        $racine = realpath(storage_path('app/public'));
        $complet = realpath(storage_path('app/public/'.$path));

        if (
            $racine === false
            || $complet === false
            || ! str_starts_with($complet, $racine.DIRECTORY_SEPARATOR)
            || ! Storage::disk('public')->exists($path)
        ) {
            abort(404);
        }

        return Storage::disk('public')->response($path, basename($complet), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="'.basename($complet).'"',
        ]);
    }
}
