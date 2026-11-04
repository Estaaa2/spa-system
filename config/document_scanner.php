<?php

return [
    'tesseract' => env(
        'DOCUMENT_TESSERACT_PATH',
        'tesseract'
    ),

    'pdftotext' => env(
        'DOCUMENT_PDFTOTEXT_PATH',
        'pdftotext'
    ),

    'pdftoppm' => env(
        'DOCUMENT_PDFTOPPM_PATH',
        'pdftoppm'
    ),

    'language' => env(
        'DOCUMENT_OCR_LANGUAGE',
        'eng'
    ),

    'timeout' => (int) env(
        'DOCUMENT_OCR_TIMEOUT',
        30
    ),

    'max_pdf_pages' => (int) env(
        'DOCUMENT_OCR_MAX_PAGES',
        5
    ),
];
