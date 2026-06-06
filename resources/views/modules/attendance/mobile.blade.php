@extends('layouts.app')

@section('content')
<h4 class="mb-3">Absensi Mobile (Face + Geotag)</h4>
<div class="mb-3">
  <button type="button" class="btn btn-dark btn-sm" id="btnOpenNativeFlow">Buka Absensi Native (APK)</button>
  <div class="small text-muted mt-1">Jika memakai APK HR-BCP Android, gunakan mode native agar deteksi wajah lebih stabil.</div>
</div>

@if (!empty($company))
  @php
    $workDays = [];
    if (!empty($company->work_days_json)) {
      $decoded = json_decode($company->work_days_json, true);
      if (is_array($decoded)) {
        $workDays = $decoded;
      }
    }
    $dayLabels = [
      'Mon' => 'Senin',
      'Tue' => 'Selasa',
      'Wed' => 'Rabu',
      'Thu' => 'Kamis',
      'Fri' => 'Jumat',
      'Sat' => 'Sabtu',
      'Sun' => 'Minggu',
    ];
    $workDaysText = [];
    foreach ($workDays as $d) {
      $workDaysText[] = $dayLabels[$d] ?? $d;
    }
  @endphp
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 small">
        <div class="col-md-3"><strong>Jam Kerja:</strong> {{ $company->work_time_start ? format_time_id($company->work_time_start) : '-' }} - {{ $company->work_time_end ? format_time_id($company->work_time_end) : '-' }}</div>
        <div class="col-md-3"><strong>Durasi:</strong> {{ $company->work_duration_hours ?? '-' }} jam</div>
        <div class="col-md-3"><strong>Hari / Minggu:</strong> {{ $company->work_days_per_week ?? '-' }}</div>
        <div class="col-md-3"><strong>Hari Kerja:</strong> {{ count($workDaysText) ? implode(', ', $workDaysText) : '-' }}</div>
      </div>
    </div>
  </div>
