<?php

return [
    // Public, editable code tables. Codes are encoded as integers inside credentials and circuit claims.
    'programmes' => [1 => 'Computer Science & Engineering', 2 => 'Electrical & Electronic Engineering', 3 => 'Mechanical Engineering', 4 => 'Civil Engineering', 5 => 'Industrial & Production Engineering', 6 => 'Electronics & Communication Engineering'],
    'degrees' => [1 => 'Bachelor of Science (BSc)', 2 => 'Master of Science (MSc)', 3 => 'Doctor of Philosophy (PhD)'],

    'institutions' => [1 => 'Khulna University of Engineering & Technology (KUET)'],
    // Extra accepted spellings for bulk import (matched after lowercasing and stripping non-alphanumerics; the text inside "(...)" of a label also matches).
    'aliases' => [
        'programmes' => [1 => ['cse', 'cs'], 2 => ['eee'], 3 => ['me'], 4 => ['ce'], 5 => ['ipe'], 6 => ['ece']],
        'degrees' => [1 => ['bsc', 'bachelor'], 2 => ['msc', 'master'], 3 => ['phd', 'doctorate']],
        'institutions' => [1 => ['kuet']],
    ],
    'import_max_rows' => (int) env('IMPORT_MAX_ROWS', 500),
    'min_passing_year' => 1986,
    'bulk_max_tokens' => (int) env('BULK_MAX_TOKENS', 500),

    // Issuer signing keys live on disk, encrypted with APP_KEY, never in MongoDB. Back this directory up and restrict access.
    'issuer_key_path' => env('ISSUER_KEY_PATH', storage_path('app/issuer-keys')),
    'node_bin' => env('NODE_BIN', 'node'),
    'credential_schema' => 'academiq.credential.v1',
];
