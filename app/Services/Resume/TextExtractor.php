<?php

namespace App\Services\Resume;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Raw text extraction layer. Pure PHP (smalot/pdfparser + phpoffice/phpword)
 * so it runs on shared hosting with no binaries or exec().
 */
class TextExtractor
{
    /**
     * @throws RuntimeException when the file cannot be parsed.
     */
    public function extract(string $absolutePath, string $mimeType): string
    {
        $text = match (true) {
            str_contains($mimeType, 'pdf') => $this->fromPdf($absolutePath),
            str_contains($mimeType, 'wordprocessingml') => $this->fromDocx($absolutePath),
            default => throw new RuntimeException("Unsupported resume MIME type: {$mimeType}"),
        };

        return $this->normalize($text);
    }

    private function fromPdf(string $path): string
    {
        $document = (new PdfParser)->parseFile($path);

        return $document->getText();
    }

    private function fromDocx(string $path): string
    {
        $document = IOFactory::load($path);
        $lines = [];

        foreach ($document->getSections() as $section) {
            $this->collectText($section, $lines);
        }

        return implode("\n", $lines);
    }

    /** Walk the PhpWord element tree collecting text lines. */
    private function collectText(AbstractContainer $container, array &$lines): void
    {
        foreach ($container->getElements() as $element) {
            if ($element instanceof Text) {
                $lines[] = $element->getText();
            } elseif ($element instanceof TextRun) {
                $run = '';
                foreach ($element->getElements() as $child) {
                    if ($child instanceof Text) {
                        $run .= $child->getText();
                    }
                }
                $lines[] = $run;
            } elseif ($element instanceof TextBreak) {
                $lines[] = '';
            } elseif ($element instanceof AbstractContainer) {
                $this->collectText($element, $lines);
            }
        }
    }

    private function normalize(string $text): string
    {
        // Unify line endings, strip control chars (keep \n and \t), collapse
        // runs of blank lines, trim trailing space per line.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text) ?? $text;
        $lines = array_map(fn (string $line) => rtrim($line), explode("\n", $text));
        $text = implode("\n", $lines);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
