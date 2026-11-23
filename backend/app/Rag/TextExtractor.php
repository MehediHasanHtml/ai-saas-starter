<?php

namespace App\Rag;

use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Turns an uploaded file into plain text, page by page where the format has
 * pages (PDF), so citations can point at a page number.
 */
class TextExtractor
{
    /**
     * @return list<array{page: ?int, text: string}>
     */
    public function extract(string $absolutePath, string $extension): array
    {
        return match (strtolower($extension)) {
            'pdf' => $this->pdf($absolutePath),
            'docx' => [['page' => null, 'text' => $this->docx($absolutePath)]],
            'html', 'htm' => [['page' => null, 'text' => $this->html(file_get_contents($absolutePath))]],
            'json' => [['page' => null, 'text' => $this->json(file_get_contents($absolutePath))]],
            'txt', 'md', 'markdown', 'csv' => [['page' => null, 'text' => $this->utf8(file_get_contents($absolutePath))]],
            default => throw new RuntimeException("Unsupported file type: .{$extension}"),
        };
    }

    private function pdf(string $path): array
    {
        $pdf = (new PdfParser)->parseFile($path);
        $pages = [];

        foreach ($pdf->getPages() as $index => $page) {
            $text = trim($page->getText());
            if ($text !== '') {
                $pages[] = ['page' => $index + 1, 'text' => $this->utf8($text)];
            }
        }

        if ($pages === []) {
            throw new RuntimeException('No extractable text found. Scanned PDFs need OCR before upload.');
        }

        return $pages;
    }

    private function docx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open DOCX file.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Invalid DOCX: word/document.xml missing.');
        }

        // Paragraph and line breaks → newlines, then strip tags.
        $xml = preg_replace(['/<w:p[ >]/', '/<w:br\/>/', '/<w:tab\/>/'], ["\n\n<w:p ", "\n", "\t"], $xml);

        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function html(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr)[^>]*>#i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function json(string $raw): string
    {
        $decoded = json_decode($raw, true);

        return $decoded === null ? $this->utf8($raw) : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function utf8(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        // Postgres rejects NUL bytes in text columns.
        return str_replace("\0", '', $text);
    }
}
