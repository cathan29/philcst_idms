<?php

use App\Http\Controllers\AcademicTermController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdHolderController;
use App\Http\Controllers\IdProductionController;
use App\Http\Controllers\IdReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanLogController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/scan', [ScanController::class, 'store'])->middleware('permission:cards.view')->name('scan.store');

    Route::middleware('permission:cards.view')->group(function () {
        Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
        Route::post('/scanner/verify', [ScannerController::class, 'verify'])->middleware('throttle:120,1')->name('scanner.verify');
        Route::post('/scanner/cards/{card}/transition', [ScannerController::class, 'transition'])->name('scanner.transition');
        Route::get('/production', [IdProductionController::class, 'index'])->name('production.index');
        Route::get('/production/{card}', [IdProductionController::class, 'show'])->whereNumber('card')->name('production.show');
        Route::post('/production/{card}/transition', [IdProductionController::class, 'transition'])->name('production.transition');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('holders', IdHolderController::class)->except(['show']);
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::get('/logs', [ScanLogController::class, 'index'])->middleware('permission:audit.view')->name('logs.index');

    Route::middleware('permission:students.manage')->group(function () {
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    });
    Route::middleware('permission:students.view')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('/imports/students/template', [StudentImportController::class, 'template'])->name('imports.students.template');
    });
    Route::middleware('permission:imports.manage')->group(function () {
        Route::get('/imports/students', [StudentImportController::class, 'create'])->name('imports.students.create');
        Route::post('/imports/students/preview', [StudentImportController::class, 'preview'])->name('imports.students.preview');
        Route::get('/imports/students/mapping', [StudentImportController::class, 'mapping'])->name('imports.students.mapping');
        Route::post('/imports/students/validate', [StudentImportController::class, 'validateMapping'])->name('imports.students.validate');
        Route::get('/imports/students/review', [StudentImportController::class, 'review'])->name('imports.students.review');
        Route::post('/imports/students/confirm', [StudentImportController::class, 'confirm'])->name('imports.students.confirm');
        Route::post('/imports/students/commit', [StudentImportController::class, 'commit'])->name('imports.students.commit');
        Route::get('/imports/students/results/{batch}', [StudentImportController::class, 'results'])->name('imports.students.results');
        Route::get('/imports/students/results/{batch}/errors', [StudentImportController::class, 'errors'])->name('imports.students.errors');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports', [IdReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [IdReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/print', [IdReportController::class, 'print'])->name('reports.print');
        Route::get('/reports/pdf', [IdReportController::class, 'pdf'])->name('reports.pdf');
    });
    Route::middleware('permission:cards.manage')->group(function () {
        Route::get('/production/students/search', [IdProductionController::class, 'searchStudents'])->middleware('throttle:60,1')->name('production.students.search');
        Route::get('/production/create/request', [IdProductionController::class, 'create'])->name('production.create');
        Route::post('/production', [IdProductionController::class, 'store'])->name('production.store');
    });
    Route::post('/production/bulk', [IdProductionController::class, 'bulk'])->middleware('permission:cards.print')->name('production.bulk');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/academic-terms', [AcademicTermController::class, 'index'])->name('academic-terms.index');
        Route::post('/programs', [AcademicTermController::class, 'storeProgram'])->name('programs.store');
        Route::put('/programs/{program}', [AcademicTermController::class, 'updateProgram'])->name('programs.update');
        Route::post('/academic-years', [AcademicTermController::class, 'storeYear'])->name('academic-years.store');
        Route::post('/academic-terms', [AcademicTermController::class, 'store'])->name('academic-terms.store');
        Route::put('/academic-terms/{academicTerm}', [AcademicTermController::class, 'update'])->name('academic-terms.update');
    });

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});