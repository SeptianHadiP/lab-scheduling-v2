<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class StudentImportService
{
    private const MAX_SHEETS = 20;

    private const MAX_ROWS = 1000;

    /**
     * Membaca berkas dan menghasilkan pratinjau. Tidak menyimpan apa pun.
     */
    public function analyze(string $path, User $user): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $worksheets = $spreadsheet->getAllSheets();

        if (count($worksheets) > self::MAX_SHEETS) {
            throw new RuntimeException('Terlalu banyak sheet.');
        }

        $sheets = [];
        foreach ($worksheets as $worksheet) {
            $rows = $worksheet->toArray(null, false, false, false);
            $sheets[] = $this->analyzeSheet($worksheet->getTitle(), $rows, $user);
        }

        $spreadsheet->disconnectWorksheets();

        $total = 0;
        foreach ($sheets as $sheet) {
            if ($sheet['importable']) {
                $total += $sheet['counts']['baru'] + $sheet['counts']['tautkan'];
            }
        }

        return ['sheets' => $sheets, 'total_importable' => $total];
    }

    /**
     * Menyimpan sheet yang importable. Sheet bermasalah dilewati, tidak diimpor sebagian.
     */
    public function commit(array $analysis): array
    {
        $results = [];

        foreach ($analysis['sheets'] as $sheet) {
            $hasil = [
                'sheet' => $sheet['name'],
                'class' => $sheet['class_label'],
                'imported' => false,
                'created' => 0,
                'linked' => 0,
                'reason' => null,
            ];

            if (! $sheet['importable']) {
                $hasil['reason'] = $sheet['errors'][0]
                    ?? ($sheet['counts']['error'] > 0 ? 'Ada baris yang tidak valid.' : 'Tidak ada mahasiswa baru untuk diimpor.');
                $results[] = $hasil;

                continue;
            }

            DB::transaction(function () use ($sheet, &$hasil) {
                $class = CourseClass::findOrFail($sheet['class_id']);
                $ids = [];

                foreach ($sheet['rows'] as $row) {
                    if ($row['status'] === 'sudah') {
                        continue;
                    }

                    $student = Student::firstOrCreate(['npm' => $row['npm']], ['name' => $row['name']]);
                    $student->wasRecentlyCreated ? $hasil['created']++ : $hasil['linked']++;
                    $ids[] = $student->id;
                }

                $class->students()->syncWithoutDetaching($ids);
            });

            $hasil['imported'] = true;
            $results[] = $hasil;
        }

        return $results;
    }

    private function analyzeSheet(string $title, array $rows, User $user): array
    {
        $sheet = [
            'name' => $title,
            'dosen' => null,
            'course' => null,
            'class_code' => null,
            'class_id' => null,
            'class_label' => null,
            'errors' => [],
            'rows' => [],
            'counts' => ['baru' => 0, 'tautkan' => 0, 'sudah' => 0, 'error' => 0],
            'importable' => false,
        ];

        if (count($rows) > self::MAX_ROWS) {
            $sheet['errors'][] = 'Sheet terlalu besar (maksimal '.self::MAX_ROWS.' baris).';

            return $sheet;
        }

        $sheet['dosen'] = $this->labelValue($rows, 'nama dosen');
        $sheet['course'] = $this->labelValue($rows, 'mata kuliah');
        $sheet['class_code'] = $this->labelValue($rows, 'kelas');

        // if (blank($sheet['dosen'])) {
        //     $sheet['errors'][] = 'Header "Nama Dosen" tidak ditemukan atau kosong.';
        // } elseif (! $this->namesMatch($sheet['dosen'], $user->name)) {
        //     $sheet['errors'][] = "Nama dosen pada berkas ({$sheet['dosen']}) tidak cocok dengan akun Anda ({$user->name}).";
        // }
        
        $owner = null;
        if (blank($sheet['dosen'])) {
            $sheet['errors'][] = 'Header "Nama Dosen" tidak ditemukan atau kosong.';
        } else {
            $owner = $this->resolveOwner($sheet, $user);
        }

        if (blank($sheet['course'])) {
            $sheet['errors'][] = 'Header "Mata Kuliah" tidak ditemukan atau kosong.';
        }

        if (blank($sheet['class_code'])) {
            $sheet['errors'][] = 'Header "Kelas" tidak ditemukan atau kosong.';
        }

        $class = null;
        if ($sheet['errors'] === []) {
            $class = $this->resolveClass($sheet, $owner);
        }

        $headerRow = null;
        $map = [];
        foreach ($rows as $r => $row) {
            $found = [];
            foreach (array_values($row) as $i => $cell) {
                $n = $this->norm((string) $cell);
                if ($n === 'npm') {
                    $found['npm'] = $i;
                }
                if (in_array($n, ['nama', 'nama mahasiswa'], true)) {
                    $found['name'] = $i;
                }
            }
            if (isset($found['npm'], $found['name'])) {
                $headerRow = $r;
                $map = $found;
                break;
            }
        }

        if ($headerRow === null) {
            $sheet['errors'][] = 'Tabel dengan kolom NPM dan NAMA tidak ditemukan.';
        }

        if ($sheet['errors'] !== [] || $class === null) {
            return $sheet;
        }

        $sheet['class_id'] = $class->id;
        $sheet['class_label'] = "{$class->course->code} - {$class->course->name} / {$class->code} ({$class->academic_year})";

        $parsed = [];
        for ($r = $headerRow + 1; $r < count($rows); $r++) {
            $row = array_values($rows[$r]);
            $npm = $this->npmText($row[$map['npm']] ?? null);
            $name = $this->cleanName($row[$map['name']] ?? null);

            if ($npm === '' && $name === '') {
                continue;
            }

            $parsed[] = ['row' => $r + 1, 'npm' => $npm, 'name' => $name];
        }

        if ($parsed === []) {
            $sheet['errors'][] = 'Tidak ada baris mahasiswa pada tabel.';

            return $sheet;
        }

        $existing = Student::whereIn('npm', array_column($parsed, 'npm'))->get()->keyBy('npm');
        $inClass = $class->students()->pluck('students.npm')->flip()->all();
        $seen = [];

        foreach ($parsed as $p) {
            $errors = [];
            $warning = null;

            if (! preg_match('/^\d{12}$/', $p['npm'])) {
                $errors[] = 'NPM harus 12 digit angka.';
            }
            if ($p['name'] === '') {
                $errors[] = 'Nama kosong.';
            } elseif (mb_strlen($p['name']) > 255) {
                $errors[] = 'Nama terlalu panjang.';
            }
            if ($p['npm'] !== '' && isset($seen[$p['npm']])) {
                $errors[] = "NPM dobel dengan baris {$seen[$p['npm']]}.";
            }
            $seen[$p['npm']] ??= $p['row'];

            if ($errors !== []) {
                $status = 'error';
            } elseif (isset($inClass[$p['npm']])) {
                $status = 'sudah';
            } elseif ($existing->has($p['npm'])) {
                $status = 'tautkan';
            } else {
                $status = 'baru';
            }

            if ($errors === [] && $existing->has($p['npm'])
                && $this->norm($existing[$p['npm']]->name) !== $this->norm($p['name'])) {
                $warning = "Nama pada sistem: {$existing[$p['npm']]->name}";
            }

            $sheet['counts'][$status]++;
            $sheet['rows'][] = $p + ['status' => $status, 'errors' => $errors, 'warning' => $warning];
        }

        $sheet['importable'] = $sheet['counts']['error'] === 0
            && ($sheet['counts']['baru'] + $sheet['counts']['tautkan']) > 0;

        return $sheet;
    }

    private function resolveOwner(array &$sheet, User $actor): ?User
    {
        if ($actor->isDosen()) {
            if (! $this->namesMatch($sheet['dosen'], $actor->name)) {
                $sheet['errors'][] = "Nama dosen pada berkas ({$sheet['dosen']}) tidak cocok dengan akun Anda ({$actor->name}).";

                return null;
            }

            return $actor;
        }

        $kandidat = User::where('role', Role::Dosen->value)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $d) => $this->namesContain($sheet['dosen'], $d->name));

        if ($kandidat->count() !== 1) {
            $sheet['errors'][] = $kandidat->isEmpty()
                ? "Dosen \"{$sheet['dosen']}\" tidak ditemukan di sistem."
                : "Nama dosen \"{$sheet['dosen']}\" cocok dengan lebih dari satu akun. Tulis nama lengkap sesuai akun.";

            return null;
        }

        return $kandidat->first();
    }

    private function namesContain(string $fileName, string $accountName): bool
    {
        $file = explode(' ', $this->norm($fileName));
        $account = explode(' ', $this->norm($accountName));

        return $account !== [''] && array_diff($account, $file) === [];
    }

    private function resolveClass(array &$sheet, User $user): ?CourseClass
    {
        $courseText = $this->norm($sheet['course']);

        $course = Course::where('is_active', true)->get()->first(
            fn (Course $c) => $this->norm($c->name) === $courseText || $this->norm($c->code) === $courseText
        );

        if (! $course) {
            $sheet['errors'][] = "Mata kuliah \"{$sheet['course']}\" tidak ditemukan di sistem.";

            return null;
        }

        $classes = CourseClass::with('course')
            ->where('course_id', $course->id)
            ->where('dosen_id', $user->id)
            ->where('is_active', true)
            ->where('code', strtoupper(trim($sheet['class_code'])))
            ->get();

        if ($classes->isEmpty()) {
            $sheet['errors'][] = "Kelas \"{$sheet['class_code']}\" untuk mata kuliah ini tidak ditemukan atau bukan kelas Anda.";

            return null;
        }

        if ($classes->count() > 1) {
            $sheet['errors'][] = 'Ada beberapa kelas aktif dengan kode yang sama. Minta Ketua Lab mengarsipkan kelas tahun lama.';

            return null;
        }

        return $classes->first();
    }

    private function labelValue(array $rows, string $label): ?string
    {
        foreach (array_slice($rows, 0, 15) as $row) {
            $cells = array_values($row);

            foreach ($cells as $i => $cell) {
                $text = trim((string) $cell);

                if ($text === '' || ! str_starts_with(mb_strtolower($text), $label)) {
                    continue;
                }

                $sisa = ltrim(mb_substr($text, mb_strlen($label)), " :\t");
                if ($sisa !== '') {
                    return trim($sisa);
                }

                for ($j = $i + 1; $j < count($cells); $j++) {
                    $nilai = trim((string) $cells[$j]);
                    if ($nilai !== '' && $nilai !== ':') {
                        return ltrim($nilai, ': ');
                    }
                }
            }
        }

        return null;
    }

    private function npmText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            $value = sprintf('%.0f', $value);
        }

        return preg_replace('/[\s\'`]+/u', '', trim((string) $value));
    }

    private function cleanName(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function norm(string $value): string
    {
        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function namesMatch(string $a, string $b): bool
    {
        $a = $this->norm($a);
        $b = $this->norm($b);

        return $a !== '' && $b !== '' && ($a === $b || str_contains($a, $b) || str_contains($b, $a));
    }
}