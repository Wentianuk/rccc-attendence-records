<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'photo',
        'face_id',
        'face_metadata',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'face_metadata' => 'array'
    ];

    // Relationships
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
