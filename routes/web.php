<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\LabFacilityController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\CourseClassController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\PublicLaboratoryController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\LabCalendarController;

Route::inertia('/', 'welcome')->name('home');
Route::inertia('/tentang', 'tentang')->name('tentang');
Route::get('/laboratorium', [PublicLaboratoryController::class, 'index'])->name('laboratorium.index');
Route::inertia('/sertifikat', 'sertifikat')->name('sertifikat.index');
Route::inertia('/panduan', 'panduan', ['section' => 'sop'])->name('panduan.index');
Route::inertia('/panduan/software', 'panduan', ['section' => 'software'])->name('panduan.software');
Route::get('bookings/create', [BookingController::class, 'create'])->name('bookings.create');
Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('bookings/{reservation}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
Route::put('bookings/{reservation}', [BookingController::class, 'update'])->name('bookings.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('calendar', [LabCalendarController::class, 'index'])->name('calendar.index');

    Route::middleware('can:manage-accounts')
        ->prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::get('create', [AccountController::class, 'create'])->name('create');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{user}/edit', [AccountController::class, 'edit'])->name('edit');
            Route::put('{user}', [AccountController::class, 'update'])->name('update');
            Route::patch('{user}/status', [AccountController::class, 'toggleStatus'])->name('status');
        });
    
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:view-audit-logs')
        ->name('audit-logs.index');

    Route::get('facilities', [LabFacilityController::class, 'index'])->name('facilities.index');
    Route::post('labs/{lab}/facilities', [LabFacilityController::class, 'store'])->name('facilities.store');
    Route::put('facilities/{facility}', [LabFacilityController::class, 'update'])->name('facilities.update');
    Route::delete('facilities/{facility}', [LabFacilityController::class, 'destroy'])->name('facilities.destroy');

    Route::middleware('can:import-students')
    ->prefix('students/import')->name('students.import.')->group(function () {
        Route::get('/', [StudentImportController::class, 'show'])->name('show');
        Route::get('template', [StudentImportController::class, 'template'])->name('template');
        Route::post('/', [StudentImportController::class, 'upload'])->name('upload');
        Route::post('confirm', [StudentImportController::class, 'confirm'])->name('confirm');
        Route::delete('/', [StudentImportController::class, 'cancel'])->name('cancel');
    });
    Route::middleware('can:manage-classes')->group(function () {
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::put('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::patch('courses/{course}/status', [CourseController::class, 'toggleStatus'])->name('courses.status');

        Route::get('classes', [CourseClassController::class, 'index'])->name('classes.index');
        Route::post('classes', [CourseClassController::class, 'store'])->name('classes.store');
        Route::put('classes/{courseClass}', [CourseClassController::class, 'update'])->name('classes.update');
        Route::patch('classes/{courseClass}/status', [CourseClassController::class, 'toggleStatus'])->name('classes.status');
    });

    Route::middleware('can:manage-master-data')->group(function () {
        Route::get('time-slots', [TimeSlotController::class, 'index'])->name('time-slots.index');
        Route::put('time-slots/{timeSlot}', [TimeSlotController::class, 'update'])->name('time-slots.update');
    });
    Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::get('reservations/{reservation}/edit', [ReservationController::class, 'edit'])->name('reservations.edit');
    Route::put('reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
    Route::post('reservations/{reservation}/send', [ReservationController::class, 'send'])->name('reservations.send');
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::get('reservations/{reservation}/sessions/{reservationSession}/module', [ReservationController::class, 'module'])
        ->name('reservations.module');

    Route::get('approvals', [ApprovalController::class, 'index'])
        ->middleware('can:review-reservations')
        ->name('approvals.index');
    Route::post('reservations/{reservation}/decide', [ApprovalController::class, 'decide'])->name('reservations.decide');
});

require __DIR__.'/settings.php';
