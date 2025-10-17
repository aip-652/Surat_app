<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dokumen;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\AdminLog;

class DokumenController extends Controller
{
  // MAPPING BARU: Nama Lengkap => Kode Singkat (digunakan untuk penomoran & disimpan di DB)
  protected $unitKerjaMap = [
    'CEO / COO / CSO / DIRECTOR'  =>  'DIR',
    'Brand Marketing Division'  =>  'MKT',
    'Brand Stratetgic Department'  =>  'BST',
    'Business & Partnership Division'  =>  'BNP',
    'Business Analyst'  =>  'BSA',
    'Business Development'  =>  'BSD',
    'Business Support Department'  =>  'BSP',
    'Community & Partnership'  =>  'COM',
    'Creative Design Department'  =>  'CDS',
    'Digital Marketing Department'  =>  'DMT',
    'EAST Team'  =>  'EAST',
    'Finance & Accounting Department'  =>  'FNA',
    'General Purchasing Section'  =>  'GPR',
    'IT Service Section'  =>  'ICT',
    'Key Account Manager'  =>  'KAM',
    'Material Sourcing & Development'  =>  'MAT',
    'Procurement Division'  =>  'PRC',
    'Product Design Division'  =>  'DSN',
    'Product Development Division'  =>  'PDV',
    'Product Innovation Department'  =>  'PIN',
    'Product Manager'  =>  'PRM',
    'Product Sourcing & Development'  =>  'PRD',
    'Quality Assurance Department'  =>  'QUA',
    'Quaity Control Department'  =>  'QUC',
    'Quality Management Division'  =>  'QUM',
    'SBU Women & Kids'  =>  'WNK',
    'Technical Design Department'  =>  'TDS',
    'Transformation Management Office'  =>  'TMO',
    'Visual Creative Department'  =>  'VIS',

  ];

  // MAPPING BARU: Nama Lengkap => Kode Singkat (digunakan untuk penomoran & disimpan di DB)
  protected $kodeSuratMap = [
    'Surat' => 'S',
    'BA Nego' => 'BA-NEG',
    'Perjanjian' => 'P',
    'Surat Keputusan' => 'SK',
    'Berita Acara' => 'BA',
    'Berita Acara Serah Terima' => 'BAST',
    'Berita Acara Kesepakatan' => 'BAK',
    'Surat Perintah' => 'SPK',
    'Kebijakan' => 'K',
    'Retur Promosi' => 'SRP',
    'Offering Letter' => 'OL',
  ];

  /**
   * Tampilkan formulir memo internal.
   */
  public function createMemo()
  {
    // Mengirimkan nama lengkap (keys dari map) ke view
    $unitKerja = array_keys($this->unitKerjaMap);
    return view('dokumen.create_memo', compact('unitKerja'));
  }

  /**
   * Tampilkan formulir surat keluar.
   */
  public function createSuratKeluar()
  {
    // Mengirimkan nama lengkap (keys dari map) ke view
    $kodeSurat = array_keys($this->kodeSuratMap);
    return view('dokumen.create_surat_keluar', compact('kodeSurat'));
  }

  /**
   * Simpan Memo Internal ke database dan generate nomor surat.
   */
  public function storeMemo(Request $request)
  {
    $request->validate([
      'unit_kerja' => 'required|string',
      'perihal' => 'required',
      'kepada' => 'nullable',
      'alamat' => 'nullable',
      'pic' => 'required|string',
    ]);

    // 1. Mengambil kode singkat dari nama lengkap yang dikirim dari form
    $unitKerjaCode = $this->unitKerjaMap[$request->unit_kerja] ?? 'UNKNOWN';

    $tanggal = Carbon::now();
    $bulanRomawi = $this->getRomawi($tanggal->format('m'));
    $tahun = $tanggal->format('Y');

    // 2. Menghitung nomor urut harian berdasarkan unit kerja
    $nomorUrutMemoHariIni = Dokumen::where('jenis_dokumen', 'memo_internal')
      ->where('unit_kerja', $unitKerjaCode)
      ->whereDay('tanggal', $tanggal->format('d'))
      ->whereMonth('tanggal', $tanggal->format('m'))
      ->whereYear('tanggal', $tanggal->format('Y'))
      ->count();

    $nomorUrutTigaDigit = str_pad($nomorUrutMemoHariIni + 1, 3, '0', STR_PAD_LEFT);

    // 3. Membentuk nomor surat: FNA-001.03/X/2025
    $nomorSurat = "{$unitKerjaCode}-{$nomorUrutTigaDigit}.{$tanggal->format('d')}/{$bulanRomawi}/{$tahun}";

    Dokumen::create([
      'jenis_dokumen' => 'memo_internal',
      'unit_kerja' => $unitKerjaCode, // Menyimpan kode singkat
      'nomor_dokumen' => $nomorSurat,
      'perihal' => $request->perihal,
      'kepada' => $request->kepada,
      'alamat' => $request->alamat,
      'pic' => $request->pic, // <-- Ambil dari input
      'email_requestor' => Auth::user()->email, // <-- Ambil otomatis dari user login      
      'tanggal' => $tanggal->format('Y-m-d'),
    ]);

    return redirect()->back()
    ->with([
      'success'=>'Memo berhasil dibuat dengan nomor: ' . $nomorSurat,
      'nomor_surat' => $nomorSurat,
    ]);
  }

