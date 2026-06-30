<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SiMaggot Dashboard')</title>
    
    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
    
    {{-- Preconnect: buka koneksi lebih awal ke origin eksternal --}}
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://ui-avatars.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    
    {{-- Font Awesome: non-render-blocking --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all';this.onload=null;">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    {{-- Google Fonts: preload + display=swap --}}
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    
    {{-- Vite: Tailwind CSS yang sudah di-build (menggantikan CDN ~3MB) --}}
    @vite('resources/css/app.css')
    
    <style>
        body { background-color: #F8F9FA; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        @media (max-width: 640px) {
            html { font-size: 12px; }
        }
        /* Content-visibility: tunda rendering elemen di bawah fold */
        .below-fold { content-visibility: auto; contain-intrinsic-size: auto 500px; }
        /* Placeholder dimensi container chart (halaman statistik) */
        .chart-container { min-height: 300px; contain: layout style; }
        /* Animasi modal */
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-fade-in { animation: fadeIn 0.2s ease-out; }
    </style>
</head>
<body class="text-gray-800 antialiased font-sans relative">

    <!-- NAVBAR DESKTOP & PROFILE -->
    <nav class="fixed top-4 left-4 right-4 z-50 bg-white/90 backdrop-blur-md shadow-sm border border-gray-100 rounded-[2rem] px-4 sm:px-6 py-3 flex justify-between items-center max-w-screen-2xl mx-auto">
        
       <a href="/" class="flex items-center gap-3 cursor-pointer hover:opacity-80 transition-opacity" title="Dashboard">
            <img src="{{ asset('icon.png') }}" alt="SiMaggot" class="w-10 h-10 object-contain">
            <span class="font-extrabold text-xl text-gray-800 tracking-tight">Si<span class="text-amber-500">Maggot</span></span>
        </a>

        <div class="hidden lg:flex items-center bg-gray-50 p-1 rounded-full border border-gray-200 shadow-inner">
            <a href="/" class="{{ request()->is('/') ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-800 font-medium' }} px-5 py-2 rounded-full text-sm transition-all duration-300 flex items-center gap-2">
                <i class="fa-solid fa-border-all"></i> Dashboard
            </a>
            <a href="/statistik" class="{{ request()->is('statistik') ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-800 font-medium' }} px-5 py-2 rounded-full text-sm transition-all duration-300 flex items-center gap-2">
                <i class="fa-solid fa-chart-simple"></i> Statistik
            </a>
            <a href="/logbook" class="{{ request()->is('logbook') ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-800 font-medium' }} px-5 py-2 rounded-full text-sm transition-all duration-300 flex items-center gap-2">
                <i class="fa-solid fa-book-open"></i> Logbook
            </a>
            
            <!-- Menu Terproteksi -->
            @auth
            <a href="/cycle" class="{{ request()->is('cycle') ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-800 font-medium' }} px-5 py-2 rounded-full text-sm transition-all duration-300 flex items-center gap-2">
                <i class="fa-solid fa-rotate"></i> Siklus
            </a>
            <a href="/control" class="{{ request()->is('control') ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-800 font-medium' }} px-5 py-2 rounded-full text-sm transition-all duration-300 flex items-center gap-2">
                <i class="fa-solid fa-sliders"></i> Kontrol
            </a>
            @endauth
        </div>

        <!-- PROFILE & LOGIN/LOGOUT -->
        <div class="flex items-center gap-3 relative">
            <!-- Tombol Tentang (selalu tampil) -->
            <button onclick="openAboutModal()" class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-200 text-gray-500 bg-white hover:bg-gray-50 transition-all hover:shadow-sm" title="Tentang">
                <i class="fa-solid fa-circle-info"></i>
            </button>

            @auth
                <!-- Dropdown Notifikasi -->
                <div class="relative group" id="notifContainer">
                    <button onclick="toggleNotifDropdown()" class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-200 text-gray-500 bg-white hover:bg-gray-50 transition-all hover:shadow-sm relative">
                        <i class="fa-regular fa-bell"></i>
                        <!-- Titik Merah (Badge) -->
                        <span id="notifBadge" class="absolute top-0 right-0 flex h-3 w-3 hidden">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 m-[1px]"></span>
                        </span>
                    </button>

                    <!-- Kotak List Notifikasi (Sembunyi by default) -->
                    <div id="notifDropdown" class="absolute top-full right-0 mt-3 w-80 bg-white border border-gray-100 shadow-xl rounded-2xl overflow-hidden hidden z-50 transform origin-top-right transition-all">
                        <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                            <h3 class="font-bold text-gray-800 text-sm">Notifikasi Baru</h3>
                            <button onclick="markAllAsRead()" class="text-[10px] font-bold text-amber-500 hover:text-amber-600 uppercase tracking-wider">Tandai Dibaca</button>
                        </div>
                        <div id="notifList" class="max-h-80 overflow-y-auto bg-white custom-scrollbar">
                            <!-- Diisi oleh JavaScript nanti -->
                            <div class="p-6 text-center text-sm text-gray-400">Mengecek data...</div>
                        </div>
                    </div>
                </div>

                <!-- Tampil Jika Sudah Login -->
                <div id="profileDropdown" class="flex items-center gap-2 bg-white pr-4 pl-1 py-1 rounded-full border border-gray-200 hover:shadow-sm transition-all relative cursor-pointer" onclick="toggleLogoutDropdown()">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=f59e0b&color=fff&bold=true" alt="Profile" class="w-8 h-8 rounded-full" width="32" height="32">
                    <span class="text-sm font-bold text-gray-700 hidden md:block">{{ Auth::user()->name }}</span>
                    
                    <!-- Dropdown Logout -->
                    <div id="logoutDropdown" class="absolute top-full right-0 mt-2 w-48 bg-white border border-gray-100 shadow-lg rounded-xl overflow-hidden hidden">
                        <button onclick="openAboutModal()" class="w-full text-left px-4 py-3 text-sm text-gray-600 hover:bg-gray-50 font-medium flex items-center gap-2 border-b border-gray-100">
                            <i class="fa-solid fa-circle-info"></i> Tentang
                        </button>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 font-bold flex items-center gap-2">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <!-- Tampil Jika Belum Login (Publik) -->
                <a href="{{ route('login') }}" class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-full text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>
            @endauth
        </div>
    </nav>

    <!-- NAVBAR MOBILE -->
    <nav class="lg:hidden fixed bottom-4 left-4 right-4 z-50 bg-gray-50/95 backdrop-blur-xl p-1.5 rounded-[2rem] border border-gray-200 shadow-[0_8px_30px_rgb(0,0,0,0.12)] flex justify-between items-center">
        <a href="/" class="flex-1 flex flex-col items-center justify-center gap-1 py-2.5 rounded-full transition-all duration-300 {{ request()->is('/') ? 'bg-white shadow-sm text-amber-500 font-bold' : 'text-gray-400 hover:text-gray-600 font-medium' }}">
            <i class="fa-solid fa-border-all text-lg mb-0.5"></i>
            <span class="text-[10px] tracking-wide">Dashboard</span>
        </a>
        <a href="/statistik" class="flex-1 flex flex-col items-center justify-center gap-1 py-2.5 rounded-full transition-all duration-300 {{ request()->is('statistik') ? 'bg-white shadow-sm text-amber-500 font-bold' : 'text-gray-400 hover:text-gray-600 font-medium' }}">
            <i class="fa-solid fa-chart-simple text-lg mb-0.5"></i>
            <span class="text-[10px] tracking-wide">Statistik</span>
        </a>
        <a href="/logbook" class="flex-1 flex flex-col items-center justify-center gap-1 py-2.5 rounded-full transition-all duration-300 {{ request()->is('logbook') ? 'bg-white shadow-sm text-amber-500 font-bold' : 'text-gray-400 hover:text-gray-600 font-medium' }}">
            <i class="fa-solid fa-book-open text-lg mb-0.5"></i>
            <span class="text-[10px] tracking-wide">Logbook</span>
        </a>
        
        @auth
        <a href="/cycle" class="flex-1 flex flex-col items-center justify-center gap-1 py-2.5 rounded-full transition-all duration-300 {{ request()->is('cycle') ? 'bg-white shadow-sm text-amber-500 font-bold' : 'text-gray-400 hover:text-gray-600 font-medium' }}">
            <i class="fa-solid fa-rotate text-lg mb-0.5"></i>
            <span class="text-[10px] tracking-wide">Siklus</span>
        </a>
        <a href="/control" class="flex-1 flex flex-col items-center justify-center gap-1 py-2.5 rounded-full transition-all duration-300 {{ request()->is('control') ? 'bg-white shadow-sm text-amber-500 font-bold' : 'text-gray-400 hover:text-gray-600 font-medium' }}">
            <i class="fa-solid fa-sliders text-lg mb-0.5"></i>
            <span class="text-[10px] tracking-wide">Kontrol</span>
        </a>
        @endauth
    </nav>

    <main class="pt-28 pb-28 lg:pb-12 px-4 sm:px-6 max-w-screen-2xl mx-auto relative z-30">
        <div class="mb-6 flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-black text-gray-800 tracking-tight">@yield('title')</h1>
                <p class="text-sm text-gray-500 mt-1 hidden sm:block">Sistem Informasi Manajemen Budidaya Maggot TPST Undip</p>
            </div>
        </div>

        @yield('content')
    </main>

    <!-- Global Toast Container (Tempat pop-up muncul) -->
    <div id="toast-container" class="fixed top-24 right-4 z-[100] flex flex-col gap-3 pointer-events-none"></div>

    @stack('scripts')

    <!-- Script Global Alert & Dropdown Khusus Pengelola (Auth) -->
    @auth
    <script>
        // Variabel global untuk notifikasi
        var readAlerts = JSON.parse(localStorage.getItem('siMaggotReadAlerts') || '[]');
        var toastedAlerts = [];
        var currentAlerts = [];

        // Fungsi Buka/Tutup Dropdown (global, dipanggil dari onclick)
        function toggleNotifDropdown() {
            const dropdown = document.getElementById('notifDropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        }

        // Fungsi Tandai Satu Dibaca (global, dipanggil dari onclick)
        function markAsRead(id) {
            if (!readAlerts.includes(id)) {
                readAlerts.push(id);
                if (readAlerts.length > 100) readAlerts.shift();
                localStorage.setItem('siMaggotReadAlerts', JSON.stringify(readAlerts));
            }
            renderNotifList();
        }

        // Fungsi Tandai Semua Dibaca (global, dipanggil dari onclick)
        function markAllAsRead() {
            currentAlerts.forEach(alert => {
                if (!readAlerts.includes(alert.id)) readAlerts.push(alert.id);
            });
            localStorage.setItem('siMaggotReadAlerts', JSON.stringify(readAlerts));
            renderNotifList();
            toggleNotifDropdown();
        }

        // Render HTML untuk Dropdown
        function renderNotifList() {
            const list = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            if (!list || !badge) return;

            const unreadAlerts = currentAlerts.filter(a => !readAlerts.includes(a.id));

            if (unreadAlerts.length > 0) {
                badge.classList.remove('hidden');
                let html = '';
                unreadAlerts.forEach(alert => {
                    let iconClass = alert.type === 'danger' ? 'text-red-500 bg-red-50' : 
                                    (alert.type === 'warning' ? 'text-amber-500 bg-amber-50' : 'text-blue-500 bg-blue-50');
                    let iconObj = alert.type === 'danger' ? 'fa-triangle-exclamation' : 
                                    (alert.type === 'warning' ? 'fa-bell' : 'fa-circle-info');
                                    
                    html += `
                    <div class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors flex gap-3 relative group">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 ${iconClass}">
                            <i class="fa-solid ${iconObj} text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-xs font-bold text-gray-800 mb-0.5">${alert.title}</h4>
                            <p class="text-[10px] text-gray-500 leading-snug pr-4">${alert.message}</p>
                            <span class="text-[9px] text-gray-400 mt-1 block"><i class="fa-regular fa-clock"></i> ${alert.time}</span>
                        </div>
                        <button onclick="markAsRead('${alert.id}')" class="absolute right-4 top-4 w-6 h-6 bg-white border border-gray-200 rounded text-gray-300 hover:text-green-500 hover:border-green-300 opacity-0 group-hover:opacity-100 transition-all shadow-sm flex items-center justify-center" title="Tandai dibaca">
                            <i class="fa-solid fa-check text-[10px]"></i>
                        </button>
                    </div>
                    `;
                });
                list.innerHTML = html;
            } else {
                badge.classList.add('hidden');
                list.innerHTML = `
                    <div class="p-8 flex flex-col items-center justify-center text-center">
                        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-3 text-xl">
                            <i class="fa-solid fa-check-double"></i>
                        </div>
                        <p class="text-xs font-bold text-gray-500">Semua Beres!</p>
                        <p class="text-[10px] text-gray-400 mt-1">Tidak ada notifikasi baru untukmu.</p>
                    </div>
                `;
            }
        }

        // Tampilkan Toast
        function showToast(alert) {
            const container = document.getElementById('toast-container');
            let colors = alert.type === 'danger' ? 'bg-red-50 border-red-200 text-red-800' : 
                        (alert.type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-800');
            let icon = alert.type === 'danger' ? '<i class="fa-solid fa-triangle-exclamation text-red-500 text-xl"></i>' : 
                       (alert.type === 'warning' ? '<i class="fa-solid fa-bell text-amber-500 text-xl"></i>' : '<i class="fa-solid fa-circle-info text-blue-500 text-xl"></i>');

            const toast = document.createElement('div');
            toast.className = `flex items-start gap-3 p-4 rounded-2xl border shadow-lg transform transition-all duration-500 translate-x-full opacity-0 pointer-events-auto w-80 sm:w-96 ${colors}`;
            toast.innerHTML = `
                <div class="shrink-0 mt-0.5">${icon}</div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold mb-0.5">${alert.title}</h4>
                    <p class="text-xs opacity-90 leading-relaxed">${alert.message}</p>
                </div>
                <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fa-solid fa-xmark"></i></button>
            `;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-full', 'opacity-0'));
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 500);
            }, 8000);
        }

        // Ambil Data dari API
        function fetchAlerts() {
            fetch('/api/check-alerts')
                .then(response => response.json())
                .then(data => {
                    if (data.alerts) {
                        currentAlerts = data.alerts;
                        renderNotifList();

                        const unreadAlerts = currentAlerts.filter(a => !readAlerts.includes(a.id));
                        unreadAlerts.forEach(alert => {
                            if (!toastedAlerts.includes(alert.id)) {
                                showToast(alert);
                                toastedAlerts.push(alert.id);
                            }
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Inisialisasi saat DOM siap
        document.addEventListener('DOMContentLoaded', function() {
            // Tutup dropdown jika user klik di luar area notifikasi
            document.addEventListener('click', function(event) {
                const container = document.getElementById('notifContainer');
                const dropdown = document.getElementById('notifDropdown');
                if (container && dropdown && !container.contains(event.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            // Jalankan polling
            setTimeout(fetchAlerts, 2000); 
            setInterval(fetchAlerts, 60000);
        });
    </script>
    @endauth

    <!-- 0. Load Alpine.js & Bootstrap JS (Vite) -->
    @vite('resources/js/app.js')

    <!-- 2. Script Universal (Auto-Refresh untuk Publik & Admin) -->
    <script>
        // Fungsi toggle dropdown logout
        function toggleLogoutDropdown() {
            const dropdown = document.getElementById('logoutDropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        // Fungsi buka modal About
        function openAboutModal() {
            const modal = document.getElementById('aboutModal');
            const dropdown = document.getElementById('logoutDropdown');
            if (modal) modal.style.display = 'flex';
            if (dropdown) dropdown.classList.add('hidden');
        }

        // Fungsi tutup modal About
        function closeAboutModal() {
            const modal = document.getElementById('aboutModal');
            if (modal) modal.style.display = 'none';
        }

        // Tutup dropdown logout & modal about saat klik di luar
        document.addEventListener('click', function(event) {
            const profile = document.getElementById('profileDropdown');
            const logoutDropdown = document.getElementById('logoutDropdown');
            const aboutModal = document.getElementById('aboutModal');
            
            // Jangan tutup jika klik di dalam profile atau modal
            if (profile && profile.contains(event.target)) return;
            if (aboutModal && aboutModal.style.display === 'flex' && event.target.closest('#aboutModal .bg-white')) return;
            
            if (logoutDropdown && !logoutDropdown.classList.contains('hidden')) {
                logoutDropdown.classList.add('hidden');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
        const autoRefreshPages = ['/', '/statistik', '/logbook']; 
        const currentPath = window.location.pathname;

        if (autoRefreshPages.includes(currentPath)) {
            setTimeout(() => {
                // Jangan refresh jika ada Modal yang sedang terbuka (mencegah kehilangan input data)
                const isModalOpen = document.querySelector('.flex[id^="modal"]:not(.hidden)'); 
                
                if (!isModalOpen) {
                    window.location.reload();
                } else {
                    console.log("Refresh ditunda: Modal sedang terbuka.");
                    const retry = setInterval(() => {
                        if (!document.querySelector('.flex[id^="modal"]:not(.hidden)')) {
                            window.location.reload();
                            clearInterval(retry);
                        }
                    }, 30000); // Cek ulang setiap 30 detik
                }
            }, 60000); // Interval Refresh: 1 Menit
        }
        }); // END DOMContentLoaded
    </script>

    <!-- About Modal -->
    <div id="aboutModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);" onclick="closeAboutModal()">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full mx-4 p-8 text-center relative" onclick="event.stopPropagation()" style="animation: fadeIn 0.2s ease-out;">
            <button onclick="closeAboutModal()" class="absolute top-4 right-4 w-8 h-8 bg-gray-100 hover:bg-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <img src="{{ asset('icon.png') }}" alt="SiMaggot" class="w-20 h-20 object-contain mx-auto mb-4">
            <h2 class="font-extrabold text-2xl text-gray-800 tracking-tight mb-1">Si<span class="text-amber-500">Maggot</span></h2>
            <p class="text-xs text-gray-400 mb-6">Bagian dari Tugas Akhir</p>
            
            <p class="text-sm font-semibold text-gray-700 leading-relaxed mb-6 px-2">
                RANCANG BANGUN SMART VERTICAL BIOPOND BUDIDAYA MAGGOT BSF BERBASIS IOT UNTUK PENGENDALIAN PARAMETER LINGKUNGAN PADA BIOKONVERSI DI TPST UNDIP
            </p>
            
            <div class="space-y-1.5 mb-6 text-left bg-gray-50 rounded-2xl p-4">
                <p class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-2">Pembimbing</p>
                <p class="text-xs text-gray-600"><span class="font-medium text-gray-800">Pembimbing 1:</span> Ir. M. Arfan S.Kom., M.Eng.</p>
                <p class="text-xs text-gray-600"><span class="font-medium text-gray-800">Pembimbing 2:</span> Ir. Budi Setiyono S.T., M.T.</p>
                <p class="text-xs text-gray-600"><span class="font-medium text-gray-800">Pembimbing 3:</span> Dr. Vivi Endar Herawati, S.Pi., M.Si.</p>
            </div>
            
            <div class="space-y-1.5 text-left bg-amber-50 rounded-2xl p-4">
                <p class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-2">Mahasiswa</p>
                <p class="text-xs text-gray-600"><span class="font-medium text-gray-800">Mahasiswa 1:</span> Farhan Hanif Rahmansyah (21060122120002)</p>
                <p class="text-xs text-gray-600"><span class="font-medium text-gray-800">Mahasiswa 2:</span> Yusuf Nadim Irawan (21060122120029)</p>
                <p class="text-xs text-gray-600"><span class="font-medium text-gray-800">Mahasiswa 3:</span> Noor Aqila Zayyana Fikki (21060122120037)</p>
            </div>
        </div>
    </div>

</body>
</html>