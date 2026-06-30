<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Maggot</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f9; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #007bff; color: white; }
    </style>
</head>
<body>

    <h2>Dashboard Monitoring Lingkungan & Biopond</h2>


    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h3>🎛️ Panel Kontrol Manual</h3>
        
        <div style="margin-bottom: 20px;">
            <label><strong>Kecepatan Kipas (Exhaust)</strong></label><br>
            <input type="range" id="fanSlider" min="0" max="100" value="{{ $control->fan }}" style="width: 300px;" oninput="document.getElementById('fanValue').innerText = this.value + '%'">
            <span id="fanValue" style="font-weight: bold; margin-left: 10px;">{{ $control->fan }}%</span>
            <button onclick="updateFan()" style="margin-left: 10px; padding: 5px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Set Kipas</button>
        </div>

        <div>
            <label><strong>Mist Maker (1 - 6)</strong></label><br>
            @foreach($control->mist as $index => $state)
                <button id="mistBtn_{{ $index }}" 
                        onclick="toggleMist({{ $index }}, {{ $state }})" 
                        style="margin-right: 10px; padding: 10px 20px; font-weight: bold; border: none; border-radius: 5px; cursor: pointer; background-color: {{ $state == 1 ? '#28a745' : '#dc3545' }}; color: white;">
                    Mist {{ $index + 1 }} : {{ $state == 1 ? 'ON' : 'OFF' }}
                </button>
            @endforeach
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Fungsi untuk update nilai Kipas
        function updateFan() {
            const fanVal = document.getElementById('fanSlider').value;
            fetch('/web-control', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ fan: fanVal })
            }).then(response => {
                if(response.ok) alert('Kecepatan kipas diatur ke ' + fanVal + '%');
            });
        }

        // Fungsi untuk nyala/matikan Mist Maker
        function toggleMist(index, currentState) {
            const newState = currentState === 1 ? 0 : 1; // Balikkan status
            
            fetch('/web-control', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ mist_index: index, mist_value: newState })
            }).then(response => {
                if(response.ok) {
                    // Update tampilan tombol secara langsung
                    const btn = document.getElementById('mistBtn_' + index);
                    btn.style.backgroundColor = newState === 1 ? '#28a745' : '#dc3545';
                    btn.innerText = 'Mist ' + (index + 1) + ' : ' + (newState === 1 ? 'ON' : 'OFF');
                    // Update fungsi onclick agar bisa ditekan lagi dengan status baru
                    btn.setAttribute('onclick', `toggleMist(${index}, ${newState})`);
                }
            });
        }
    </script>
    
    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Biopond 1-6 (gram)</th>
                <th>Panen (gram)</th>
                <th>Suhu (&deg;C)</th>
                <th>Kelembaban Udara (%)</th>
                <th>Kelembaban Tanah (%)</th>
                <th>Amonia (ppm)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sensors as $data)
            <tr>
                <td>{{ $data->created_at->format('H:i:s d-m-Y') }}</td>
                <td>{{ implode(', ', $data->biopond) }}</td>
                <td>{{ $data->harvest }}</td>
                <td>{{ $data->temp }}</td>
                <td>{{ $data->hum }}</td>
                <td>{{ $data->soil }}</td>
                <td>{{ $data->ammonia }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>