  /**
   * Simpan Surat Keluar ke database dan generate nomor surat.
   */
  public function storeSuratKeluar(Request $request)
  {
    $request->validate([
      'kode_surat' => 'required|string',
      'perihal' => 'required',
      'kepada' => 'nullable',
      'alamat' => 'nullable',
      'pic' => 'required|string', // Validasi untuk PIC    
    ]);

    // 1. Mengambil kode singkat dari nama lengkap yang dikirim dari form
    $kodeSuratCode = $this->kodeSuratMap[$request->kode_surat] ?? 'UNKNOWN';

    $tanggal = Carbon::now();
    $bulanRomawi = $this->getRomawi($tanggal->format('m'));
    $tahun = $tanggal->format('Y');

    // 2. Menghitung nomor urut harian berdasarkan kode surat
    $nomorUrutHariIni = Dokumen::where('jenis_dokumen', 'surat_keluar')
      ->where('kode_surat', $kodeSuratCode)
      ->whereDate('tanggal', $tanggal->toDateString()) // <-- KUNCI PERUBAHANNYA DI SINI
      ->count();

    $nomorUrut = $nomorUrutHariIni + 1;
    $nomorUrutTigaDigit = str_pad($nomorUrut, 3, '0', STR_PAD_LEFT);

    // 3. Membentuk nomor surat: SK-001.03/72425/X/2025
    $nomorSurat = "{$kodeSuratCode}-{$nomorUrutTigaDigit}.{$tanggal->format('d')}/72425/{$bulanRomawi}/{$tahun}";

    Dokumen::create([
      'jenis_dokumen' => 'surat_keluar',
      'kode_surat' => $kodeSuratCode, // Menyimpan kode singkat
      'nomor_dokumen' => $nomorSurat,
      'perihal' => $request->perihal,
      'kepada' => $request->kepada,
      'alamat' => $request->alamat,
      'pic' => $request->pic, // <-- Ambil dari input
      'email_requestor' => Auth::user()->email, // <-- Ambil otomatis dari user login      
      'tanggal' => $tanggal->format('Y-m-d'),
    ]);

return redirect()->back()
    ->with([
      'success'=>'Surat keluar berhasil dibuat dengan nomor: ' . $nomorSurat,
      'nomor_surat' => $nomorSurat,
    ]);  }

  /**
   * Helper untuk mengubah angka bulan menjadi Romawi.
   */
  private function getRomawi($bulan)
  {
    $romawi = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    return $romawi[(int)$bulan];
  }

  /**
   * Tampilkan halaman admin.
   */
  public function adminDashboard(Request $request)
  {
    $filterJenis = $request->input('jenis');
    $orderBy = $request->input('order_by', 'created_at');
    $sort = $request->input('sort', 'desc');
    $search = $request->input('search');

    // Ganti baris ini
    // $dokumens = Dokumen::query();

    // Menjadi seperti ini
    $query = Dokumen::with('requestor'); // <-- TAMBAHKAN with('requestor')

    if ($filterJenis) {
      $query->where('jenis_dokumen', $filterJenis);
    }

    if ($search) {
      $query->where(function($q) use ($search){
          $q->where('perihal', 'like', '%' . $search . '%')
        ->orWhere('nomor_dokumen', 'like', '%' . $search . '%');
      });
    }

    $query->orderBy($orderBy, $sort);

    $dokumens = $query->paginate(15);

    return view('dokumen.dashboard', compact('dokumens', 'filterJenis', 'orderBy', 'sort', 'search'));
  }

  public function destroy(Dokumen $dokumen)
  {
    // Hapus record dokumen
    $dokumen->delete();

    // AdminLog::create([
    //   'user_id' => Auth::id(),
    //   'action' => 'deleted',
    //   'loggable_id' => $dokumen->id,
    //   'loggable_type' => Dokumen::class,
    //   'details' => "Dokumen '{$dokumen->perihal}' dengan nomor {$dokumen->nomor_dokumen} dihapus.",
    // ]);

    // Redirect kembali ke dashboard dengan pesan sukses
    return redirect()->route('dashboard')->with('success', 'Dokumen berhasil dihapus.');
  }

