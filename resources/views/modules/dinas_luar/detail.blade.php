@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0">Detail Dinas Luar</h4>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('dinas_luar.index') }}">Kembali</a>
    <a class="btn btn-outline-primary" href="{{ route('dinas_luar.form', ['id' => $row->id]) }}">Edit</a>
    <a class="btn btn-outline-success" href="{{ route('dinas_luar.pdf', ['id' => $row->id]) }}">Download PDF</a>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2 small">
      <div class="col-md-3"><strong>No Dokumen:</strong> {{ $row->doc_no ?? '-' }}</div>
      <div class="col-md-3"><strong>Tanggal:</strong> {{ $row->request_date ? format_date_id($row->request_date) : '-' }}</div>
      <div class="col-md-3"><strong>Perpanjangan Ke:</strong> {{ $row->extension_no ?? 0 }}</div>
      <div class="col-md-3"><strong>Jenis:</strong> {{ $row->request_type }}</div>
      <div class="col-md-3"><strong>Lama Pekerjaan:</strong> {{ $row->work_start ? format_date_id($row->work_start) : '-' }} s/d {{ $row->work_end ? format_date_id($row->work_end) : '-' }}</div>
      <div class="col-md-3"><strong>Customer:</strong> {{ $row->customer ?? '-' }}</div>
      <div class="col-md-3"><strong>No WO:</strong> {{ $row->work_order_no ?? '-' }}</div>
      <div class="col-md-3"><strong>Project:</strong> {{ $row->project ?? '-' }}</div>
      <div class="col-md-3"><strong>Pekerjaan:</strong> {{ $row->pekerjaan ?? '-' }}</div>
      <div class="col-md-3"><strong>Lokasi:</strong> {{ $row->lokasi ?? '-' }}</div>
      @if ($row->request_type === 'DLN')
        <div class="col-md-3"><strong>Negara:</strong> {{ $row->country ?? '-' }}</div>
        <div class="col-md-3"><strong>Kota:</strong> {{ $row->city ?? '-' }}</div>
        <div class="col-md-3"><strong>Paspor:</strong> {{ $row->passport_no ?? '-' }}</div>
        <div class="col-md-3"><strong>Paspor Exp:</strong> {{ $row->passport_expiry ? format_date_id($row->passport_expiry) : '-' }}</div>
        <div class="col-md-3"><strong>Currency:</strong> {{ $row->currency ?? '-' }}</div>
      @endif
      <div class="col-md-3"><strong>Status:</strong> {{ $row->status }}</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">A. Biaya Lumpsum</div>
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Hari</th>
              <th>Jumlah</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($lumpsums as $rowA)
              <tr>
                <td>{{ $rowA->name }}</td>
                <td>{{ $rowA->days }}</td>
                <td>{{ format_currency($rowA->amount) }}</td>
                <td>{{ format_currency($rowA->total) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-muted">Belum ada data.</td></tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3">Total A</th>
              <th>{{ format_currency($totalA) }}</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">B. Fasilitas</div>
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Fasilitas</th>
              <th>Didanai</th>
              <th>Jumlah</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($facilities as $rowB)
              <tr>
                <td>{{ $rowB->name }}</td>
                <td>{{ $rowB->funded_by }}</td>
                <td>{{ format_currency($rowB->amount) }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-muted">Belum ada data.</td></tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2">Total B</th>
              <th>{{ format_currency($totalB) }}</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">C. Lain-lain</div>
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Jumlah</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($others as $rowC)
              <tr>
                <td>{{ $rowC->name }}</td>
                <td>{{ format_currency($rowC->amount) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-muted">Belum ada data.</td></tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <th>Total C</th>
              <th>{{ format_currency($totalC) }}</th>
            </tr>
          </tfoot>
        </table>
        <div class="fw-semibold mt-2">Grand Total: {{ format_currency($grandTotal) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Catatan</div>
        <div class="text-muted">{{ $row->notes ?? '-' }}</div>
      </div>
    </div>
  </div>
</div>

@if ($canApprove && $pendingStepNo)
  <div class="card shadow-sm mt-3">
    <div class="card-body">
      <form method="post" action="{{ route('dinas_luar.index') }}" class="row g-2 align-items-end">
        @csrf
        <input type="hidden" name="id" value="{{ $row->id }}">
        <div class="col-md-6">
          <label class="form-label">Catatan (opsional)</label>
          <input type="text" class="form-control form-control-sm" name="note" maxlength="255">
        </div>
        <div class="col-md-6">
          <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" type="submit" name="action" value="approve_step">Approve</button>
            <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="reject" onclick="return confirm('Tolak pengajuan ini?');">Reject</button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endif
@endsection
