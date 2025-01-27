<?php

use App\Http\Controllers\AttendanceController;
use App\Services\CompreFaceService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// CompreFace Test Routes
Route::get('/test', function () {
    return view('test');
});

Route::post('/test-face-capture', function (CompreFaceService $compreFace) {
    try {
        $imageData = request()->file('image')->get();
        // First, try to add a test face
        $result = $compreFace->addFace($imageData, 'test-subject-' . time());
        return response()->json([
            'status' => 'success',
            'message' => 'Face captured successfully',
            'data' => $result
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::post('/test-compreface', function (CompreFaceService $compreFace) {
    try {
        $imageData = request()->file('image')->get();
        $response = $compreFace->recognizeFace($imageData);
        return response()->json([
            'status' => 'success',
            'data' => $response
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::group(['prefix' => 'attendance'], function () {
    Route::get('/', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/record', [AttendanceController::class, 'recordAttendance'])->name('attendance.record');
    Route::post('/register', [AttendanceController::class, 'register'])->name('attendance.register');
    Route::get('/reports', [AttendanceController::class, 'reports'])->name('attendance.reports');
    Route::delete('/reports/clear-all', [AttendanceController::class, 'clearAllRecords'])->name('attendance.clear.all');
    
    // Member management routes
    Route::get('/manage', [AttendanceController::class, 'manageMembers'])->name('attendance.manage');
    Route::delete('/members/{id}', [AttendanceController::class, 'deleteMember'])->name('attendance.members.delete');
    Route::delete('/records/{id}', [AttendanceController::class, 'deleteRecord'])->name('attendance.record.delete');

    Route::post('/realtime', [AttendanceController::class, 'realtimeRecognize'])->name('attendance.realtime');
});
