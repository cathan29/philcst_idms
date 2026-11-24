<?php

return [
    'import_max_kilobytes' => (int) env('IDMS_IMPORT_MAX_KB', 25600),
    'report_pdf_limit' => (int) env('IDMS_REPORT_PDF_LIMIT', 500),
    'seed_demo_holders' => (bool) env('IDMS_SEED_DEMO_HOLDERS', false),
];