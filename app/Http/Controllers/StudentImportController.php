<?php

namespace App\Http\Controllers;

use App\Services\StudentImportService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('students/import', [
            'preview' => session('student_import.preview'),
            'result' => session('student_import_result'),
        ]);
    }

    public function upload(Request $request, StudentImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx'],
        ], [
            'file.required' => 'Pilih berkas Excel (.xlsx).',
            'file.extensions' => 'Berkas harus berformat .xlsx.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $this->discardPending();

        $path = $request->file('file')->store('imports', 'local');

        try {
            $analysis = $service->analyze(
                Storage::disk('local')->path($path), $request->user()
            );
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            report($e);

            throw ValidationException::withMessages([
                'file' => 'Berkas tidak dapat dibaca. Pastikan formatnya .xlsx yang valid dan sesuai template.',
            ]);
        }

        session(['student_import' => ['path' => $path, 'preview' => $analysis]]);

        return redirect()->route('students.import.show');
    }

    public function confirm(Request $request, StudentImportService $service): RedirectResponse
    {
        $path = session('student_import.path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('students.import.show')
                ->withErrors(['file' => 'Sesi impor sudah berakhir. Unggah ulang berkas Anda.']);
        }

        try {
            $analysis = $service->analyze(Storage::disk('local')->path($path), $request->user());
        } catch (\Throwable $e) {
            report($e);
            $this->discardPending();

            return redirect()->route('students.import.show')
                ->withErrors(['file' => 'Berkas tidak dapat dibaca. Unggah ulang.']);
        }

        $results = $service->commit($analysis);

        $this->discardPending();

        Audit::record('students.imported', null, [
            'sheets' => $results,
            'actor_role' => $request->user()->role->value,
        ]);

        return redirect()->route('students.import.show')->with('student_import_result', $results);
    }

    public function cancel(): RedirectResponse
    {
        $this->discardPending();

        return redirect()->route('students.import.show');
    }

    public function template(Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kelas 1');

        $sheet->setCellValue('A1', 'Nama Dosen');
        $sheet->setCellValue('B1', ':');
        $sheet->setCellValue('C1', $request->user()->name);
        $sheet->setCellValue('A2', 'Mata Kuliah');
        $sheet->setCellValue('B2', ':');
        $sheet->setCellValue('A3', 'Kelas');
        $sheet->setCellValue('B3', ':');

        $sheet->setCellValue('A5', 'No');
        $sheet->setCellValue('B5', 'NPM');
        $sheet->setCellValue('C5', 'NAMA');
        $sheet->getStyle('A5:C5')->getFont()->setBold(true);
        $sheet->getStyle('B6:B300')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(40);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template-mahasiswa.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function discardPending(): void
    {
        $pending = session('student_import.path');

        if ($pending) {
            Storage::disk('local')->delete($pending);
        }

        session()->forget('student_import');
    }
}