@endif

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="ratio ratio-4x3 bg-dark rounded overflow-hidden">
          <video id="video" autoplay muted playsinline style="width:100%; height:100%; object-fit:cover;"></video>
        </div>
        <div class="d-flex gap-2 mt-3 flex-wrap">
          <button class="btn btn-outline-secondary" id="btnInitPermission">Aktifkan Kamera & Lokasi</button>
          <button class="btn btn-outline-dark" id="btnSwitchCamera">Ganti Kamera</button>
          <button class="btn btn-outline-info" id="btnCheckLocation">Cek Lokasi</button>
          <button class="btn btn-outline-primary" id="btnEnroll">Daftarkan Wajah</button>
          <button class="btn btn-success" id="btnCheckin">Absen Masuk/Keluar</button>
        </div>
        <div class="small text-muted mt-2" id="statusText">Siap.</div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Panduan</div>
        <ul class="small text-muted">
          <li>Izinkan kamera dan lokasi pada browser HP.</li>
          <li>Pastikan wajah terlihat jelas dan terang.</li>
          <li>Absensi hanya bisa di radius lokasi perusahaan.</li>
        </ul>
        <div class="alert alert-warning small mt-2">
          Jika belum daftar wajah, klik <strong>Daftarkan Wajah</strong> terlebih dahulu.
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
  (function () {
    var video = document.getElementById('video');
    var statusText = document.getElementById('statusText');
    var btnInitPermission = document.getElementById('btnInitPermission');
    var btnSwitchCamera = document.getElementById('btnSwitchCamera');
    var btnCheckLocation = document.getElementById('btnCheckLocation');
    var btnEnroll = document.getElementById('btnEnroll');
    var btnCheckin = document.getElementById('btnCheckin');
    var btnOpenNativeFlow = document.getElementById('btnOpenNativeFlow');
    var storedDescriptor = null;
    var currentFacingMode = 'user';
    var detectOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.35 });

    function setStatus(msg) {
      if (statusText) statusText.textContent = msg;
    }

    function openNativeFlowIfAvailable() {
      try {
        if (window.AndroidHRBCP && typeof window.AndroidHRBCP.openNativeAttendance === 'function') {
          window.AndroidHRBCP.openNativeAttendance();
          return true;
        }
      } catch (e) {
        // ignore
      }
      return false;
    }

    async function loadModels() {
      var base = '{{ asset('face-api/weights') }}';
      await faceapi.nets.tinyFaceDetector.loadFromUri(base);
      await faceapi.nets.faceLandmark68Net.loadFromUri(base);
      await faceapi.nets.faceRecognitionNet.loadFromUri(base);
    }

    function stopCamera() {
      if (video && video.srcObject) {
        var tracks = video.srcObject.getTracks();
        tracks.forEach(function (t) { t.stop(); });
        video.srcObject = null;
      }
    }

    async function startCamera(forceFacingMode) {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Kamera tidak didukung browser ini.');
      }
      if (forceFacingMode) {
        currentFacingMode = forceFacingMode;
      }
      stopCamera();
      var stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: currentFacingMode } },
        audio: false
      });
      video.srcObject = stream;
    }

    function estimateBrightness() {
      try {
        var c = document.createElement('canvas');
        c.width = 64;
        c.height = 64;
        var ctx = c.getContext('2d');
        if (!ctx) return 128;
        ctx.drawImage(video, 0, 0, c.width, c.height);
        var img = ctx.getImageData(0, 0, c.width, c.height).data;
        var sum = 0;
        var px = img.length / 4;
        for (var i = 0; i < img.length; i += 4) {
          sum += (img[i] + img[i + 1] + img[i + 2]) / 3;
        }
        return px > 0 ? (sum / px) : 128;
      } catch (e) {
        return 128;
      }
    }

    function evaluateFaceQuality(detection) {
      if (!detection || !detection.detection || !detection.detection.box) {
        return { ok: false, message: 'Wajah tidak terdeteksi. Posisikan wajah di tengah kamera.' };
      }
      var box = detection.detection.box;
      var vw = Math.max(1, video.videoWidth || video.clientWidth || 1);
      var vh = Math.max(1, video.videoHeight || video.clientHeight || 1);
      var areaRatio = (box.width * box.height) / (vw * vh);
      var cx = box.x + (box.width / 2);
      var cy = box.y + (box.height / 2);
      var centerDx = Math.abs((cx / vw) - 0.5);
      var centerDy = Math.abs((cy / vh) - 0.5);
      var brightness = estimateBrightness();

      if (brightness < 55) {
        return { ok: false, message: 'Terlalu gelap. Tambah pencahayaan wajah.' };
      }
      if (areaRatio < 0.08) {
        return { ok: false, message: 'Wajah terlalu jauh. Dekatkan kamera sekitar 30-50 cm.' };
      }
      if (areaRatio > 0.55) {
        return { ok: false, message: 'Wajah terlalu dekat. Jauhkan sedikit dari kamera.' };
      }
      if (centerDx > 0.22 || centerDy > 0.22) {
        return { ok: false, message: 'Posisikan wajah lebih ke tengah frame.' };
      }
      return { ok: true, message: 'Wajah terdeteksi baik.' };
    }

    async function sleep(ms) {
      return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    async function getDescriptorWithRetry(maxAttempts) {
      var attempts = Math.max(1, maxAttempts || 4);
      var lastMessage = 'Wajah tidak terdeteksi.';
      for (var i = 1; i <= attempts; i++) {
        var detection = await faceapi
          .detectSingleFace(video, detectOptions)
          .withFaceLandmarks()
          .withFaceDescriptor();

        var quality = evaluateFaceQuality(detection);
        if (quality.ok && detection && detection.descriptor) {
          return { descriptor: Array.from(detection.descriptor), message: quality.message };
        }

        lastMessage = quality.message + ' (Percobaan ' + i + '/' + attempts + ')';
        setStatus(lastMessage);
        await sleep(220);
      }
      return { descriptor: null, message: lastMessage };
    }

    async function getDescriptor() {
      var result = await getDescriptorWithRetry(5);
      if (!result.descriptor) {
        setStatus(result.message);
        return null;
      }
      return result.descriptor;
    }

    function getLocation() {
      return new Promise(function (resolve, reject) {
        if (!navigator.geolocation) return reject(new Error('Geolocation tidak tersedia.'));
        navigator.geolocation.getCurrentPosition(function (pos) {
          resolve({
            lat: pos.coords.latitude,
            lng: pos.coords.longitude
          });
        }, function (err) {
          reject(err);
        }, { enableHighAccuracy: true, timeout: 8000 });
      });
    }

    async function ensurePermissionsByGesture() {
      await startCamera(currentFacingMode);
      await getLocation();
    }

    async function fetchProfile() {
      var res = await fetch('{{ route('attendance.face_profile') }}', { credentials: 'same-origin' });
      var data = await res.json();
      if (data && data.descriptor) {
        storedDescriptor = data.descriptor;
      }
    }

    async function postJson(url, payload) {
      var res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
      });
      return res.json();
    }

    btnEnroll.addEventListener('click', async function () {
      try {
        setStatus('Memindai wajah (dengan optimasi deteksi)...');
        var desc = await getDescriptor();
        if (!desc) {
          return;
        }
        var resp = await postJson('{{ route('attendance.face_enroll') }}', { descriptor: desc });
        if (resp.ok) {
          storedDescriptor = desc;
          setStatus(resp.message || 'Wajah tersimpan.');
        } else {
          setStatus(resp.message || 'Gagal menyimpan wajah.');
        }
      } catch (e) {
        setStatus('Gagal: ' + e.message);
      }
    });

    btnCheckin.addEventListener('click', async function () {
      try {
        setStatus('Memindai wajah & lokasi (dengan optimasi deteksi)...');
        var desc = await getDescriptor();
        if (!desc) {
          return;
        }
        var loc = await getLocation();
        var resp = await postJson('{{ route('attendance.face_checkin') }}', { descriptor: desc, lat: loc.lat, lng: loc.lng });
        if (resp.ok) {
          setStatus(resp.message || 'Absensi berhasil.');
        } else {
          setStatus(resp.message || 'Absensi gagal.');
        }
      } catch (e) {
        setStatus('Gagal: ' + e.message);
      }
    });

    btnInitPermission.addEventListener('click', async function () {
      try {
        setStatus('Meminta izin kamera & lokasi...');
        await ensurePermissionsByGesture();
        setStatus('Izin kamera & lokasi aktif.');
      } catch (e) {
        setStatus('Izin belum aktif: ' + e.message + '. Jika tetap gagal, buka Permission aplikasi (Camera/Location) lalu izinkan.');
      }
    });

    btnSwitchCamera.addEventListener('click', async function () {
      try {
        currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
        await startCamera(currentFacingMode);
        setStatus('Kamera aktif: ' + (currentFacingMode === 'environment' ? 'Belakang' : 'Depan'));
      } catch (e) {
        setStatus('Gagal ganti kamera: ' + e.message);
      }
    });

    btnCheckLocation.addEventListener('click', async function () {
      try {
        var loc = await getLocation();
        setStatus('Lokasi terdeteksi: ' + Number(loc.lat).toFixed(6) + ', ' + Number(loc.lng).toFixed(6));
      } catch (e) {
        setStatus('Lokasi gagal: ' + e.message + '. Izinkan Location di Permission aplikasi.');
      }
    });

    if (btnOpenNativeFlow) {
      btnOpenNativeFlow.addEventListener('click', function () {
        var opened = openNativeFlowIfAvailable();
        if (!opened) {
          setStatus('Mode native hanya tersedia di APK HR-BCP Android terbaru.');
        }
      });
    }

    (async function init() {
      try {
        var params = new URLSearchParams(window.location.search || '');
        var preferNative = params.get('native') === '1';
        if (preferNative) {
          var opened = openNativeFlowIfAvailable();
          if (opened) {
            setStatus('Mengalihkan ke mode native...');
            return;
          }
        }
        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
          setStatus('Kamera & lokasi butuh HTTPS. Buka lewat HTTPS atau localhost.');
          btnEnroll.disabled = true;
          btnCheckin.disabled = true;
          return;
        }
        setStatus('Memuat model...');
        await loadModels();
        // Beberapa WebView APK butuh user gesture untuk permission kamera/lokasi.
        // Maka inisialisasi awal tidak memaksa akses kamera.
        await fetchProfile();
        setStatus('Siap. Klik "Aktifkan Kamera & Lokasi". ' + (storedDescriptor ? 'Wajah terdaftar.' : 'Wajah belum terdaftar.'));
      } catch (e) {
        setStatus('Gagal inisialisasi: ' + e.message);
      }
    })();
  })();
</script>
@endsection
