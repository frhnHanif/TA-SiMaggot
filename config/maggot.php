<?php

return [
    // PIN Manajemen Akun via Web
    'admin_pin' => env('ADMIN_PIN'),

    // API Key untuk Hardware ESP32 (Tambahan Baru)
    'api_key' => env('IOT_API_KEY'),

    'thresholds' => [
        // Suhu Udara
        'temp' => [
            'min_ideal' => 24,  // Di bawah ini aktivitas menurun
            'max_ideal' => 30,  // Batas atas ideal
            'max_safe'  => 35,  // Di atas 35 (mulai 36) fatal/mematikan
        ],
        
        // Kelembapan Udara (RH)
        'hum' => [
            'min_ideal' => 60,  // Di bawah ini terlalu kering
            'max_ideal' => 80,  // Di atas ini terlalu lembap
        ],

        // Kelembapan Media/Substrat (Soil)
        'soil' => [
            'min_safe'  => 60,  // Batas bawah aman
            'min_ideal' => 70,
            'max_ideal' => 80,
            'max_safe'  => 90,  // Di atas ini terlalu basah/menghambat pertumbuhan
        ],

        // Gas Amonia (NH3)
        'ammonia' => [
            'max_safe'  => 20,  // Di atas ini beracun bagi larva
        ]
    ]
];