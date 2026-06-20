<?php

namespace App\Http\Controllers;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PersonalizationFull;
use App\Models\Personalization;
use Illuminate\Support\Facades\Storage;

use Illuminate\Http\Request;

class OwnerController extends Controller
{
    /** Raster types we accept and store verbatim (content-sniffed mime → extension). */
    private const RASTER = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /** Read the organizer's current personalization (for the web_app branding page). */
    public function getPersonalization(): JsonResource
    {
        $personalization = Personalization::firstOrNew(['owner' => $this->getOwner()]);

        return new PersonalizationFull($personalization);
    }

    /**
     * Update the organizer's brand accent colour.
     *
     * Logos are NOT set here — they only enter through uploadLogo(), which stores
     * them on the engine's own public disk and writes a same-origin photo_url. That
     * keeps voter-facing pages from ever loading a logo off a third-party host (an
     * IP-leak on a secret ballot), so this endpoint deliberately ignores photo_url.
     */
    public function updatePersonalization(Request $request): JsonResponse|JsonResource
    {
        $params = $request->all();
        $settings = [
            // Optional ballot accent color. Strict 6-digit hex only — it is emitted into
            // an inline style on the ballot, so nothing but #rrggbb may pass. /D anchors
            // $ to the true end (no trailing newline).
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/D'],
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $owner = $this->getOwner();

        // Only touch brand_color when the caller actually sent it, so this never
        // wipes a previously-uploaded logo.
        $values = [];
        if (array_key_exists('brand_color', $params)) {
            $values['brand_color'] = $params['brand_color'];
        }

        $personalization = Personalization::updateOrCreate(['owner' => $owner], $values);

        return new PersonalizationFull($personalization);
    }

    /**
     * Accept a logo file upload, store it on the engine's own public disk, and set
     * the organizer's photo_url to the resulting same-origin URL.
     *
     * Type is decided by sniffed content, not the filename. Raster (png/jpg/webp) is
     * stored verbatim; SVG is run through enshrined/svg-sanitize (scripts, event
     * handlers and remote references stripped) and the cleaned markup is stored — we
     * never rasterize (no Imagick in the image, and conversion is its own XXE/SSRF
     * surface). Anything else is rejected.
     */
    public function uploadLogo(Request $request): JsonResponse|JsonResource
    {
        $errors = $this->findErrors($request->all(), [
            'logo' => ['required', 'file', 'max:2048'], // 2 MB
        ]);
        if ($errors) {
            return $errors;
        }

        $file = $request->file('logo');
        $raw = (string) file_get_contents($file->getRealPath());
        $mime = (string) $file->getMimeType();

        if (isset(self::RASTER[$mime])) {
            $ext = self::RASTER[$mime];
            $contents = $raw;
        } elseif ($this->looksLikeSvg($mime, $raw)) {
            $sanitizer = new Sanitizer();
            // Strip <script>, on* handlers AND any external/xlink references so the
            // stored logo can never phone home from a voter's browser.
            $sanitizer->removeRemoteReferences(true);
            $clean = $sanitizer->sanitize($raw);
            if (!is_string($clean) || trim($clean) === '') {
                return $this->basicResponse(422, ['error' => 'The logo could not be processed.']);
            }
            $contents = $clean;
            $ext = 'svg';
        } else {
            return $this->basicResponse(422, ['error' => 'Unsupported image type. Use PNG, JPG, WEBP or SVG.']);
        }

        $owner = $this->getOwner();
        $base = 'logos/' . hash('sha256', $owner);

        // Drop any prior logo for this owner (a previous upload may have a different
        // extension) so we never accumulate orphaned files or serve a stale type.
        foreach (array_merge(['svg'], array_values(self::RASTER)) as $priorExt) {
            Storage::disk('public')->delete("$base.$priorExt");
        }

        $path = "$base.$ext";
        Storage::disk('public')->put($path, $contents);

        // Same-origin engine URL (+ content-hash cache-bust so a re-upload to the
        // same path is picked up by the ballot/results views and the invite email).
        $url = Storage::disk('public')->url($path) . '?v=' . substr(hash('sha256', $contents), 0, 8);

        $personalization = Personalization::updateOrCreate(['owner' => $owner], ['photo_url' => $url]);

        return new PersonalizationFull($personalization);
    }

    /** SVG by sniffed mime, or a permissive text mime whose body actually contains an <svg> root. */
    private function looksLikeSvg(string $mime, string $raw): bool
    {
        if ($mime === 'image/svg+xml' || $mime === 'image/svg') {
            return true;
        }

        // finfo often reports SVG as text/* or application/xml; require a real <svg root.
        $textual = ['text/plain', 'text/xml', 'text/html', 'application/xml'];

        return in_array($mime, $textual, true) && stripos($raw, '<svg') !== false;
    }
}
