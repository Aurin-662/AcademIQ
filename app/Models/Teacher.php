<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Teacher extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'teachers';

    protected $fillable = [
        'teacher_id',
        'user_id',
        'name',
        'designation',
        'department',
        'institution_code',
        'email',
        'verified',
    ];

    protected $casts = [
        'user_id'    => 'integer',
        'verified'   => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function institution()
    {
        return Institution::where('code', $this->institution_code)->first();
    }
}
