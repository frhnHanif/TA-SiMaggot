<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SiMaggot Dashboard')</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#f59e0b',
                        background: '#F8F9FA'
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F8F9FA; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        @media (max-width: 640px) {
        html {
            font-size: 12px;
        }
        }
    </style>
</head>
<body class="text-gray-800 antialiased font-sans relative">

    <!-- NAVBAR DESKTOP & PROFILE -->
    <nav class="fixed top-4 left-4 right-4 z-50 bg-white/90 backdrop-blur-md shadow-sm border border-gray-100 rounded-[2rem] px-4 sm:px-6 py-3 flex justify-between items-center max-w-screen-2xl mx-auto">
        
       <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-amber-400 to-amber-600 rounded-full flex items-center justify-center text-white shadow-inner">
                <i class="fa-solid fa-worm"></i>
            </div>
            <span class="font-extrabold text-xl text-gray-800 tracking-tight">Si<span class="text-amber-500">Maggot</span></span>
        </div>

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
                <div class="flex items-center gap-2 bg-white pr-4 pl-1 py-1 rounded-full border border-gray-200 hover:shadow-sm transition-all group relative cursor-pointer">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=f59e0b&color=fff&bold=true" alt="Profile" class="w-8 h-8 rounded-full">
                    <span class="text-sm font-bold text-gray-700 hidden md:block">{{ Auth::user()->name }}</span>
                    
                    <!-- Dropdown Logout -->
                    <div class="absolute top-full right-0 mt-2 w-32 bg-white border border-gray-100 shadow-lg rounded-xl overflow-hidden hidden group-hover:block">
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
        // 1. Inisialisasi Data dari Local Storage
        let readAlerts = JSON.parse(localStorage.getItem('siMaggotReadAlerts') || '[]');
        let toastedAlerts = []; // Mencegah pop-up Toast muncul berulang di sesi yang sama
        let currentAlerts = []; // Menampung hasil fetch terbaru

        // 2. Fungsi Buka/Tutup Dropdown
        function toggleNotifDropdown() {
            const dropdown = document.getElementById('notifDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Tutup dropdown jika user klik di luar area notifikasi
        document.addEventListener('click', function(event) {
            const container = document.getElementById('notifContainer');
            const dropdown = document.getElementById('notifDropdown');
            if (container && !container.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // 3. Fungsi Tandai Satu Dibaca
        function markAsRead(id) {
            if (!readAlerts.includes(id)) {
                readAlerts.push(id);
                // Batasi memori agar tidak penuh (simpan 100 ID terakhir saja)
                if (readAlerts.length > 100) readAlerts.shift();
                localStorage.setItem('siMaggotReadAlerts', JSON.stringify(readAlerts));
            }
            renderNotifList(); // Refresh tampilan
        }

        // 4. Fungsi Tandai Semua Dibaca
        function markAllAsRead() {
            currentAlerts.forEach(alert => {
                if (!readAlerts.includes(alert.id)) readAlerts.push(alert.id);
            });
            localStorage.setItem('siMaggotReadAlerts', JSON.stringify(readAlerts));
            renderNotifList();
            toggleNotifDropdown(); // Tutup dropdown
        }

        // 5. Render HTML untuk Dropdown
        function renderNotifList() {
            const list = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            
            // Filter hanya notif yang belum dibaca
            const unreadAlerts = currentAlerts.filter(a => !readAlerts.includes(a.id));

            if (unreadAlerts.length > 0) {
                badge.classList.remove('hidden'); // Munculkan titik merah
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
                badge.classList.add('hidden'); // Sembunyikan titik merah
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

        // 6. Tampilkan Toast (Tetap dipertahankan)
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

        // 7. Ambil Data dari API
        function fetchAlerts() {
            fetch('/api/check-alerts')
                .then(response => response.json())
                .then(data => {
                    if (data.alerts) {
                        currentAlerts = data.alerts;
                        renderNotifList(); // Update Dropdown & Badge

                        // Cek apakah ada notif baru yang belum ditandai dibaca DAN belum pernah di-toast
                        const unreadAlerts = currentAlerts.filter(a => !readAlerts.includes(a.id));
                        unreadAlerts.forEach(alert => {
                            if (!toastedAlerts.includes(alert.id)) {
                                showToast(alert); // Munculkan Pop-up
                                toastedAlerts.push(alert.id); // Catat agar tidak pop-up lagi
                            }
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Jalankan polling
        setTimeout(fetchAlerts, 2000); 
        setInterval(fetchAlerts, 60000); 
    </script>
    @endauth

    <!-- 2. Script Universal (Auto-Refresh untuk Publik & Admin) -->
    <script>
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
    </script>

</body>
</html>