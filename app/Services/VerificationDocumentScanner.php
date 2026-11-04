<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class VerificationDocumentScanner
{
    private const MIN_OCR_CONFIDENCE = 50;
    private const MAX_LOW_CONFIDENCE_RATIO = 0.55;
    private const MIN_OCR_WORDS = 4;

    public function scan(
        string $absolutePath,
        ?string $mimeType = null
    ): array {
        try {
            $extension = strtolower(
                pathinfo(
                    $absolutePath,
                    PATHINFO_EXTENSION
                )
            );

            if (
                $extension === 'pdf' ||
                $mimeType === 'application/pdf'
            ) {
                return $this->scanPdf(
                    $absolutePath
                );
            }

            return $this->scanImage(
                $absolutePath
            );
        } catch (Throwable $e) {
            report($e);

            return [
                'expiry_date' => null,
                'expiry_date_raw' => null,
                'expiry_detection_status' => 'failed',
                'expiry_detection_source' => null,
                'expiry_scanned_at' => now(),
            ];
        }
    }

    private function scanPdf(
        string $absolutePath
    ): array {
        $text = '';

        try {
            $text = $this->extractPdfText(
                $absolutePath
            );
        } catch (Throwable $e) {
            report($e);
        }

        if ($this->hasUsefulText($text)) {
            return $this->buildResult(
                $text,
                'native_pdf'
            );
        }

        $analysis = $this->ocrPdf(
            $absolutePath
        );

        if (
            $this->isUnreadable(
                $analysis
            )
        ) {
            return $this->buildUnreadableResult(
                'ocr_pdf'
            );
        }

        return $this->buildResult(
            $analysis['text'],
            'ocr_pdf'
        );
    }

    private function scanImage(
        string $absolutePath
    ): array {
        $analysis = $this->ocrImage(
            $absolutePath
        );

        if (
            $this->isUnreadable(
                $analysis
            )
        ) {
            return $this->buildUnreadableResult(
                'ocr_image'
            );
        }

        return $this->buildResult(
            $analysis['text'],
            'ocr_image'
        );
    }

    private function extractPdfText(
        string $absolutePath
    ): string {
        $process = new Process([
            config(
                'document_scanner.pdftotext'
            ),
            '-layout',
            $absolutePath,
            '-',
        ]);

        $process->setTimeout(
            config(
                'document_scanner.timeout'
            )
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                trim(
                    $process->getErrorOutput()
                ) ?: 'PDF text extraction failed.'
            );
        }

        return trim(
            $process->getOutput()
        );
    }

    private function ocrPdf(
        string $absolutePath
    ): array {
        $directory =
            storage_path(
                'app/tmp/document-ocr/' .
                Str::uuid()
            );

        File::ensureDirectoryExists(
            $directory
        );

        try {
            $prefix =
                $directory .
                DIRECTORY_SEPARATOR .
                'page';

            $process = new Process([
                config(
                    'document_scanner.pdftoppm'
                ),
                '-f',
                '1',
                '-l',
                (string) config(
                    'document_scanner.max_pdf_pages'
                ),
                '-r',
                '200',
                '-png',
                $absolutePath,
                $prefix,
            ]);

            $process->setTimeout(
                config(
                    'document_scanner.timeout'
                )
            );

            $process->run();

            if (!$process->isSuccessful()) {
                throw new RuntimeException(
                    trim(
                        $process->getErrorOutput()
                    ) ?: 'PDF image conversion failed.'
                );
            }

            $pages = glob(
                $prefix . '-*.png'
            ) ?: [];

            natsort($pages);

            $texts = [];
            $totalConfidence = 0;
            $totalWords = 0;
            $lowConfidenceWords = 0;

            foreach ($pages as $page) {
                $analysis =
                    $this->ocrImage(
                        $page
                    );

                if (
                    filled(
                        $analysis['text']
                    )
                ) {
                    $texts[] =
                        $analysis['text'];
                }

                if (
                    $analysis['confidence'] !==
                    null &&
                    $analysis['word_count'] > 0
                ) {
                    $totalConfidence +=
                        $analysis['confidence'] *
                        $analysis['word_count'];
                }

                $totalWords +=
                    $analysis['word_count'];

                $lowConfidenceWords +=
                    $analysis[
                        'low_confidence_words'
                    ];
            }

            return [
                'text' => trim(
                    implode(
                        PHP_EOL,
                        $texts
                    )
                ),

                'confidence' =>
                    $totalWords > 0
                        ? $totalConfidence /
                            $totalWords
                        : null,

                'word_count' =>
                    $totalWords,

                'low_confidence_ratio' =>
                    $totalWords > 0
                        ? $lowConfidenceWords /
                            $totalWords
                        : 1,
            ];
        } finally {
            File::deleteDirectory(
                $directory
            );
        }
    }

    private function ocrImage(
        string $absolutePath
    ): array {
        $process = new Process([
            config(
                'document_scanner.tesseract'
            ),
            $absolutePath,
            'stdout',
            '-l',
            config(
                'document_scanner.language'
            ),
            'tsv',
        ]);

        $process->setTimeout(
            config(
                'document_scanner.timeout'
            )
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                trim(
                    $process->getErrorOutput()
                ) ?: 'OCR processing failed.'
            );
        }

        return $this->parseTsv(
            $process->getOutput()
        );
    }

    private function parseTsv(
        string $tsv
    ): array {
        $rows = preg_split(
            '/\R/u',
            trim($tsv)
        ) ?: [];

        $lines = [];
        $confidenceTotal = 0;
        $wordCount = 0;
        $lowConfidenceWords = 0;

        foreach (
            array_slice($rows, 1)
            as $row
        ) {
            if (!filled($row)) {
                continue;
            }

            $columns =
                str_getcsv(
                    $row,
                    "\t"
                );

            if (
                count($columns) <
                12
            ) {
                continue;
            }

            if (
                (int) $columns[0] !==
                5
            ) {
                continue;
            }

            $text = trim(
                $columns[11]
            );

            $confidence =
                is_numeric(
                    $columns[10]
                )
                    ? (float) $columns[10]
                    : -1;

            if (
                $text === '' ||
                $confidence < 0
            ) {
                continue;
            }

            $lineKey =
                $columns[1] . '-' .
                $columns[2] . '-' .
                $columns[3] . '-' .
                $columns[4];

            $lines[$lineKey][] =
                $text;

            $confidenceTotal +=
                $confidence;

            $wordCount++;

            if (
                $confidence <
                self::MIN_OCR_CONFIDENCE
            ) {
                $lowConfidenceWords++;
            }
        }

        $textLines = [];

        foreach (
            $lines
            as $words
        ) {
            $textLines[] =
                implode(
                    ' ',
                    $words
                );
        }

        return [
            'text' =>
                trim(
                    implode(
                        PHP_EOL,
                        $textLines
                    )
                ),

            'confidence' =>
                $wordCount > 0
                    ? $confidenceTotal /
                        $wordCount
                    : null,

            'word_count' =>
                $wordCount,

            'low_confidence_words' =>
                $lowConfidenceWords,

            'low_confidence_ratio' =>
                $wordCount > 0
                    ? $lowConfidenceWords /
                        $wordCount
                    : 1,
        ];
    }

    private function isUnreadable(
        array $analysis
    ): bool {
        if (
            !filled(
                $analysis['text']
            )
        ) {
            return true;
        }

        if (
            $analysis['word_count'] <
            self::MIN_OCR_WORDS
        ) {
            return true;
        }

        if (
            $analysis['confidence'] ===
            null
        ) {
            return true;
        }

        if (
            $analysis['confidence'] <
            self::MIN_OCR_CONFIDENCE
        ) {
            return true;
        }

        if (
            $analysis[
                'low_confidence_ratio'
            ] >
            self::MAX_LOW_CONFIDENCE_RATIO
        ) {
            return true;
        }

        return false;
    }

    private function buildUnreadableResult(
        string $source
    ): array {
        return [
            'expiry_date' => null,
            'expiry_date_raw' => null,
            'expiry_detection_status' => 'unreadable',
            'expiry_detection_source' => $source,
            'expiry_scanned_at' => now(),
        ];
    }

    private function buildResult(
        string $text,
        string $source
    ): array {
        $detected =
            $this->extractExpiryDate(
                $text
            );

        return [
            'expiry_date' =>
                $detected['date'],

            'expiry_date_raw' =>
                $detected['raw'],

            'expiry_detection_status' =>
                $detected['status'],

            'expiry_detection_source' =>
                $source,

            'expiry_scanned_at' =>
                now(),
        ];
    }

    private function extractExpiryDate(
        string $text
    ): array {
        $lines = preg_split(
            '/\R/u',
            $text
        ) ?: [];

        $labelPattern =
            '/(?:' .
            'expiry\s*date|' .
            'expiration\s*date|' .
            'date\s*of\s*expiry|' .
            'date\s*of\s*expiration|' .
            'valid\s*until|' .
            'valid\s*through|' .
            'valid\s*thru|' .
            'validity\s*until|' .
            'expires?\s*on|' .
            'shall\s*expire\s*on' .
            ')/i';

        foreach (
            $lines
            as $index => $line
        ) {
            if (
                !preg_match(
                    $labelPattern,
                    $line
                )
            ) {
                continue;
            }

            $window = trim(
                implode(
                    ' ',
                    array_slice(
                        $lines,
                        $index,
                        3
                    )
                )
            );

            $candidate =
                $this->findDateCandidate(
                    $window
                );

            if (!$candidate) {
                continue;
            }

            $parsed =
                $this->parseDateCandidate(
                    $candidate
                );

            if (
                $parsed['ambiguous']
            ) {
                return [
                    'date' => null,
                    'raw' => $candidate,
                    'status' => 'needs_review',
                ];
            }

            if ($parsed['date']) {
                return [
                    'date' =>
                        $parsed['date'],

                    'raw' =>
                        $candidate,

                    'status' =>
                        'detected',
                ];
            }
        }

        $range =
            $this->findValidityRange(
                $text
            );

        if ($range) {
            $start =
                $this->parseDateCandidate(
                    $range['start']
                );

            $end =
                $this->parseDateCandidate(
                    $range['end']
                );

            if (
                $start['ambiguous'] ||
                $end['ambiguous']
            ) {
                return [
                    'date' => null,
                    'raw' =>
                        $range['raw'],
                    'status' =>
                        'needs_review',
                ];
            }

            if ($end['date']) {
                if (
                    $start['date'] &&
                    Carbon::parse(
                        $end['date']
                    )->lt(
                        Carbon::parse(
                            $start['date']
                        )
                    )
                ) {
                    return [
                        'date' => null,
                        'raw' =>
                            $range['raw'],
                        'status' =>
                            'needs_review',
                    ];
                }

                return [
                    'date' =>
                        $end['date'],

                    'raw' =>
                        $range['raw'],

                    'status' =>
                        'detected',
                ];
            }
        }

        return [
            'date' => null,
            'raw' => null,
            'status' => 'not_found',
        ];
    }

    private function findValidityRange(
        string $text
    ): ?array {
        $months =
            'Jan(?:uary)?|' .
            'Feb(?:ruary)?|' .
            'Mar(?:ch)?|' .
            'Apr(?:il)?|' .
            'May|' .
            'Jun(?:e)?|' .
            'Jul(?:y)?|' .
            'Aug(?:ust)?|' .
            'Sep(?:tember)?|' .
            'Sept(?:ember)?|' .
            'Oct(?:ober)?|' .
            'Nov(?:ember)?|' .
            'Dec(?:ember)?';

        $datePattern =
            '(?:' .
                '(?:' .
                    $months .
                ')\s+\d{1,2},?\s+\d{4}' .
            '|' .
                '\d{1,2}\s+(?:' .
                    $months .
                '),?\s+\d{4}' .
            '|' .
                '\d{4}[\/.\-]\d{1,2}[\/.\-]\d{1,2}' .
            '|' .
                '\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{4}' .
            ')';

        $normalized =
            preg_replace(
                '/\s+/u',
                ' ',
                $text
            ) ?? $text;

        $patterns = [
            '/\b(?:valid(?:ity)?(?:\s+period)?(?:\s+from)?|effective(?:\s+from)?|from)\s*:?\s*(' .
                $datePattern .
            ')\s*(?:to|until|through|thru)\s*(' .
                $datePattern .
            ')\b/i',

            '/\b(?:valid(?:ity)?|effective)\s*:?\s*(' .
                $datePattern .
            ')\s*(?:-|–|—)\s*(' .
                $datePattern .
            ')\b/i',
        ];

        foreach (
            $patterns
            as $pattern
        ) {
            if (
                preg_match(
                    $pattern,
                    $normalized,
                    $matches
                )
            ) {
                return [
                    'start' =>
                        trim($matches[1]),

                    'end' =>
                        trim($matches[2]),

                    'raw' =>
                        trim($matches[0]),
                ];
            }
        }

        return null;
    }

    private function findDateCandidate(
        string $text
    ): ?string {
        $months =
            'Jan(?:uary)?|' .
            'Feb(?:ruary)?|' .
            'Mar(?:ch)?|' .
            'Apr(?:il)?|' .
            'May|' .
            'Jun(?:e)?|' .
            'Jul(?:y)?|' .
            'Aug(?:ust)?|' .
            'Sep(?:tember)?|' .
            'Sept(?:ember)?|' .
            'Oct(?:ober)?|' .
            'Nov(?:ember)?|' .
            'Dec(?:ember)?';

        $patterns = [
            '/\b(?:' .
                $months .
                ')\s+\d{1,2},?\s+\d{4}\b/i',

            '/\b\d{1,2}\s+(?:' .
                $months .
                '),?\s+\d{4}\b/i',

            '/\b\d{4}[\/.\-]\d{1,2}[\/.\-]\d{1,2}\b/',

            '/\b\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{4}\b/',
        ];

        foreach (
            $patterns
            as $pattern
        ) {
            if (
                preg_match(
                    $pattern,
                    $text,
                    $matches
                )
            ) {
                return trim(
                    $matches[0]
                );
            }
        }

        return null;
    }

    private function parseDateCandidate(
        string $candidate
    ): array {
        $candidate =
            trim(
                $candidate
            );

        if (
            preg_match(
                '/[A-Za-z]/',
                $candidate
            )
        ) {
            try {
                return [
                    'date' =>
                        Carbon::parse(
                            $candidate
                        )->format(
                            'Y-m-d'
                        ),

                    'ambiguous' =>
                        false,
                ];
            } catch (Throwable $e) {
                return [
                    'date' => null,
                    'ambiguous' => false,
                ];
            }
        }

        $parts =
            preg_split(
                '/[\/.\-]/',
                $candidate
            );

        if (
            !$parts ||
            count($parts) !== 3
        ) {
            return [
                'date' => null,
                'ambiguous' => false,
            ];
        }

        $originalFirst =
            trim(
                $parts[0]
            );

        $parts =
            array_map(
                'intval',
                $parts
            );

        if (
            strlen(
                $originalFirst
            ) === 4
        ) {
            return $this->safeDate(
                $parts[0],
                $parts[1],
                $parts[2]
            );
        }

        [
            $first,
            $second,
            $year,
        ] = $parts;

        if (
            $first <= 12 &&
            $second <= 12
        ) {
            return [
                'date' => null,
                'ambiguous' => true,
            ];
        }

        if ($first > 12) {
            return $this->safeDate(
                $year,
                $second,
                $first
            );
        }

        return $this->safeDate(
            $year,
            $first,
            $second
        );
    }

    private function safeDate(
        int $year,
        int $month,
        int $day
    ): array {
        if (
            !checkdate(
                $month,
                $day,
                $year
            )
        ) {
            return [
                'date' => null,
                'ambiguous' => false,
            ];
        }

        return [
            'date' =>
                sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                ),

            'ambiguous' =>
                false,
        ];
    }

    private function hasUsefulText(
        string $text
    ): bool {
        return mb_strlen(
            preg_replace(
                '/\s+/',
                '',
                $text
            ) ?? ''
        ) >= 30;
    }
}
