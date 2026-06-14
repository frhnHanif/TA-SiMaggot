<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceControl extends Model
{
    use HasFactory;

    // Mengizinkan pengisian data massal untuk 2 kolom ini
    protected $fillable = [
        'is_manual',
        'mist',
        'mist_stop_at',
        'fan',
        'controlled_by',
        'locked_until'
    ];

    // Memberi tahu Laravel bahwa 'mist' harus diperlakukan sebagai Array
    protected $casts = [
        'is_manual' => 'boolean',
        'fan' => 'integer',               // Paksa selalu jadi angka
        'controlled_by' => 'integer',     // Paksa selalu jadi angka
        'mist' => 'array',
        'mist_stop_at' => 'array',
        'locked_until' => 'datetime',
    ];

    // Relasi untuk mengetahui nama pengelola yang sedang mengunci
    public function controllerUser()
    {
        return $this->belongsTo(User::class, 'controlled_by');
    }
}