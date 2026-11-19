<?php

namespace App\Services\Credentials;

use App\Models\StudentReference;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Processes 1.1 (bulk import): parse CSV/XLSX and validate every row before anything is issued. No crypto, no writes. */
class BulkImport
{
    public const COLUMNS = ['student_id', 'name', 'institution', 'degree', 'department', 'cgpa', 'passing_year'];

    public static function template(): string
    {
        return implode(',', self::COLUMNS)."\r\nKUET-2001,Example Student,KUET,BSc,CSE,3.52,".now()->year."\r\n";
    }

    /** Raw cell grid of a CSV or XLSX upload (also used by bulk verification). @return array<int,array<int,string>> */
    public function grid(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx' ? $this->xlsx($file->getRealPath()) : $this->csv($file->getRealPath());
    }

    /** @return array<int,array<string,string>> rows keyed by spreadsheet row number (header = row 1), header names lowercased */
    public function parse(UploadedFile $file): array
    {
        $grid = $this->grid($file);
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), array_shift($grid) ?? []);
        if ($missing = array_diff(self::COLUMNS, $header)) {
            throw ValidationException::withMessages(['file' => 'Missing column(s): '.implode(', ', $missing).'. Download the template.']);
        }
        $rows = [];
        foreach ($grid as $i => $cells) {
            $row = [];
            foreach ($header as $c => $name) {
                $row[$name] = trim((string) ($cells[$c] ?? ''));
            }
            if (array_filter($row, fn ($v) => $v !== '')) {
                $rows[$i + 2] = $row;
            }
        }
        $max = config('academiq.import_max_rows');
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'The file has no data rows.']);
        }
        if (count($rows) > $max) {
            throw ValidationException::withMessages(['file' => "Too many rows (max $max per import). Split the file."]);
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array{valid:array<int,array>,invalid:array<int,array{ref:string,errors:string[]}>,preview:array<int,array>}
     *   valid = claims ready for CredentialService::issueBatch; preview = every row with errors/warnings for the table
     */
    public function validate(array $rows): array
    {
        $students = StudentReference::whereIn('student_reference', array_values(array_unique(array_column($rows, 'student_id'))))->get()->keyBy('student_reference');
        $names = User::whereIn('_id', $students->pluck('user_id')->filter()->all())->get()->mapWithKeys(fn ($u) => [(string) $u->getKey() => $u->name]);
        $seen = [];
        $valid = $invalid = $preview = [];
        $minYear = config('academiq.min_passing_year');
        $maxYear = (int) now()->year;

        foreach ($rows as $no => $r) {
            $errors = $warnings = [];
            $ref = $r['student_id'];
            foreach (self::COLUMNS as $col) {
                if ($r[$col] === '') {
                    $errors[] = "Missing $col";
                }
            }
            if ($ref !== '') {
                if (! preg_match('/^[A-Za-z0-9\-_]{1,40}$/', $ref)) {
                    $errors[] = 'Invalid student_id format';
                } elseif (isset($seen[$ref])) {
                    $errors[] = "Duplicate student_id (first seen on row {$seen[$ref]})";
                } else {
                    $seen[$ref] = $no;
                    $s = $students->get($ref);
                    if (! $s || $s->account_status !== 'active') {
                        $errors[] = 'No active student with this ID';
                    } elseif (empty($s->wallet_public_key)) {
                        $errors[] = 'Student has not set up a wallet yet';
                    } elseif ($r['name'] !== '' && ($known = $names[$s->user_id] ?? null) && $this->norm($known) !== $this->norm($r['name'])) {
                        $warnings[] = 'Name differs from the student account';
                    }
                }
            }
            $institution = $r['institution'] === '' ? null : self::resolve('institutions', $r['institution']);
            $degree = $r['degree'] === '' ? null : self::resolve('degrees', $r['degree']);
            $programme = $r['department'] === '' ? null : self::resolve('programmes', $r['department']);
            foreach ([['institution', $institution], ['degree', $degree], ['department', $programme]] as [$col, $code]) {
                if ($r[$col] !== '' && $code === null) {
                    $errors[] = "Unknown $col '{$r[$col]}'";
                }
            }
            $cgpa = null;
            if ($r['cgpa'] !== '') {
                if (! is_numeric($r['cgpa']) || (float) $r['cgpa'] < 0 || (float) $r['cgpa'] > 4) {
                    $errors[] = 'CGPA must be a number from 0.00 to 4.00';
                } elseif (abs((float) $r['cgpa'] * 100 - round((float) $r['cgpa'] * 100)) > 1e-6) {
                    $errors[] = 'CGPA allows at most 2 decimals';
                } else {
                    $cgpa = number_format((float) $r['cgpa'], 2, '.', '');
                }
            }
            $year = null;
            if ($r['passing_year'] !== '') {
                if (! is_numeric($r['passing_year']) || (float) $r['passing_year'] != (int) $r['passing_year'] || (int) $r['passing_year'] < $minYear || (int) $r['passing_year'] > $maxYear) {
                    $errors[] = "Passing year must be a whole year from $minYear to $maxYear";
                } else {
                    $year = (int) $r['passing_year'];
                }
            }

            $preview[$no] = ['student_id' => $ref, 'name' => $r['name'], 'institution' => $r['institution'], 'degree' => $r['degree'], 'department' => $r['department'], 'cgpa' => $r['cgpa'], 'passing_year' => $r['passing_year'], 'errors' => $errors, 'warnings' => $warnings];
            if ($errors) {
                $invalid[$no] = ['ref' => $ref, 'errors' => $errors];
            } else {
                $valid[$no] = ['student_reference' => $ref, 'cgpa' => $cgpa, 'programme_code' => $programme, 'degree_category' => $degree, 'institution_code' => $institution,
                    'passing_year' => $year, 'issue_date' => now()->toDateString()];
            }
        }

        return compact('valid', 'invalid', 'preview');
    }

    /** Free text -> code via label, label's "(...)" part, or configured aliases. Null when unknown. */
    public static function resolve(string $table, string $text): ?int
    {
        $want = self::norm($text);
        foreach (config("academiq.$table") as $code => $label) {
            $cands = [$label, ...(config("academiq.aliases.$table.$code") ?? [])];
            if (preg_match('/\(([^)]+)\)/', $label, $m)) {
                $cands[] = $m[1];
            }
            foreach ($cands as $c) {
                if (self::norm($c) === $want) {
                    return $code;
                }
            }
        }

        return null;
    }

    private static function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s));
    }

    private function csv(string $path): array
    {
        $grid = [];
        $h = fopen($path, 'rb');
        while (($cells = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $grid[] = $cells;
        }
        fclose($h);

        return $grid;
    }

    /** Minimal first-sheet XLSX reader (zip + XML, no dependency). Formulas/dates are not evaluated; use plain values. */
    private function xlsx(string $path): array
    {
        $z = new ZipArchive;
        if ($z->open($path) !== true || ($sheetXml = $z->getFromName('xl/worksheets/sheet1.xml')) === false) {
            throw ValidationException::withMessages(['file' => 'Cannot read this XLSX file (first sheet must be the data).']);
        }
        $strings = [];
        if (($ss = $z->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($ss)->si as $si) {
                $t = (string) $si->t;
                foreach ($si->r as $run) {
                    $t .= (string) $run->t;
                }
                $strings[] = $t;
            }
        }
        $grid = [];
        foreach (simplexml_load_string($sheetXml)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = 0;
                foreach (str_split(preg_replace('/\d+/', '', (string) $c['r'])) as $ch) {
                    $col = $col * 26 + ord($ch) - 64;
                }
                $type = (string) $c['t'];
                $cells[$col - 1] = $type === 's' ? ($strings[(int) $c->v] ?? '') : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
            }
            $max = $cells ? max(array_keys($cells)) : -1;
            $grid[] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
        }

        return $grid;
    }
}
