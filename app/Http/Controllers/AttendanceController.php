<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\AttendanceRecord;
use App\Services\CompreFaceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    private $compreFaceService;

    public function __construct(CompreFaceService $compreFaceService)
    {
        $this->compreFaceService = $compreFaceService;
    }

    public function index()
    {
        $todayAttendance = AttendanceRecord::with('member')
            ->forDate(Carbon::today())
            ->get();

        return view('attendance.index', compact('todayAttendance'));
    }

    public function recordAttendance(Request $request)
    {
        try {
            Log::info('Starting attendance recording process');
            
            // Validate request
            if (!$request->hasFile('image')) {
                Log::error('No image file provided in request');
                return response()->json([
                    'status' => 'error',
                    'message' => 'No image provided'
                ], 400);
            }

            // Get image data
            $imageData = $request->file('image')->get();
            Log::info('Image received, size: ' . strlen($imageData) . ' bytes');

            // Attempt face recognition
            Log::info('Attempting face recognition with CompreFace');
            $recognitionResult = $this->compreFaceService->recognizeFace($imageData);
            Log::info('CompreFace recognition result:', ['result' => $recognitionResult]);

            // Check if any faces were detected
            if (empty($recognitionResult['result']) || !is_array($recognitionResult['result'])) {
                Log::warning('No face detected in image');
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'No face detected in the image. Please try again.'
                ], 404);
            }

            // Get the best match
            $matches = collect($recognitionResult['result']);
            if ($matches->isEmpty()) {
                Log::warning('No matches found in recognition result');
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'Face not recognized. Please register first.'
                ], 404);
            }

            // Log all matches for debugging
            $matches->each(function ($match) {
                Log::info('Match found:', [
                    'subject' => $match['subject'] ?? 'unknown',
                    'similarity' => $match['similarity'] ?? 'not set'
                ]);
            });

            // Get the best match from the first face detected
            $bestMatch = null;
            if (!empty($recognitionResult['result'][0]['subjects'])) {
                $bestMatch = collect($recognitionResult['result'][0]['subjects'])
                    ->sortByDesc('similarity')
                    ->first();
            }

            Log::info('Best match found:', ['match' => $bestMatch]);

            // Check if we have a valid match
            if (!$bestMatch || !isset($bestMatch['similarity'])) {
                Log::warning('No valid match found in recognition result');
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'Face not recognized. Please register first.'
                ], 404);
            }

            // Check confidence threshold (set to 0.95 or 95%)
            if ($bestMatch['similarity'] < 0.95) {
                Log::warning('Low confidence match:', [
                    'similarity' => $bestMatch['similarity'],
                    'threshold' => 0.95,
                    'subject' => $bestMatch['subject'] ?? 'unknown'
                ]);
                return response()->json([
                    'status' => 'low_confidence',
                    'message' => 'Face recognition confidence too low (' . number_format($bestMatch['similarity'] * 100, 1) . '%). Please try again with better lighting and face position.'
                ], 400);
            }

            // Check if subject exists
            if (!isset($bestMatch['subject'])) {
                Log::error('No subject ID in match result');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid recognition result. Please try again.'
                ], 500);
            }

            // Find member
            $member = Member::where('face_id', $bestMatch['subject'])->first();
            Log::info('Looking for member with face_id:', [
                'face_id' => $bestMatch['subject'], 
                'found' => (bool)$member,
                'similarity' => $bestMatch['similarity']
            ]);
            
            if (!$member) {
                Log::error('Member not found for face_id: ' . $bestMatch['subject']);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member not found in database.'
                ], 404);
            }

            // Record attendance
            Log::info('Recording attendance for member:', [
                'member_id' => $member->id,
                'name' => $member->full_name,
                'similarity' => $bestMatch['similarity']
            ]);
            
            $attendance = AttendanceRecord::firstOrCreate([
                'member_id' => $member->id,
                'attendance_date' => Carbon::today(),
                'event_type' => $request->input('event_type', 'sunday_service')
            ], [
                'check_in_time' => Carbon::now()
            ]);

            Log::info('Attendance recorded successfully', [
                'attendance_id' => $attendance->id,
                'member_name' => $member->full_name,
                'similarity' => $bestMatch['similarity']
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => "Welcome, {$member->full_name}! (Confidence: " . number_format($bestMatch['similarity'] * 100, 1) . "%)",
                'member' => $member,
                'attendance' => $attendance,
                'confidence' => $bestMatch['similarity']
            ]);

        } catch (Exception $e) {
            Log::error('Error recording attendance: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing attendance. Please try again.'
            ], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            Log::info('Starting member registration process', [
                'request_data' => $request->except('image')
            ]);
            
            try {
                $request->validate([
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'sometimes|nullable|email',
                    'phone' => 'sometimes|nullable|string|max:20',
                    'image' => 'required|file|image|max:10240|mimes:jpeg,png,jpg' // Max 10MB, common image formats
                ]);

                // Check for existing email (including soft-deleted records)
                if ($request->email) {
                    $existingMember = Member::withTrashed()
                        ->where('email', $request->email)
                        ->first();

                    if ($existingMember) {
                        if ($existingMember->trashed()) {
                            // If the record is soft-deleted, restore it and update
                            $existingMember->restore();
                            $existingMember->update([
                                'first_name' => $request->first_name,
                                'last_name' => $request->last_name,
                                'phone' => $request->phone,
                                'is_active' => true,
                                'face_id' => (string) Str::uuid(),
                            ]);
                            $member = $existingMember;
                        } else {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'This email address is already registered. Please use a different email address.'
                            ], 422);
                        }
                    }
                }

                // Only create new member if we haven't restored an existing one
                if (!isset($member)) {
                    $member = Member::create([
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'face_id' => (string) Str::uuid(),
                    ]);
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                throw $e;
            }

            // Get and validate image data
            if (!$request->hasFile('image')) {
                Log::error('No image file provided');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please provide a photo for face recognition.'
                ], 400);
            }

            $imageData = $request->file('image')->get();
            $imageSize = strlen($imageData);
            
            Log::info('Image received for registration', [
                'size' => $imageSize,
                'mime_type' => $request->file('image')->getMimeType()
            ]);

            if ($imageSize < 1024) { // Less than 1KB
                Log::warning('Image file too small', ['size' => $imageSize]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'The provided image is too small. Please provide a clearer photo.'
                ], 400);
            }

            // First, verify that a face can be detected in the image
            try {
                $recognitionResult = $this->compreFaceService->recognizeFace($imageData);
                Log::info('Face detection result', ['result' => $recognitionResult]);

                if (empty($recognitionResult['result']) || !is_array($recognitionResult['result'])) {
                    Log::warning('No face detected in registration image');
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No face was detected in the photo. Please try again with better lighting and make sure your face is clearly visible.'
                    ], 400);
                }

                // Check if multiple faces were detected
                if (count($recognitionResult['result']) > 1) {
                    Log::warning('Multiple faces detected in registration image', [
                        'face_count' => count($recognitionResult['result'])
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Multiple faces were detected in the photo. Please provide a photo with only your face.'
                    ], 400);
                }
            } catch (Exception $e) {
                Log::error('Face detection error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to process the face in the image. Please try again with a clearer photo in good lighting.'
                ], 400);
            }

            // Store the photo
            $photoPath = null;
            try {
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    
                    // Get file info for logging
                    $fileInfo = [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize()
                    ];
                    Log::info('Processing uploaded file', $fileInfo);

                    // Generate filename using person's name and unique identifier
                    $extension = $file->getClientOriginalExtension();
                    $safeName = Str::slug("{$member->first_name}_{$member->last_name}"); // Convert name to URL-safe format
                    $uniqueId = Str::substr(Str::uuid(), 0, 8); // Use first 8 characters of UUID
                    $filename = "{$safeName}_{$uniqueId}.{$extension}";
                    
                    // Store in member-photos directory
                    $photoPath = $file->storeAs('member-photos', $filename, 'public');
                    
                    if ($photoPath) {
                        // Update member with photo path
                        $member->update(['photo' => $photoPath]);
                        Log::info('Photo stored successfully', [
                            'path' => $photoPath,
                            'member_id' => $member->id,
                            'storage_path' => Storage::disk('public')->path($photoPath)
                        ]);
                    } else {
                        Log::warning('Photo storage returned empty path', [
                            'filename' => $filename,
                            'target_path' => "member-photos/{$filename}"
                        ]);
                    }
                }
            } catch (Exception $e) {
                Log::error('Error in photo upload process', [
                    'error' => $e->getMessage(),
                    'member_id' => $member->id,
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue with registration even if photo storage has issues
            }

            // Get the image data for face registration
            $imageData = $request->file('image')->get();

            // Add face to CompreFace
            try {
                Log::info('Adding face to CompreFace', [
                    'member_id' => $member->id,
                    'face_id' => $member->face_id
                ]);
                
                // Use member's name as the subject ID
                $subjectName = Str::slug($member->full_name);
                $faceResult = $this->compreFaceService->addFace($imageData, $subjectName);
                Log::info('Face added to CompreFace', ['result' => $faceResult]);

                // Store the face_id as the subject name for consistency
                $member->update([
                    'face_id' => $subjectName,
                    'face_metadata' => $faceResult
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Registration successful! You can now use face recognition to record attendance.',
                    'member' => $member
                ]);

            } catch (Exception $e) {
                Log::error('Error adding face to CompreFace', [
                    'error' => $e->getMessage(),
                    'member_id' => $member->id,
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Instead of soft-deleting, mark the member as inactive
                $member->update(['is_active' => false]);
                
                // Clean up photo if it exists
                if ($member->photo) {
                    Storage::disk('public')->delete($member->photo);
                    $member->update(['photo' => null]);
                }
                
                $errorMessage = 'Error registering face. ';
                if (strpos($e->getMessage(), 'Connection refused') !== false) {
                    $errorMessage .= 'Face recognition service is currently unavailable. Please try again later.';
                } else {
                    $errorMessage .= 'Please try again with a clearer photo in good lighting.';
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Unexpected error during registration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Clean up if member was created but keep the record
            if (isset($member)) {
                if ($member->photo) {
                    Storage::disk('public')->delete($member->photo);
                    $member->update(['photo' => null]);
                }
                $member->update(['is_active' => false]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred during registration. Please try again.',
                'debug_info' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function reports(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $eventType = $request->input('event_type', 'sunday_service');

        $attendance = AttendanceRecord::with('member')
            ->forDate($date)
            ->forEvent($eventType)
            ->get();

        return view('attendance.reports', compact('attendance', 'date', 'eventType'));
    }

    public function deleteMember($id)
    {
        try {
            $member = Member::findOrFail($id);
            
            // Delete face from CompreFace if face_id exists
            if ($member->face_id) {
                try {
                    $this->compreFaceService->deleteFace($member->face_id);
                    Log::info('Face deleted from CompreFace', ['face_id' => $member->face_id]);
                } catch (Exception $e) {
                    Log::error('Error deleting face from CompreFace: ' . $e->getMessage());
                    // Continue with member deletion even if face deletion fails
                }
            }

            // Delete photo if it exists
            if ($member->photo) {
                try {
                    Storage::disk('public')->delete($member->photo);
                    Log::info('Member photo deleted', ['photo_path' => $member->photo]);
                } catch (Exception $e) {
                    Log::error('Error deleting member photo: ' . $e->getMessage());
                    // Continue with member deletion even if photo deletion fails
                }
            }

            // Hard delete member and related records
            $member->attendanceRecords()->delete(); // Delete related attendance records
            $member->forceDelete(); // Perform hard delete
            
            Log::info('Member permanently deleted', [
                'member_id' => $id,
                'name' => $member->full_name,
                'email' => $member->email
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Member permanently deleted'
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting member', [
                'error' => $e->getMessage(),
                'member_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting member. Please try again.'
            ], 500);
        }
    }

    public function manageMembers()
    {
        $members = Member::where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
            
        Log::info('Fetching members for management page', [
            'count' => $members->count(),
            'active_only' => true
        ]);
        
        return view('attendance.manage', compact('members'));
    }

    public function recognize(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|file|image'
            ]);

            // Get image data
            $imageData = $request->file('image')->get();

            // Recognize face using CompreFace
            $recognitionResult = $this->compreFaceService->recognizeFace($imageData);

            if (empty($recognitionResult['result'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No face detected in the image. Please try again with better lighting and face position.'
                ], 400);
            }

            // Find the best match
            $bestMatch = collect($recognitionResult['result'])
                ->sortByDesc('similarity')
                ->first();

            if (!$bestMatch || $bestMatch['similarity'] < 0.85) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Face not recognized. Please try again or register if you are a new member.'
                ], 404);
            }

            // Find member by face_id
            $member = Member::where('face_id', $bestMatch['subject'])
                ->where('is_active', true)
                ->first();

            if (!$member) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member not found in the system.'
                ], 404);
            }

            // Record attendance
            $attendance = $member->attendanceRecords()->create([
                'check_in_time' => now(),
                'attendance_date' => now()->toDateString(),
                'similarity_score' => $bestMatch['similarity'],
                'event_type' => 'sunday_service' // Default event type
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Welcome, {$member->full_name}! Your attendance has been recorded.",
                'member' => $member,
                'attendance' => $attendance
            ]);

        } catch (Exception $e) {
            Log::error('Error in face recognition: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during face recognition. Please try again.'
            ], 500);
        }
    }

    public function realtimeRecognize(Request $request)
    {
        try {
            // Validate request
            if (!$request->hasFile('image')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No image provided'
                ], 400);
            }

            // Get image data
            $imageData = $request->file('image')->get();

            // Attempt face recognition
            $recognitionResult = $this->compreFaceService->recognizeFace($imageData);

            // If no faces detected, return early
            if (empty($recognitionResult['result']) || !is_array($recognitionResult['result'])) {
                return response()->json([
                    'status' => 'no_face',
                    'message' => 'No face detected'
                ], 200); // Use 200 for real-time to avoid error logging
            }

            // Get the best match from the first face detected
            $bestMatch = null;
            if (!empty($recognitionResult['result'][0]['subjects'])) {
                $bestMatch = collect($recognitionResult['result'][0]['subjects'])
                    ->sortByDesc('similarity')
                    ->first();
            }

            // If no match or low confidence, return early
            if (!$bestMatch || !isset($bestMatch['similarity']) || $bestMatch['similarity'] < 0.95) {
                return response()->json([
                    'status' => 'low_confidence',
                    'similarity' => $bestMatch['similarity'] ?? 0,
                    'message' => 'Face not recognized'
                ], 200); // Use 200 for real-time to avoid error logging
            }

            // Find member
            $member = Member::where('face_id', $bestMatch['subject'])->first();
            
            if (!$member) {
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'Member not found',
                    'member' => null
                ], 200);
            }

            // Check if attendance already recorded today
            $existingAttendance = AttendanceRecord::where('member_id', $member->id)
                ->where('attendance_date', Carbon::today())
                ->where('event_type', $request->input('event_type', 'sunday_service'))
                ->first();

            if ($existingAttendance) {
                return response()->json([
                    'status' => 'already_recorded',
                    'message' => "Welcome back, {$member->full_name}!",
                    'member' => [
                        'id' => $member->id,
                        'full_name' => $member->first_name . ' ' . $member->last_name
                    ],
                    'confidence' => $bestMatch['similarity']
                ], 200);
            }

            // Record attendance
            $attendance = AttendanceRecord::create([
                'member_id' => $member->id,
                'attendance_date' => Carbon::today(),
                'check_in_time' => Carbon::now(),
                'event_type' => $request->input('event_type', 'sunday_service')
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Welcome, {$member->full_name}!",
                'member' => [
                    'id' => $member->id,
                    'full_name' => $member->first_name . ' ' . $member->last_name
                ],
                'attendance' => $attendance,
                'confidence' => $bestMatch['similarity']
            ]);

        } catch (Exception $e) {
            Log::error('Error in realtime recognition: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing recognition'
            ], 500);
        }
    }

    public function deleteRecord($id)
    {
        try {
            $record = AttendanceRecord::findOrFail($id);
            $record->delete();
            
            Log::info('Attendance record deleted', [
                'record_id' => $id,
                'member_id' => $record->member_id,
                'date' => $record->attendance_date
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Attendance record deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting attendance record', [
                'error' => $e->getMessage(),
                'record_id' => $id
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting attendance record'
            ], 500);
        }
    }

    public function clearAllRecords()
    {
        try {
            AttendanceRecord::truncate();
            
            Log::info('All attendance records cleared');
            
            return response()->json([
                'status' => 'success',
                'message' => 'All attendance records have been cleared'
            ]);
        } catch (Exception $e) {
            Log::error('Error clearing attendance records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error clearing attendance records'
            ], 500);
        }
    }
}
