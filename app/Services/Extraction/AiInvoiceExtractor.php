<?php

namespace App\Services\Extraction;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The real extractor: hands the document (PDF/image/text) to Claude and asks
 * for strict JSON. Vision-capable, so it reads scans and arbitrary layouts the
 * {@see StubExtractor} can't. Falls back to low confidence on any error so the
 * invoice simply lands in the human review queue rather than blowing up.
 *
 * Uses the Anthropic Messages API directly over HTTP (no extra package), so the
 * app stays self-contained. Configure ANTHROPIC_API_KEY to enable it.
 */
class AiInvoiceExtractor implements InvoiceExtractor
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function extract(Invoice $invoice): array
    {
        try {
            $block = $this->documentBlock($invoice);
            if ($block === null) {
                return ['confidence' => 0];
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'messages' => [[
                    'role' => 'user',
                    'content' => [$block, ['type' => 'text', 'text' => $this->prompt()]],
                ]],
            ]);

            if ($response->failed()) {
                Log::warning('handlemyap AI extraction failed', ['status' => $response->status()]);

                return ['confidence' => 0];
            }

            $text = $response->json('content.0.text', '');

            return $this->parse($text);
        } catch (\Throwable $e) {
            Log::warning('handlemyap AI extraction error', ['error' => $e->getMessage()]);

            return ['confidence' => 0];
        }
    }

    /** @return array<string, mixed>|null */
    private function documentBlock(Invoice $invoice): ?array
    {
        $disk = Storage::disk(config('handlemyap.uploads_disk'));
        if (! $invoice->file_path || ! $disk->exists($invoice->file_path)) {
            return null;
        }

        $bytes = $disk->get($invoice->file_path);
        $ext = strtolower(pathinfo($invoice->original_filename ?? $invoice->file_path, PATHINFO_EXTENSION));
        $b64 = base64_encode($bytes);

        return match ($ext) {
            'pdf' => [
                'type' => 'document',
                'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64],
            ],
            'png', 'jpg', 'jpeg', 'gif', 'webp' => [
                'type' => 'image',
                'source' => ['type' => 'base64', 'media_type' => 'image/'.($ext === 'jpg' ? 'jpeg' : $ext), 'data' => $b64],
            ],
            default => ['type' => 'text', 'text' => $bytes], // txt/csv/etc.
        };
    }

    private function prompt(): string
    {
        return <<<'TXT'
        Extract this invoice into STRICT JSON with exactly these keys (use null when absent):
        {"vendor_name": string|null, "vendor_email": string|null, "invoice_number": string|null,
         "amount": number|null, "currency": string|null (ISO 4217), "issue_date": "YYYY-MM-DD"|null,
         "due_date": "YYYY-MM-DD"|null, "po_ref": string|null,
         "line_items": [{"description": string, "qty": number, "unit_price": number}]|null,
         "confidence": integer 0-100 (how sure you are overall)}
        Return ONLY the JSON object, no prose, no code fences.
        TXT;
    }

    private function parse(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                $data['confidence'] = (int) ($data['confidence'] ?? 50);

                return array_filter($data, fn ($v) => $v !== null);
            }
        }

        return ['confidence' => 0];
    }
}
