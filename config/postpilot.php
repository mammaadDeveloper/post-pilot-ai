<?php

return [
    'model' => env('AI_MODEL'),
    'timeout' => (int) env('AI_TIMEOUT', 45),
    'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 5000),
    'max_output_chars' => (int) env('AI_MAX_OUTPUT_CHARS', 3000),
    'max_variants' => (int) env('AI_MAX_VARIANTS', 3),
    'requests_per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 10),
];