  public function edit(Dokumen $dokumen)
  {
    // Kirim data dokumen ke view
    return view('dokumen.edit', compact('dokumen'));
  }

  /**
   * Update the specified document in storage.
   */
  public function update(Request $request, Dokumen $dokumen)
  {
    $request->validate([
      'perihal' => 'required|string|max:255',
      'kepada' => 'nullable|string|max:255',
      'pic' => 'required|string|max:255',
    ]);

    // Simpan data lama sebelum diupdate
    $oldData = $dokumen->getOriginal();

    // Lakukan update
    $dokumen->update($request->only(['perihal', 'kepada', 'pic']));

    // Bangun string detail perubahan
    $details = "Dokumen '{$dokumen->perihal}' diperbarui. Perubahan: ";
    $changes = [];
    foreach ($dokumen->getChanges() as $key => $value) {
      if ($key === 'updated_at') continue; // Abaikan kolom updated_at
      $changes[] = "kolom '{$key}' dari '{$oldData[$key]}' menjadi '{$value}'";
    }

    // AdminLog::create([
    //   'user_id' => Auth::id(),
    //   'action' => 'updated',
    //   'loggable_id' => $dokumen->id,
    //   'loggable_type' => Dokumen::class,
    //   'details' => $details . implode(', ', $changes), // Gabungkan detail
    // ]);

    return redirect()->route('dashboard')->with('success', 'Dokumen berhasil diperbarui.');
  }

  /**
   * Simpan dokumen backdate dengan penomoran khusus.
   */
  public function storeBackdate(Request $request)
  {
    $request->validate([
      'tanggal_backdate' => 'required|date|before:today',      'jenis_dokumen' => 'required|in:memo_internal,surat_keluar',
      'kode_spesifik' => 'required|string',
      'perihal' => 'required|string',
      'kepada' => 'nullable|string',
      'pic' => 'required|string',
    ]);

    $tanggal = Carbon::parse($request->tanggal_backdate);
    $bulanRomawi = $this->getRomawi($tanggal->month);
    $tahun = $tanggal->year;

    $nomorSurat = '';
    $dataToCreate = [
      'jenis_dokumen' => $request->jenis_dokumen,
      'perihal' => $request->perihal,
      'kepada' => $request->kepada,
      'pic' => $request->pic,
      'email_requestor' => Auth::user()->email,
      'tanggal' => $tanggal->toDateString(),
    ];

    if ($request->jenis_dokumen == 'memo_internal') {
      $unitKerjaCode = $this->unitKerjaMap[$request->kode_spesifik] ?? 'UNKNOWN';

      $nomorUrutHariItu = Dokumen::where('jenis_dokumen', 'memo_internal')
        ->where('unit_kerja', $unitKerjaCode)
        ->whereDate('tanggal', $tanggal->toDateString())
        ->count();

      // LOGIKA BARU: Tambahkan '1' di belakang nomor urut
      $nomorUrutEmpatDigit = str_pad($nomorUrutHariItu + 1, 3, '0', STR_PAD_LEFT) . '1';

      $nomorSurat = "{$unitKerjaCode}-{$nomorUrutEmpatDigit}.{$tanggal->format('d')}/{$bulanRomawi}/{$tahun}";
      $dataToCreate['unit_kerja'] = $unitKerjaCode;
    } elseif ($request->jenis_dokumen == 'surat_keluar') {
      $kodeSuratCode = $this->kodeSuratMap[$request->kode_spesifik] ?? 'UNKNOWN';

      $nomorUrutHariItu = Dokumen::where('jenis_dokumen', 'surat_keluar')
        ->where('kode_surat', $kodeSuratCode)
        ->whereDate('tanggal', '!=', Carbon::now()->toDateString()) // Hitung semua backdate sebelumnya
        ->count();

      // LOGIKA BARU: Tambahkan '1' di belakang nomor urut
      $nomorUrutEmpatDigit = str_pad($nomorUrutHariItu + 1, 3, '0', STR_PAD_LEFT) . '1';

      $nomorSurat = "{$kodeSuratCode}-{$nomorUrutEmpatDigit}.{$tanggal->format('d')}/72425/{$bulanRomawi}/{$tahun}";
      $dataToCreate['kode_surat'] = $kodeSuratCode;
    }

    $dataToCreate['nomor_dokumen'] = $nomorSurat;
    Dokumen::create($dataToCreate);

    return redirect()->back()
      ->with([
        'success' => 'Dokumen backdate berhasil dibuat dengan nomor: ' . $nomorSurat,
        'nomor_surat' => $nomorSurat,
      ]);  }

  public function createBackdate()
  {
    $unitKerja = array_keys($this->unitKerjaMap);
    $kodeSurat = array_keys($this->kodeSuratMap);
    return view('dokumen.create_backdate', compact('unitKerja', 'kodeSurat'));
  }
}
