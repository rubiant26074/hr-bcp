@extends('mobile.layout')

@php($showNav = true)
@php($activeTab = 'attendance')

@section('content')
<h5 class="fw-bold mb-3">Absensi Mobile</h5>

<div class="card card-clean mb-3">
  <div class="card-body">
    <div class="small text-muted mb-2">Daftar wajah dan absensi dilakukan di mode native APK (kamera + GPS) dengan sistem backend yang sama.</div>
    <button class="btn btn-dark w-100" type="button" id="btnOpenNative">Buka Absensi Native</button>
    <div id="nativeStatus" class="small mt-2 text-muted">Siap.</div>
  </div>
</div>

<div class="card card-clean">
  <div class="card-body">
    <div class="fw-semibold mb-2">Riwayat Terakhir</div>
    @if ($logs->isEmpty())
      <div class="text-muted small">Belum ada riwayat absensi.</div>
    @else
      @foreach ($logs as $log)
        <div class="py-2 border-bottom">
          <div class="fw-semibold small">{{ date('d M Y H:i', strtotime((string) $log->scan_time)) }}</div>
          <div class="small text-muted">{{ strtoupper((string) $log->verify_type) }}</div>
        </div>
      @endforeach
    @endif
  </div>
</div>

<script>
  (function () {
    var btn = document.getElementById('btnOpenNative');
    var status = document.getElementById('nativeStatus');
    if (!btn) return;

    function setStatus(msg) {
      if (status) status.textContent = msg;
    }

    function openNative() {
      try {
        if (window.AndroidHRBCP && typeof window.AndroidHRBCP.openNativeAttendance === 'function') {
          window.AndroidHRBCP.openNativeAttendance();
          setStatus('Membuka kamera native...');
          return true;
        }
      } catch (e) {
        // ignore
      }
      return false;
    }

    btn.addEventListener('click', function () {
      var ok = openNative();
      if (!ok) {
        setStatus('Mode native hanya tersedia di APK HR-BCP Android.');
      }
    });

    var params = new URLSearchParams(window.location.search);
    if (params.get('native') === '1') {
      var opened = openNative();
      if (!opened) {
        setStatus('APK native tidak terdeteksi di perangkat ini.');
      }
    }
  })();
</script>
@endsection
