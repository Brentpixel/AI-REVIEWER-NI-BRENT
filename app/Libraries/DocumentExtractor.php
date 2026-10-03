<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Files\File;

/**
 * DocumentExtractor
 *
 * Extracts plain text from PDF, DOCX, and TXT files.
 * Uploaded files are processed from a temporary path
 * and are never permanently stored.
 */
class DocumentExtractor
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_MIMES  = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'txt'];

    /**
     * Validate and extract text from an uploaded file object.
     *
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file
     * @return string  Extracted plain text
     * @throws \InvalidArgumentException on validation failure
     * @throws \RuntimeException         on extraction failure
     */
    public function extract(\CodeIgniter\HTTP\Files\UploadedFile $file): string
    {
        $this->validate($file);

        $tmpPath  = $file->getTempName();
        $ext      = strtolower($file->getClientExtension());

        try {
            return match ($ext) {
                'pdf'  => $this->extractPdf($tmpPath),
                'docx' => $this->extractDocx($tmpPath),
                'txt'  => $this->extractTxt($tmpPath),
                default => throw new \InvalidArgumentException('Unsupported file type.'),
            };
        } finally {
            // Ensure temp file is removed even on error
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Validate the uploaded file for size, extension, and MIME type.
     */
    private function validate(\CodeIgniter\HTTP\Files\UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('File upload failed or the file is corrupted. Error: ' . $file->getErrorString());
        }

        if ($file->getSizeByUnit('b') > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException('File exceeds the 10 MB maximum size limit.');
        }

        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Unsupported file type. Allowed: PDF, DOCX, TXT.');
        }

        $mime = $file->getMimeType();
        // Allow text/plain variants
        if (str_starts_with($mime, 'text/') && $ext === 'txt') {
            return;
        }
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('File MIME type does not match its extension. Allowed types: PDF, DOCX, TXT.');
        }
    }

    /**
     * Extract text from a PDF file using smalot/pdfparser.
     */
    private function extractPdf(string $path): string
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new \RuntimeException('PDF parsing library not installed. Run: php composer.phar require smalot/pdfparser');
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($path);
            $text   = $pdf->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to parse PDF file: ' . $e->getMessage());
        }

        if (empty(trim($text))) {
            throw new \InvalidArgumentException('The PDF file appears to be empty or contains no extractable text (it may be image-only).');
        }

        return $text;
    }

    /**
     * Extract text from a DOCX file using phpoffice/phpword.
     */
    private function extractDocx(string $path): string
    {
        if (! class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            throw new \RuntimeException('DOCX parsing library not installed. Run: php composer.phar require phpoffice/phpword');
        }

        try {
            $phpWord  = \PhpOffice\PhpWord\IOFactory::load($path);
            $sections = $phpWord->getSections();
            $text     = '';

            foreach ($sections as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->extractWordElement($element);
                }
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to parse DOCX file: ' . $e->getMessage());
        }

        if (empty(trim($text))) {
            throw new \InvalidArgumentException('The DOCX file appears to be empty or contains no extractable text.');
        }

        return $text;
    }

    /**
     * Recursively extract text from a PhpWord element.
     */
    private function extractWordElement(mixed $element): string
    {
        $text = '';

        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun
            || $element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractWordElement($child);
            }
            $text .= "\n";
        } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            $text .= $element->getText();
        } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellEl) {
                        $text .= $this->extractWordElement($cellEl) . "\t";
                    }
                }
                $text .= "\n";
            }
        } elseif (method_exists($element, 'getText')) {
            $text .= $element->getText();
        }

        return $text;
    }

    /**
     * Extract text from a plain TXT file.
     */
    private function extractTxt(string $path): string
    {
        $text = file_get_contents($path);

        if ($text === false) {
            throw new \RuntimeException('Failed to read the text file.');
        }

        // Detect and convert encoding
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
        }

        if (empty(trim($text))) {
            throw new \InvalidArgumentException('The text file is empty.');
        }

        return $text;
    }
}
