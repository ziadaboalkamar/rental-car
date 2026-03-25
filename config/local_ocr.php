<?php

return [
    'enabled' => filter_var(env('LOCAL_OCR_ENABLED', true), FILTER_VALIDATE_BOOL),
    'python_binary' => (string) env('LOCAL_OCR_PYTHON_BINARY', 'python'),
    'script_path' => (string) env('LOCAL_OCR_SCRIPT_PATH', base_path('scripts/client_document_ocr.py')),
    'timeout' => (int) env('LOCAL_OCR_TIMEOUT', 90),
    'engine' => (string) env('LOCAL_OCR_ENGINE', 'ollama'),
    'ollama_model' => (string) env('LOCAL_OCR_OLLAMA_MODEL', 'llava'),
    'tesseract_binary' => (string) env('LOCAL_OCR_TESSERACT_BINARY', ''),
    'max_image_side' => (int) env('LOCAL_OCR_MAX_IMAGE_SIDE', 1600),
    'grayscale' => filter_var(env('LOCAL_OCR_GRAYSCALE', true), FILTER_VALIDATE_BOOL),
    'autocontrast' => filter_var(env('LOCAL_OCR_AUTOCONTRAST', true), FILTER_VALIDATE_BOOL),
];
