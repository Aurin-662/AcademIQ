<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BulkVerificationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\VerificationController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Public Verification Routes (Throttled)
$throttleMiddleware = 'throttle:' . config('academiq.verify_throttle', '60,1');

Route::middleware($throttleMiddleware)->group(function () {
    Route::get('/verify', [VerificationController::class, 'index'])->name('verify.index');
    Route::get('/verify/{documentId}', [VerificationController::class, 'verify'])->name('verify.show');
    Route::get('/api/verify/{documentId}', [VerificationController::class, 'verifyApi'])->name('verify.api');
});

// Public Teacher Directory & Profile
Route::get('/teachers/search', [TeacherController::class, 'search'])->name('teachers.search');
Route::get('/teachers/{teacher_id}', [TeacherController::class, 'show'])->name('teachers.show');

// Printable document view (Issuer or Admin)
Route::get('/documents/{id}/print', [DocumentController::class, 'print'])->name('documents.print')->middleware('auth');

// Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Bulk Verification Routes (Protected: employer)
Route::middleware(['auth', 'role:employer'])->group(function () {
    Route::get('/bulk', [BulkVerificationController::class, 'create'])->name('bulk.create');
    Route::post('/bulk', [BulkVerificationController::class, 'store'])->name('bulk.store');
    Route::get('/bulk/history', [BulkVerificationController::class, 'history'])->name('bulk.history');
    Route::get('/bulk/{id}', [BulkVerificationController::class, 'show'])->name('bulk.show');
    Route::get('/bulk/{id}/download', [BulkVerificationController::class, 'download'])->name('bulk.download');
});

// Institution Document Issuance & Analytics (Protected: institution_admin + approved)
Route::middleware(['auth', 'role:institution_admin', 'approved'])->group(function () {
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/institution/analytics', [AnalyticsController::class, 'institution'])->name('institution.analytics');
});

// Teacher LOR Issuance Routes (Protected: teacher + approved)
Route::middleware(['auth', 'role:teacher', 'approved'])->group(function () {
    Route::get('/lor/create', [TeacherController::class, 'createLor'])->name('lor.create');
    Route::post('/lor', [TeacherController::class, 'storeLor'])->name('lor.store');
});

// System Admin Management Routes (Protected: system_admin)
Route::middleware(['auth', 'role:system_admin'])->prefix('admin')->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'admin'])->name('admin.analytics');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('/users/{id}/approve', [AdminController::class, 'approveUser']);
    Route::post('/users/{id}/reject', [AdminController::class, 'rejectUser']);
    Route::post('/teachers/{teacher_id}/verify', [AdminController::class, 'toggleTeacherVerified']);
    Route::get('/documents', [AdminController::class, 'documents'])->name('admin.documents');
    Route::post('/documents/{id}/revoke', [AdminController::class, 'revokeDocument']);
    Route::get('/fraud', [AdminController::class, 'fraud'])->name('admin.fraud');
    Route::post('/fraud/{id}/confirm', [AdminController::class, 'confirmFraud']);
    Route::post('/fraud/{id}/dismiss', [AdminController::class, 'dismissFraud']);
});

