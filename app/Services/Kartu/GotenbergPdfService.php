<?php

namespace App\Services\Kartu;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GotenbergPdfService
{
    protected string $url;

    public function __construct(?string $url = null)
    {
        $this->url = rtrim($url ?? (string) config('services.gotenberg.url', 'http://gotenberg:3000'), '/');
    }

    /**
     * Get the configured Gotenberg service base URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Check if the Gotenberg service is reachable and healthy.
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->url}/health");

            return $response->successful() && ($response->json('status') === 'up');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Convert an HTML string into PDF binary using Gotenberg Chromium engine.
     *
     * @param  array<string, string>  $options
     */
    public function convertHtmlToPdf(string $html, array $options = []): string
    {
        $endpoint = "{$this->url}/forms/chromium/convert/html";

        $defaultOptions = [
            'paperWidth' => '8.27',   // A4 inches
            'paperHeight' => '11.69', // A4 inches
            'marginTop' => '0.3',     // ~7.6mm margin
            'marginBottom' => '0.3',
            'marginLeft' => '0.3',
            'marginRight' => '0.3',
            'printBackground' => 'true',
            'preferCssPageSize' => 'true',
        ];

        $formFields = array_merge($defaultOptions, $options);

        try {
            $request = Http::timeout(60)->asMultipart();

            // Attach index.html
            $request->attach('files', $html, 'index.html', ['Content-Type' => 'text/html']);

            $response = $request->post($endpoint, $formFields);

            if ($response->failed()) {
                throw new RuntimeException("Gotenberg PDF generation failed with status {$response->status()}: {$response->body()}");
            }

            return $response->body();
        } catch (ConnectionException $e) {
            throw new RuntimeException("Gagal menghubungi layanan PDF Gotenberg di {$this->url}: {$e->getMessage()}", 0, $e);
        } catch (RequestException $e) {
            throw new RuntimeException("Permintaan PDF Gotenberg gagal: {$e->getMessage()}", 0, $e);
        }
    }
}
