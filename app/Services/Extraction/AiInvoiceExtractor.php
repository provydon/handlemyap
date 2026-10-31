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
                'max_tokens' => 2048,
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
        if (! $invoice->file_path || ! Storage::exists($invoice->file_path)) {
            return null;
        }

        $bytes = Storage::get($invoice->file_path);
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
        You are reading a vendor invoice. Extract EVERYTHING into STRICT JSON with
        exactly these keys (use null when a value is genuinely absent — never guess):
        {
          "vendor_name": string|null,
          "vendor_email": string|null,            // the vendor's contact email
          "invoice_number": string|null,
          "amount": number|null,                   // the grand total / amount due
          "currency": string|null,                 // ISO 4217, e.g. USD, NGN, EUR
          "issue_date": "YYYY-MM-DD"|null,
          "due_date": "YYYY-MM-DD"|null,
          "po_ref": string|null,
          "line_items": [                          // EVERY line on the invoice
            {"description": string, "qty": number|null, "unit_price": number|null, "amount": number|null}
          ]|null,
          "payment": {                             // how the vendor wants to be paid
            "method": "paypal"|"bank_transfer"|"card"|"crypto"|"other"|null,
            "paypal_email": string|null,           // the PayPal address to pay, if any
            "bank": string|null,                   // account / IBAN / routing details as written
            "instructions": string|null            // any free-text pay-to instructions
          },
          "notes": string|null,                    // any other relevant note on the invoice
          "confidence": integer                    // 0-100, how sure you are overall
        }
        If the invoice says to pay via PayPal, put that address in payment.paypal_email.
        Amounts are plain numbers (no currency symbols or thousands separators).
        Return ONLY the JSON object — no prose, no markdown, no code fences.
        TXT;
    }

    private function parse(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                $data['confidence'] = (int) ($data['confidence'] ?? 50);

                // Payouts settle to a PayPal email, so when the invoice names one
                // prefer it as the address we'll actually pay.
                $paypal = $data['payment']['paypal_email'] ?? null;
                if (is_string($paypal) && $paypal !== '') {
                    $data['vendor_email'] = $paypal;
                }

                return array_filter($data, fn ($v) => $v !== null);
            }
        }

        return ['confidence' => 0];
    }
}
