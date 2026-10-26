<?php
namespace App\Http\Controllers;
use App\Models\Branch;
use App\Models\Departemen;
use App\Models\UsersProfile;
use App\Services\ExcelReaderService;
use App\Services\ExcelTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use ZipArchive;

class UsersProfileController extends Controller {
    public function index(Request $r){
        $q = UsersProfile::query()->with(['branch','departemen']);
        if($r->filled('search')){
            $s=$r->search;
            $q->where(fn($qq)=>$qq->where('NAME','like',"%{$s}%")
                ->orWhere('Card','like',"%{$s}%")
                ->orWhere('NoIdentitas','like',"%{$s}%")
                ->orWhere('EMPLOYEE_ID','like',"%{$s}%"));
        }
        if($r->filled('Branchid')) $q->where('Branchid', $r->Branchid);
        if($r->filled('Depid')) $q->where('Depid', $r->Depid);
        // Select tanpa kolom photo (mediumblob 100KB) + has_photo flag agar tidak load 1.2MB per halaman
        $q->select(['ID','EMPLOYEE_ID','NAME','Card','NoIdentitas','Branchid','Depid','user_type','BEGIN_DATE','END_DATE', \Illuminate\Support\Facades\DB::raw('photo IS NOT NULL AND LENGTH(photo)>0 as has_photo')]);
        $users = $q->orderByDesc('ID')->paginate(12)->withQueryString();
        $branches = \Illuminate\Support\Facades\Cache::remember('branch:list',300, fn()=> Branch::orderBy('name')->get());
        $departemens = \Illuminate\Support\Facades\Cache::remember('departemen:list',300, fn()=> Departemen::orderBy('name')->get());
        $stats = ['total'=> \Illuminate\Support\Facades\Cache::remember('cnt:usersprofile',60, fn()=> UsersProfile::count())];
        $deviceGates = \Illuminate\Support\Facades\Cache::remember('devicegates:list',60, fn()=> \App\Models\DeviceGate::orderBy('name')->get());
        $deviceGroups = \Illuminate\Support\Facades\Cache::remember('devicegroups:list:cnt',60, fn()=> \App\Models\DeviceGroup::withCount('deviceGates')->orderBy('name')->get());
        return view('usersprofile.index', compact('users','branches','departemens','stats','deviceGates','deviceGroups'));
    }

    public function exportExcel(Request $r, ExcelTemplateService $tplService){
        $q = UsersProfile::with(['branch','departemen']);
        if($r->filled('search')){
            $s=$r->search;
            $q->where(fn($qq)=>$qq->where('NAME','like',"%{$s}%")
                ->orWhere('Card','like',"%{$s}%")
                ->orWhere('NoIdentitas','like',"%{$s}%")
                ->orWhere('EMPLOYEE_ID','like',"%{$s}%"));
        }
        if($r->filled('Branchid')) $q->where('Branchid', $r->Branchid);
        if($r->filled('Depid')) $q->where('Depid', $r->Depid);
        $users = $q->orderByDesc('ID')->get();

        // 1. Ekspor langsung ke format asli XLSX murni via ExcelTemplateService (agar string NIK 16 digit tidak terpotong ilmiah 3.17E+15)
        try {
            $xlsxContent = $tplService->exportUsersXlsx($users);
            $fileName = 'Indonesia_Defence_Users_' . date('Ymd_His') . '.xlsx';
            return response($xlsxContent)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Throwable $e) {}

        // 2. Fallback HTML Excel dengan mso-number-format:"\@" (paksa string di Excel)
        return $this->exportHtmlExcel($users);
    }

    private function exportXlsx($users){
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users Profile');
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDC2626']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => 'FF1F2937']]],
        ];
        $headers = ['ID','EMPLOYEE_ID','Nama / Name','NIK','RFID Card','Cabang','Departemen','Tipe','Begin','End'];
        $col='A'; foreach($headers as $h){ $sheet->setCellValue($col.'1',$h); $col++; }
        $widths=['A'=>8,'B'=>16,'C'=>26,'D'=>20,'E'=>16,'F'=>20,'G'=>20,'H'=>14,'I'=>14,'J'=>14];
        foreach($widths as $c=>$w){ $sheet->getColumnDimension($c)->setWidth($w); }
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:J1');
        $row=2;
        foreach($users as $u){
            $typeLabel = match((int)$u->user_type){1=>'MEMBER',2=>'KARYAWAN',default=>'UNKNOWN'};
            $sheet->setCellValueExplicit('A'.$row,(string)$u->ID,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row,(string)($u->EMPLOYEE_ID??''),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C'.$row,$u->NAME);
            $sheet->setCellValueExplicit('D'.$row,(string)($u->NoIdentitas??''),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$row,(string)($u->Card??''),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('F'.$row,$u->branch->name??'-');
            $sheet->setCellValue('G'.$row,$u->departemen->name??'-');
            $sheet->setCellValue('H'.$row,$typeLabel);
            $sheet->setCellValue('I'.$row,$u->BEGIN_DATE?$u->BEGIN_DATE->format('Y-m-d'):'-');
            $sheet->setCellValue('J'.$row,$u->END_DATE?$u->END_DATE->format('Y-m-d'):'-');
            if($row%2===0){ $sheet->getStyle('A'.$row.':J'.$row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB'); }
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }
        if($row>2){
            $sheet->getStyle('A2:J'.($row-1))->applyFromArray(['borders'=>['allBorders'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,'color'=>['argb'=>'FFD1D5DB']]]]);
            $sheet->getStyle('A2:B'.($row-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H2:J'.($row-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getHeaderFooter()->setOddHeader('&C&B Indonesia Defence System - Users Profile');
        $sheet->getHeaderFooter()->setOddFooter('&L&D &T &R Halaman &P / &N');
        $fileName='Indonesia_Defence_Users_'.date('Ymd_His').'.xlsx';
        $tmp=tempnam(sys_get_temp_dir(),'ids_'); (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);
        $content=file_get_contents($tmp); @unlink($tmp);
        return response($content)->header('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition','attachment; filename="'.$fileName.'"')->header('Cache-Control','max-age=0');
    }

    private function exportHtmlExcel($users){
        $fileName='Indonesia_Defence_Users_'.date('Ymd_His').'.xls';
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><style>td{font-family:Calibri,Arial,sans-serif;font-size:11px} .hdr{background:#DC2626;color:#FFFFFF;font-weight:bold;text-align:center;border:.5pt solid #1F2937} .cell{border:.5pt solid #D1D5DB;padding:4px} .txt{mso-number-format:"\@"} .zebra{background:#F9FAFB} .ctr{text-align:center} .hdr2{background:#DC2626;color:#FFFFFF;font-weight:bold;text-align:center;font-size:14px;padding:10px}</style></head><body>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;width:100%">';
        $html .= '<tr><td colspan="10" class="hdr2">Indonesia Defence System — Users Profile — '.date('d/m/Y H:i').' — Total: '.$users->count().' user</td></tr>';
        $html .= '<tr><td class="hdr">ID</td><td class="hdr">EMPLOYEE_ID</td><td class="hdr">Nama / Name</td><td class="hdr">NIK</td><td class="hdr">RFID Card</td><td class="hdr">Cabang</td><td class="hdr">Departemen</td><td class="hdr">Tipe</td><td class="hdr">Begin</td><td class="hdr">End</td></tr>';
        $n=0; foreach($users as $u){
            $typeLabel = match((int)$u->user_type){1=>'MEMBER',2=>'KARYAWAN',default=>'UNKNOWN'};
            $cls = ($n%2===1)?'zebra':'';
            $begin = $u->BEGIN_DATE?$u->BEGIN_DATE->format('Y-m-d'):'-';
            $end = $u->END_DATE?$u->END_DATE->format('Y-m-d'):'-';
            $branch = htmlspecialchars($u->branch->name??'-');
            $dept = htmlspecialchars($u->departemen->name??'-');
            $name = htmlspecialchars($u->NAME??'');
            $html .= "<tr class=\"{$cls}\"><td class=\"cell ctr txt\">{$u->ID}</td><td class=\"cell ctr txt\">".htmlspecialchars($u->EMPLOYEE_ID??'')."</td><td class=\"cell\">{$name}</td><td class=\"cell ctr txt\">".htmlspecialchars($u->NoIdentitas??'')."</td><td class=\"cell ctr txt\">".htmlspecialchars($u->Card??'')."</td><td class=\"cell\">{$branch}</td><td class=\"cell\">{$dept}</td><td class=\"cell ctr\">{$typeLabel}</td><td class=\"cell ctr\">{$begin}</td><td class=\"cell ctr\">{$end}</td></tr>";
            $n++;
        }
        if($users->isEmpty()){
            $html .= '<tr><td colspan="10" class="cell ctr" style="padding:20px;color:#9CA3AF">Tidak ada data sesuai filter</td></tr>';
        }
        $html .= '</table></body></html>';
        return response($html)->header('Content-Type','application/vnd.ms-excel; charset=UTF-8')->header('Content-Disposition','attachment; filename="'.$fileName.'"')->header('Cache-Control','max-age=0');
    }

    public function downloadTemplate(ExcelTemplateService $tplService){
        $xlsx = $tplService->generateTemplateXlsx();
        $fileName = 'Template_Import_Users_' . date('Ymd') . '.xlsx';
        return response($xlsx)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Cache-Control', 'max-age=0');
    }

    public function importExcel(Request $r, ExcelReaderService $reader){
        $r->validate([
            'excel_file' => 'required|file|max:20480', // max 20MB
            'zip_file'   => 'nullable|file|mimes:zip|max:102400', // max 100MB
        ], [
            'excel_file.required' => 'File Excel wajib dipilih.',
            'excel_file.max'      => 'Ukuran file Excel maksimal 20 MB.',
            'zip_file.mimes'      => 'File foto harus berformat arsip ZIP.',
            'zip_file.max'        => 'Ukuran file ZIP maksimal 100 MB.',
        ]);

        $excelPath = $r->file('excel_file')->getRealPath();
        try {
            $rows = $reader->read($excelPath);
        } catch (\Throwable $e) {
            return back()->with('err', 'Gagal membaca file Excel: ' . $e->getMessage());
        }

        if (count($rows) < 2) {
            return back()->with('err', 'File Excel kosong atau tidak memiliki baris data (hanya header).');
        }

        // 1. Ekstrak peta foto dari ZIP (jika di-upload)
        $photoMap = []; // [EMPLOYEE_ID => binary_jpeg]
        if ($r->hasFile('zip_file')) {
            $zip = new ZipArchive();
            $zipPath = $r->file('zip_file')->getRealPath();
            if ($zip->open($zipPath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    // Lewati folder atau file hidden Mac/Windows
                    if (str_ends_with($entryName, '/') || str_contains($entryName, '__MACOSX') || str_starts_with(basename($entryName), '.')) {
                        continue;
                    }
                    $baseName = basename($entryName);
                    $dotPos = strrpos($baseName, '.');
                    if ($dotPos === false) continue;

                    $empKey = strtoupper(trim(substr($baseName, 0, $dotPos)));
                    $ext = strtolower(substr($baseName, $dotPos + 1));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

                    $stream = $zip->getStream($entryName);
                    if ($stream) {
                        $rawBin = stream_get_contents($stream);
                        fclose($stream);
                        if ($rawBin) {
                            $optimized = $this->optimizePhotoBinary($rawBin);
                            if ($optimized) {
                                $photoMap[$empKey] = $optimized;
                            }
                        }
                    }
                }
                $zip->close();
            }
        }

        // 2. Petakan indeks header
        $headerRow = array_map(fn($v) => strtoupper(trim((string)$v)), $rows[0]);
        $colIndex = [];
        foreach ($headerRow as $idx => $hName) {
            // Hilangkan karakter non-alfanumerik
            $cleanKey = preg_replace('/[^A-Z0-9_]/', '', $hName);
            $colIndex[$cleanKey] = $idx;
        }

        // Alias penamaan kolom
        $findIdx = function(array $aliases) use ($colIndex) {
            foreach ($aliases as $a) {
                $clean = strtoupper(preg_replace('/[^A-Z0-9_]/', '', $a));
                if (isset($colIndex[$clean])) return $colIndex[$clean];
            }
            return null;
        };

        $cEmpId   = $findIdx(['EMPLOYEE_ID', 'EMPLOYEEID', 'NIP', 'ID_PEGAWAI', 'KODE']);
        $cName    = $findIdx(['NAME', 'NAMA', 'NAMA_LENGKAP', 'FULLNAME']);
        $cNik     = $findIdx(['NOIDENTITAS', 'NIK', 'NO_KTP', 'PASPOR', 'KTP']);
        $cCard    = $findIdx(['CARD', 'NO_KARTU', 'RFID', 'NOMOR_KARTU']);
        $cBranch  = $findIdx(['BRANCHID', 'CABANG', 'BRANCH_ID', 'ID_CABANG']);
        $cDept    = $findIdx(['DEPID', 'DEPARTEMEN', 'DEPT_ID', 'ID_DEPARTEMEN']);
        $cType    = $findIdx(['USER_TYPE', 'TIPE', 'JENIS', 'STATUS_USER']);
        $cBegin   = $findIdx(['BEGIN_DATE', 'TANGGAL_MULAI', 'START_DATE', 'BEGIN']);
        $cEnd     = $findIdx(['END_DATE', 'TANGGAL_SELESAI', 'EXPIRED_DATE', 'END']);
        $cBirth   = $findIdx(['BIRTHDAY', 'TANGGAL_LAHIR', 'DOB']);
        $cJob     = $findIdx(['JOB_POSITION', 'JABATAN', 'POSISI']);

        if ($cName === null) {
            return back()->with('err', 'Format Excel tidak valid: kolom "NAME" atau "Nama" tidak ditemukan pada baris header.');
        }

        $createdCount = 0;
        $updatedCount = 0;
        $photoAttachedCount = 0;
        $failedRows = [];

        // Cache daftar Cabang & Departemen untuk konversi teks -> ID
        $branches = Branch::all();
        $departemens = Departemen::all();

        for ($rIdx = 1; $rIdx < count($rows); $rIdx++) {
            $row = $rows[$rIdx];
            $lineNum = $rIdx + 1;

            $name = $cName !== null ? trim((string)($row[$cName] ?? '')) : '';
            if ($name === '') {
                // Lewati baris kosong
                continue;
            }

            $rawEmpId = $cEmpId !== null ? trim((string)($row[$cEmpId] ?? '')) : '';
            $rawNik   = $cNik !== null ? trim((string)($row[$cNik] ?? '')) : '';
            $rawCard  = $cCard !== null ? trim((string)($row[$cCard] ?? '')) : '';

            $empId = $rawEmpId !== '' ? $rawEmpId : ('EMP-' . strtoupper(substr(uniqid(), -6)));
            $nik   = $rawNik !== '' ? $rawNik : null;
            $card  = $rawCard !== '' ? $rawCard : null;

            // Validasi Card vs NoIdentitas
            if ($card !== null && $nik !== null && $card === $nik) {
                $failedRows[] = "Baris {$lineNum} ({$name}): Nomor Kartu (Card) tidak boleh sama dengan No. Identitas.";
                continue;
            }

            // Cari apakah record sudah ada berdasarkan EMPLOYEE_ID
            $existing = UsersProfile::where('EMPLOYEE_ID', $empId)->first();

            // Cek keunikan Card terhadap user lain
            if ($card !== null) {
                $dupCard = UsersProfile::where('Card', $card)
                    ->when($existing, fn($q) => $q->where('ID', '!=', $existing->ID))
                    ->first();
                if ($dupCard) {
                    $failedRows[] = "Baris {$lineNum} ({$name}): Kartu RFID '{$card}' sudah dipakai oleh {$dupCard->NAME} ({$dupCard->EMPLOYEE_ID}).";
                    continue;
                }
            }

            // Cek keunikan NIK terhadap user lain
            if ($nik !== null) {
                $dupNik = UsersProfile::where('NoIdentitas', $nik)
                    ->when($existing, fn($q) => $q->where('ID', '!=', $existing->ID))
                    ->first();
                if ($dupNik) {
                    $failedRows[] = "Baris {$lineNum} ({$name}): NIK '{$nik}' sudah dipakai oleh {$dupNik->NAME} ({$dupNik->EMPLOYEE_ID}).";
                    continue;
                }
            }

            // Resolve Cabang (ID atau Nama)
            $branchId = 1;
            if ($cBranch !== null && !empty($row[$cBranch])) {
                $bVal = trim((string)$row[$cBranch]);
                if (is_numeric($bVal)) {
                    $branchId = (int)$bVal;
                } else {
                    $foundB = $branches->first(fn($b) => strcasecmp($b->name, $bVal) === 0);
                    if ($foundB) $branchId = $foundB->id;
                }
            }

            // Resolve Departemen (ID atau Nama)
            $depId = 1;
            if ($cDept !== null && !empty($row[$cDept])) {
                $dVal = trim((string)$row[$cDept]);
                if (is_numeric($dVal)) {
                    $depId = (int)$dVal;
                } else {
                    $foundD = $departemens->first(fn($d) => strcasecmp($d->name, $dVal) === 0);
                    if ($foundD) $depId = $foundD->id;
                }
            }

            // Resolve User Type (1 = Member, 2 = Karyawan)
            $uType = 2; // Default Karyawan
            if ($cType !== null && !empty($row[$cType])) {
                $tVal = strtoupper(trim((string)$row[$cType]));
                if (in_array($tVal, ['1', 'MEMBER', 'TAMU', 'GUEST'])) {
                    $uType = 1;
                } elseif (in_array($tVal, ['2', 'KARYAWAN', 'STAFF', 'EMPLOYEE'])) {
                    $uType = 2;
                }
            }

            $beginDate = ($cBegin !== null && !empty($row[$cBegin])) ? date('Y-m-d H:i:s', strtotime($row[$cBegin])) : now();
            $endDate   = ($cEnd !== null && !empty($row[$cEnd])) ? date('Y-m-d H:i:s', strtotime($row[$cEnd])) : '2079-12-31 23:59:59';
            $birthday  = ($cBirth !== null && !empty($row[$cBirth])) ? date('Y-m-d', strtotime($row[$cBirth])) : null;
            $jobPos    = ($cJob !== null && !empty($row[$cJob])) ? trim((string)$row[$cJob]) : null;

            $payload = [
                'NAME'            => $name,
                'EMPLOYEE_ID'     => $empId,
                'Card'            => $card,
                'NoIdentitas'     => $nik,
                'Branchid'        => $branchId,
                'Depid'           => $depId,
                'user_type'       => $uType,
                'BEGIN_DATE'      => $beginDate,
                'END_DATE'        => $endDate,
                'BIRTHDAY'        => $birthday,
                'JOB_POSITION'    => $jobPos,
                'api_last_update' => now()->format('Y-m-d H:i:s'),
                'ACC_MODE'        => 0,
                'DOOR_GRP'        => 0,
                'Timezone'        => 0,
                'shiftpatternID'  => 0,
                'PIN'             => null,
                'PASSWORD'        => Hash::make($card ?? '123456'),
            ];

            // Pasang foto dari ZIP jika cocok dengan EMPLOYEE_ID
            $empLookup = strtoupper($empId);
            if (isset($photoMap[$empLookup])) {
                $payload['photo'] = $photoMap[$empLookup];
                $photoAttachedCount++;
            }

            if ($existing) {
                // Pertahankan foto lama jika di ZIP tidak disediakan
                if (!isset($payload['photo'])) {
                    unset($payload['photo']);
                }
                $payload['updated_at'] = $existing->updated_at;
                $existing->update($payload);
                $updatedCount++;
            } else {
                UsersProfile::create($payload);
                $createdCount++;
            }
        }

        $summary = "Import selesai! Dibuat baru: {$createdCount}, Diperbarui: {$updatedCount}.";
        if ($photoAttachedCount > 0) {
            $summary .= " Foto terpasang dari ZIP: {$photoAttachedCount} personel.";
        }

        if (!empty($failedRows)) {
            $errDetail = implode('<br>', array_slice($failedRows, 0, 8));
            if (count($failedRows) > 8) {
                $errDetail .= '<br>...dan ' . (count($failedRows) - 8) . ' baris lainnya.';
            }
            return redirect()->route('usersprofile.index')
                ->with('ok', $summary)
                ->with('import_warnings', $errDetail);
        }

        return redirect()->route('usersprofile.index')->with('ok', $summary);
    }

    public function create(){
        $u = new UsersProfile();
        $u->BEGIN_DATE = now();
        $u->END_DATE = '2079-12-31 23:59:59';
        $u->Branchid = 1;
        $u->Depid = 1;
        $u->user_type = 1;
        $u->flag = 0;
        $branches = Branch::orderBy('name')->get();
        $departemens = Departemen::orderBy('name')->get();
        return view('usersprofile.form', ['u'=>$u,'mode'=>'create','branches'=>$branches,'departemens'=>$departemens]);
    }

    public function store(Request $r){
        $cardRules = ['nullable', 'max:15', 'different:NoIdentitas', 'unique:usersprofile,Card'];
        $nikRules  = ['nullable', 'max:30', 'different:Card', 'unique:usersprofile,NoIdentitas'];

        $customMessages = [
            'NAME.required'         => 'Nama personel (NAME) wajib diisi.',
            'NAME.max'              => 'Nama personel maksimal 50 karakter.',
            'EMPLOYEE_ID.unique'    => 'ID Pegawai (EMPLOYEE_ID) :input sudah digunakan oleh personel lain.',
            'EMPLOYEE_ID.max'       => 'ID Pegawai (EMPLOYEE_ID) maksimal 15 karakter.',
            'Card.different'        => 'Nomor Kartu (Card) tidak boleh sama dengan No. Identitas (NoIdentitas).',
            'Card.unique'           => 'Nomor Kartu (Card) :input sudah digunakan oleh personel lain.',
            'Card.max'              => 'Nomor Kartu (Card) maksimal 15 karakter.',
            'NoIdentitas.different' => 'No. Identitas (NoIdentitas) tidak boleh sama dengan Nomor Kartu (Card).',
            'NoIdentitas.unique'    => 'No. Identitas (NoIdentitas) :input sudah terdaftar pada personel lain.',
            'NoIdentitas.max'       => 'No. Identitas (NoIdentitas) maksimal 30 karakter.',
            'user_type.required'    => 'Tipe pengguna wajib dipilih.',
            'photo.image'           => 'Berkas foto harus berupa gambar.',
            'photo.max'             => 'Ukuran foto maksimal 5 MB.',
        ];

        $data = $r->validate([
            'NAME'=>'required|max:50',
            'EMPLOYEE_ID'=>'nullable|max:15|unique:usersprofile,EMPLOYEE_ID',
            'BEGIN_DATE'=>'nullable|date',
            'END_DATE'=>'nullable|date',
            'BIRTHDAY'=>'nullable|date',
            'Depid'=>'nullable|integer|exists:departemen,id',
            'Branchid'=>'nullable|integer|exists:branch,id',
            'photo'=>'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'Card'=>$cardRules,
            'NoIdentitas'=>$nikRules,
            'user_type'=>'required|in:1,2',
            'flag'=>'nullable|integer',
        ], $customMessages);
        $payload = [
            'NAME'=>$data['NAME'],
            'EMPLOYEE_ID'=> $data['EMPLOYEE_ID'] ?? ('EMP-' . strtoupper(substr(uniqid(), -6))),
            'BEGIN_DATE'=> $data['BEGIN_DATE'] ?? now(),
            'END_DATE'=> $data['END_DATE'] ?? '2079-12-31 23:59:59',
            'BIRTHDAY'=> $data['BIRTHDAY'] ?? null,
            'Depid'=> $data['Depid'] ?? 1,
            'Branchid'=> $data['Branchid'] ?? 1,
            'Card'=> $data['Card'] ?? null,
            'NoIdentitas'=> $data['NoIdentitas'] ?? null,
            'user_type'=> (int)$data['user_type'],
            'flag'=> (int)($data['flag'] ?? 0),
            'api_last_update'=> now()->format('Y-m-d H:i:s'),
            'ACC_MODE'=>0,'DOOR_GRP'=>0,'Timezone'=>0,'shiftpatternID'=>0,'PIN'=> null,
            'PASSWORD'=> Hash::make($data['Card'] ?? '123456'),
        ];
        if($r->hasFile('photo')){
            $rawBin = $this->optimizePhotoRawFile($r->file('photo'));
            if($rawBin === null) return back()->withErrors(['photo'=>'Gagal memproses foto (format tidak didukung)'])->withInput();
            $payload['photo'] = $rawBin;
        }
        UsersProfile::create($payload);
        return redirect()->route('usersprofile.index')->with('ok','Personel berhasil ditambahkan');
    }

    public function show(UsersProfile $usersprofile){
        $usersprofile->load(['branch','departemen','devices.gate']);
        return view('usersprofile.show', ['u'=>$usersprofile]);
    }

    public function edit(UsersProfile $usersprofile){
        $branches = Branch::orderBy('name')->get();
        $departemens = Departemen::orderBy('name')->get();
        return view('usersprofile.form', ['u'=>$usersprofile,'mode'=>'edit','branches'=>$branches,'departemens'=>$departemens]);
    }

    public function update(Request $r, UsersProfile $usersprofile){
        $cardRules = ['nullable', 'max:15', 'different:NoIdentitas', 'unique:usersprofile,Card,' . $usersprofile->ID . ',ID'];
        $nikRules  = ['nullable', 'max:30', 'different:Card', 'unique:usersprofile,NoIdentitas,' . $usersprofile->ID . ',ID'];

        $customMessages = [
            'NAME.required'         => 'Nama personel (NAME) wajib diisi.',
            'NAME.max'              => 'Nama personel maksimal 50 karakter.',
            'EMPLOYEE_ID.unique'    => 'ID Pegawai (EMPLOYEE_ID) :input sudah digunakan oleh personel lain.',
            'EMPLOYEE_ID.max'       => 'ID Pegawai (EMPLOYEE_ID) maksimal 15 karakter.',
            'Card.different'        => 'Nomor Kartu (Card) tidak boleh sama dengan No. Identitas (NoIdentitas).',
            'Card.unique'           => 'Nomor Kartu (Card) :input sudah digunakan oleh personel lain.',
            'Card.max'              => 'Nomor Kartu (Card) maksimal 15 karakter.',
            'NoIdentitas.different' => 'No. Identitas (NoIdentitas) tidak boleh sama dengan Nomor Kartu (Card).',
            'NoIdentitas.unique'    => 'No. Identitas (NoIdentitas) :input sudah terdaftar pada personel lain.',
            'NoIdentitas.max'       => 'No. Identitas (NoIdentitas) maksimal 30 karakter.',
            'user_type.required'    => 'Tipe pengguna wajib dipilih.',
            'photo.image'           => 'Berkas foto harus berupa gambar.',
            'photo.max'             => 'Ukuran foto maksimal 5 MB.',
        ];

        $data = $r->validate([
            'NAME'=>'required|max:50',
            'EMPLOYEE_ID'=>'nullable|max:15|unique:usersprofile,EMPLOYEE_ID,'.$usersprofile->ID.',ID',
            'BEGIN_DATE'=>'nullable|date',
            'END_DATE'=>'nullable|date',
            'BIRTHDAY'=>'nullable|date',
            'Depid'=>'nullable|integer|exists:departemen,id',
            'Branchid'=>'nullable|integer|exists:branch,id',
            'photo'=>'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'Card'=>$cardRules,
            'NoIdentitas'=>$nikRules,
            'user_type'=>'required|in:1,2',
            'is_leave'=>'nullable|integer|in:0,1',
            'flag'=>'nullable|integer',
            'remove_photo'=>'nullable|boolean',
        ], $customMessages);
        $isLeaveVal = (int) ($r->input('is_leave', $usersprofile->is_leave ?? 0));
        $oldLeaveVal = (int) ($usersprofile->is_leave ?? 0);

        $payload = [
            'NAME'=>$data['NAME'],
            'EMPLOYEE_ID'=> $data['EMPLOYEE_ID'] ?? $usersprofile->EMPLOYEE_ID,
            'BEGIN_DATE'=> $data['BEGIN_DATE'] ?? $usersprofile->BEGIN_DATE,
            'END_DATE'=> $data['END_DATE'] ?? $usersprofile->END_DATE,
            'BIRTHDAY'=> $data['BIRTHDAY'] ?? null,
            'Depid'=> $data['Depid'] ?? 1,
            'Branchid'=> $data['Branchid'] ?? 1,
            'Card'=> $data['Card'] ?? null,
            'NoIdentitas'=> $data['NoIdentitas'] ?? null,
            'user_type'=> (int)$data['user_type'],
            'is_leave'=> $isLeaveVal,
            'flag'=> (int)($data['flag'] ?? 0),
            'api_last_update'=> now()->format('Y-m-d H:i:s'),
        ];
        if($r->boolean('remove_photo')){
            $payload['photo'] = null;
        } elseif($r->hasFile('photo')){
            $rawBin = $this->optimizePhotoRawFile($r->file('photo'));
            if($rawBin === null) return back()->withErrors(['photo'=>'Gagal memproses foto'])->withInput();
            $payload['photo'] = $rawBin;
        }
        if(!empty($data['Card']) && $data['Card'] !== $usersprofile->Card){
            $payload['PASSWORD'] = Hash::make($data['Card']);
        }
        // Pertahankan nilai updated_at yang lama agar tidak berubah saat edit dari web
        $payload['updated_at'] = $usersprofile->updated_at;
        $usersprofile->update($payload);

        // Jika status cuti berubah (atau di-submit), dorong perintah enableuser ke mesin melalui ApiEndpoint 19
        $this->pushEnableUserStatus($usersprofile, $isLeaveVal);

        $statusMsg = $isLeaveVal === 1 ? 'Status diubah ke CUTI (akses gerbang dinonaktifkan)' : 'Status diubah ke AKTIF (akses gerbang diizinkan)';
        return redirect()->route('usersprofile.index')->with('ok', 'User diperbarui. ' . $statusMsg);
    }

    public function destroy(UsersProfile $usersprofile){
        $usersprofile->delete();
        return back()->with('ok','User dihapus');
    }

    public function syncSingleUserStep(Request $r){
        $data = $r->validate([
            'user_id'   => 'required|integer|exists:usersprofile,ID',
            'gate_ids'  => 'required|array|min:1',
            'gate_ids.*'=> 'integer|exists:devicegate,id',
        ]);

        $u = UsersProfile::find($data['user_id']);
        $gates = \App\Models\DeviceGate::whereIn('id', $data['gate_ids'])->get();

        $ep2 = \App\Models\ApiEndpoint::find(2);
        $ep3 = \App\Models\ApiEndpoint::find(3);
        if(!$ep2 || !$ep3) {
            return response()->json(['ok'=>false, 'message'=>'ApiEndpoint #2 atau #3 belum terpasang di database'], 500);
        }

        $results = [];
        $allSuccess = true;

        $fdata = '';
        if($u->photo){
            $raw = $u->photo;
            if(is_string($raw) && str_starts_with($raw, 'data:')){
                $parts = explode(',', $raw, 2);
                $fdata = $parts[1] ?? '';
            } else {
                $fdata = trim($raw);
                if(base64_decode($fdata, true) === false) $fdata = base64_encode($raw);
            }
        }

        foreach($gates as $gate){
            $sn = $gate->sn;
            $payload2 = [
                'sn'         => (string)$sn,
                'userid'     => (string)$u->ID,
                'username'   => (string)$u->NAME,
                'type'       => '50',
                'admin'      => '0',
                'cardnumber' => (string)($u->Card ?? '0'),
                'timezone'   => '0',
                'fdata'      => $fdata,
            ];

            try {
                $res = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(10)->post($ep2->url, $payload2);
                $ok = $res->successful();
                $body = $this->safeJsonBody($res->body());
                $results[] = ['gate'=>$gate->name, 'api'=>'registerface', 'ok'=>$ok, 'body'=>$body];
                if(!$ok) $allSuccess = false;
            } catch(\Throwable $e){
                $results[] = ['gate'=>$gate->name, 'api'=>'registerface', 'ok'=>false, 'error'=>$e->getMessage()];
                $allSuccess = false;
                continue;
            }

            // User Access
            $starttime = $u->BEGIN_DATE ? $u->BEGIN_DATE->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
            $endtime   = $u->END_DATE ? $u->END_DATE->format('Y-m-d H:i:s') : '2079-12-31 23:59:59';
            $payload3 = [[
                'sn'        => (string)$sn,
                'userid'    => (string)$u->ID,
                'starttime' => $starttime,
                'endtime'   => $endtime,
                'weekzone'  => 0,
            ]];

            try {
                $res3 = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(10)->post($ep3->url, $payload3);
                $ok3 = $res3->successful();
                $body3 = $this->safeJsonBody($res3->body());
                $results[] = ['gate'=>$gate->name, 'api'=>'useraccess', 'ok'=>$ok3, 'body'=>$body3];
                if(!$ok3) $allSuccess = false;
            } catch(\Throwable $e){
                $results[] = ['gate'=>$gate->name, 'api'=>'useraccess', 'ok'=>false, 'error'=>$e->getMessage()];
                $allSuccess = false;
            }

            try {
                \App\Models\UsersXDevice::firstOrCreate(['userId'=>$u->ID, 'gateId'=>$gate->id], ['Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0]);
            } catch(\Throwable $e){}
        }

        return response()->json([
            'ok'        => $allSuccess,
            'user_id'   => $u->ID,
            'user_name' => $u->NAME,
            'emp_id'    => $u->EMPLOYEE_ID,
            'results'   => $results,
        ]);
    }

    public function removeSingleUserStep(Request $r){
        $data = $r->validate([
            'user_id'   => 'required|integer|exists:usersprofile,ID',
            'gate_ids'  => 'required|array|min:1',
            'gate_ids.*'=> 'integer|exists:devicegate,id',
        ]);

        $u = UsersProfile::find($data['user_id']);
        $gates = \App\Models\DeviceGate::whereIn('id', $data['gate_ids'])->get();

        $ep6 = \App\Models\ApiEndpoint::find(6);
        if(!$ep6) {
            return response()->json(['ok'=>false, 'message'=>'ApiEndpoint #6 (deleteface) belum terpasang'], 500);
        }

        $results = [];
        $allSuccess = true;

        foreach($gates as $gate){
            $payload = ['sn' => (string)$gate->sn, 'userid' => (string)$u->ID];
            try {
                $res = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(8)->post($ep6->url, $payload);
                $ok = $res->successful();
                $body = $this->safeJsonBody($res->body());
                $results[] = ['gate'=>$gate->name, 'api'=>'deleteface', 'ok'=>$ok, 'body'=>$body];
                if(!$ok) $allSuccess = false;
            } catch(\Throwable $e){
                $results[] = ['gate'=>$gate->name, 'api'=>'deleteface', 'ok'=>false, 'error'=>$e->getMessage()];
                $allSuccess = false;
            }

            try {
                \App\Models\UsersXDevice::where('userId', $u->ID)->where('gateId', $gate->id)->delete();
            } catch(\Throwable $e){}
        }

        return response()->json([
            'ok'        => $allSuccess,
            'user_id'   => $u->ID,
            'user_name' => $u->NAME,
            'emp_id'    => $u->EMPLOYEE_ID,
            'results'   => $results,
        ]);
    }

    public function syncToDevices(Request $r){
        $data = $r->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:usersprofile,ID',
            'gate_ids' => 'nullable|array',
            'gate_ids.*' => 'integer|exists:devicegate,id',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer|exists:devicegroup,id',
        ]);
        $gateIds = collect($data['gate_ids'] ?? []);
        if(!empty($data['group_ids'])){
            $groupGateIds = \App\Models\DeviceGate::whereIn('groupid', $data['group_ids'])->pluck('id');
            $gateIds = $gateIds->merge($groupGateIds);
        }
        $gateIds = $gateIds->unique()->values();
        if($gateIds->isEmpty()){
            return response()->json(['ok'=>false,'message'=>'Pilih minimal 1 device gate atau device group'], 422);
        }
        $gates = \App\Models\DeviceGate::whereIn('id', $gateIds)->get();
        $users = UsersProfile::whereIn('ID', $data['user_ids'])->get();
        $ep2 = \App\Models\ApiEndpoint::find(2);
        $ep3 = \App\Models\ApiEndpoint::find(3);
        if(!$ep2 || !$ep3) return response()->json(['ok'=>false,'message'=>'ApiEndpoint 2 atau 3 belum ada (seed dulu)'], 500);
        $results = []; $okTotal = 0; $failTotal = 0;
        foreach($gates as $gate){
            $sn = $gate->sn;
            $accessEntries = [];
            foreach($users as $u){
                $fdata = '';
                if($u->photo){
                    $raw = $u->photo;
                    if(is_string($raw) && str_starts_with($raw, 'data:')){
                        $parts = explode(',', $raw, 2);
                        $fdata = $parts[1] ?? '';
                    } else {
                        $fdata = trim($raw);
                        if(base64_decode($fdata, true)===false) $fdata = base64_encode($raw);
                    }
                }
                if(empty($fdata)) $fdata = '';
                $payload = ['sn' => (string)$sn,'userid' => (string)$u->ID,'username' => (string)$u->NAME,'type' => '50','admin' => '0','cardnumber' => (string)($u->Card ?? '0'),'timezone' => '0','fdata' => $fdata];
                try{
                    $res = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(12)->post($ep2->url, $payload);
                    $ok = $res->successful(); $body = $this->safeJsonBody($res->body());
                    $results[] = ['gate'=>$gate->name.' ('.$sn.')','user'=>$u->NAME,'api'=>'registerface','sn'=>$sn,'userid'=>$u->ID,'ok'=>$ok,'status'=>$res->status(),'body'=>$body];
                    if($ok) $okTotal++; else $failTotal++;
                }catch(\Throwable $e){
                    $results[] = ['gate'=>$gate->name.' ('.$sn.')','user'=>$u->NAME,'api'=>'registerface','ok'=>false,'error'=>$e->getMessage()];
                    $failTotal++; continue;
                }
                $starttime = $u->BEGIN_DATE ? $u->BEGIN_DATE->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
                $endtime = $u->END_DATE ? $u->END_DATE->format('Y-m-d H:i:s') : '2079-12-31 23:59:59';
                $accessEntries[] = ['sn' => (string)$sn,'userid' => (string)$u->ID,'starttime' => $starttime,'endtime' => $endtime,'weekzone' => 0];
                try{ \App\Models\UsersXDevice::firstOrCreate(['userId'=>$u->ID, 'gateId'=>$gate->id], ['Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0]); }catch(\Throwable $e){}
            }
            if(!empty($accessEntries)){
                try{
                    $res2 = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(12)->post($ep3->url, $accessEntries);
                    $ok2 = $res2->successful(); $body2 = $this->safeJsonBody($res2->body());
                    $results[] = ['gate'=>$gate->name.' ('.$sn.')','api'=>'useraccess','count'=>count($accessEntries),'ok'=>$ok2,'status'=>$res2->status(),'body'=>$body2];
                    if(!$ok2) $failTotal++;
                }catch(\Throwable $e){
                    $results[] = ['gate'=>$gate->name.' ('.$sn.')','api'=>'useraccess','ok'=>false,'error'=>$e->getMessage()];
                    $failTotal++;
                }
            }
        }
        return response()->json(['ok'=> $failTotal===0, 'message'=> $okTotal.' berhasil, '.$failTotal.' gagal', 'results'=>$results, 'gates'=>$gates->pluck('name','id'), 'users_count'=> $users->count()]);
    }

    public function removeFromDevices(Request $r){
        $data = $r->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:usersprofile,ID',
            'gate_ids' => 'nullable|array',
            'gate_ids.*' => 'integer|exists:devicegate,id',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer|exists:devicegroup,id',
        ]);
        $gateIds = collect($data['gate_ids'] ?? []);
        if(!empty($data['group_ids'])){
            $groupGateIds = \App\Models\DeviceGate::whereIn('groupid', $data['group_ids'])->pluck('id');
            $gateIds = $gateIds->merge($groupGateIds);
        }
        $gateIds = $gateIds->unique()->values();
        if($gateIds->isEmpty()){
            return response()->json(['ok'=>false,'message'=>'Pilih minimal 1 device gate atau device group'], 422);
        }
        $gates = \App\Models\DeviceGate::whereIn('id', $gateIds)->get();
        $users = UsersProfile::whereIn('ID', $data['user_ids'])->get();
        $ep6 = \App\Models\ApiEndpoint::find(6);
        if(!$ep6) return response()->json(['ok'=>false,'message'=>'ApiEndpoint 6 (deleteface) belum ada'], 500);
        $results = []; $okTotal=0; $failTotal=0;
        foreach($gates as $gate){
            foreach($users as $u){
                $payload = ['sn'=>(string)$gate->sn, 'userid'=>(string)$u->ID];
                try{
                    $res = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(10)->post($ep6->url, $payload);
                    $ok = $res->successful(); $body = $this->safeJsonBody($res->body());
                    $results[] = ['gate'=>$gate->name.' ('.$gate->sn.')','user'=>$u->NAME,'api'=>'deleteface','ok'=>$ok,'status'=>$res->status(),'body'=>$body];
                    if($ok) $okTotal++; else $failTotal++;
                }catch(\Throwable $e){
                    $results[] = ['gate'=>$gate->name.' ('.$gate->sn.')','user'=>$u->NAME,'api'=>'deleteface','ok'=>false,'error'=>$e->getMessage()];
                    $failTotal++;
                }
                try{ \App\Models\UsersXDevice::where('userId',$u->ID)->where('gateId',$gate->id)->delete(); }catch(\Throwable $e){}
            }
        }
        return response()->json(['ok'=> $failTotal===0, 'message'=> $okTotal.' dihapus, '.$failTotal.' gagal', 'results'=>$results]);
    }

    private function safeJsonBody(string $raw): mixed {
        $d = json_decode($raw, true);
        return json_last_error()===JSON_ERROR_NONE ? $d : $raw;
    }

    public function photo(UsersProfile $usersprofile){
        $raw = $usersprofile->photo;
        if(!$raw) abort(404);
        if(is_string($raw) && str_starts_with($raw, 'data:')){
            $parts = explode(',', $raw, 2);
            $bin = base64_decode($parts[1] ?? '');
            return response($bin)->header('Content-Type','image/webp');
        }
        $decoded = base64_decode($raw, true);
        if($decoded !== false && base64_encode($decoded) === trim($raw)){
            return response($decoded)->header('Content-Type','image/webp');
        }
        if(str_starts_with($raw, "\x52\x49\x46\x46")){
            return response($raw)->header('Content-Type','image/webp');
        }
        return response($raw)->header('Content-Type','image/jpeg');
    }

    public function bulkDestroy(\Illuminate\Http\Request $r){
        $r->validate(['ids'=>'required|array','ids.*'=>'integer|exists:usersprofile,ID']);
        $count = \App\Models\UsersProfile::whereIn('ID', $r->ids)->delete();
        return back()->with('ok', $count.' user dihapus');
    }

    /**
     * Push status cuti/aktif ke turnstile device via ApiEndpoint (id=19: /sztimmy/setenableuser)
     * eFlag: "0" = Cuti (Disabled), "1" = Aktif (Enabled)
     */
    protected function pushEnableUserStatus(UsersProfile $user, int $isLeave): void
    {
        try {
            $ep = \App\Models\ApiEndpoint::find(19);
            $url = $ep?->url ?: 'http://localhost:787/sztimmy/setenableuser';

            // Ambil semua device gate yang terhubung
            $devices = \App\Models\DeviceGate::all();
            if ($devices->isEmpty()) return;

            // eFlag: "0" = Cuti, "1" = Aktif
            $eFlag = $isLeave === 1 ? '0' : '1';

            foreach ($devices as $dev) {
                if (empty($dev->sn)) continue;

                $payload = [
                    'sn'     => (string) $dev->sn,
                    'userid' => (string) $user->ID,
                    'eFlag'  => $eFlag,
                ];

                try {
                    \Illuminate\Support\Facades\Http::timeout(3)->post($url, $payload);
                } catch (\Throwable $e) {
                    // silent log
                    \Illuminate\Support\Facades\Log::warning("[pushEnableUserStatus] sn={$dev->sn} user={$user->ID} err: " . $e->getMessage());
                }
            }
        } catch (\Throwable $ex) {
            \Illuminate\Support\Facades\Log::warning("[pushEnableUserStatus global] err: " . $ex->getMessage());
        }
    }

    private function optimizePhotoRawFile($file): ?string {
        if (!$file) return null;
        $tmp = $file->getPathname();
        $bin = @file_get_contents($tmp);
        if (!$bin) return null;
        return $this->optimizePhotoBinary($bin);
    }

    private function optimizePhotoBinary(string $rawBin): ?string {
        $maxBytes = 100 * 1024; // 100 KB

        // Fallback aman: jika ekstensi GD PHP tidak aktif, jangan crash fatal error
        if (!function_exists('imagecreatefromstring')) {
            return $rawBin;
        }

        $src = @imagecreatefromstring($rawBin);
        if (!$src) {
            // Jika format tidak dapat diproses GD, tetap gunakan raw binary jika ada
            return !empty($rawBin) ? $rawBin : null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $maxSide = 640;

        if (max($w, $h) > $maxSide) {
            $ratio = $maxSide / max($w, $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
            $w = $nw;
            $h = $nh;
        }

        $qualities = [82, 75, 68, 60, 50, 40];
        $finalJpeg = null;

        foreach ($qualities as $q) {
            ob_start();
            imagejpeg($src, null, $q);
            $jpegData = ob_get_clean();
            $len = strlen($jpegData);

            if ($len <= $maxBytes) {
                $finalJpeg = $jpegData;
                break;
            }
        }

        $downscaleAttempts = 0;
        while ((!$finalJpeg || strlen($finalJpeg) > $maxBytes) && $downscaleAttempts < 4 && $w > 300) {
            $downscaleAttempts++;
            $w = (int) round($w * 0.8);
            $h = (int) round($h * 0.8);
            $dst = imagecreatetruecolor($w, $h);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
            imagedestroy($src);
            $src = $dst;

            ob_start();
            imagejpeg($src, null, 68);
            $jpegData = ob_get_clean();

            if (strlen($jpegData) <= $maxBytes) {
                $finalJpeg = $jpegData;
                break;
            }
        }

        imagedestroy($src);

        if (!$finalJpeg || strlen($finalJpeg) > $maxBytes) {
            return null;
        }

        return $finalJpeg;
    }

    private function toWebpBase64($file): ?string {
        $maxBytes = 100*1024;
        $tmp = $file->getPathname();
        $bin = @file_get_contents($tmp);
        if(!$bin) return null;
        if(!function_exists('imagecreatefromstring')) {
            return base64_encode($bin);
        }
        $src = @imagecreatefromstring($bin);
        if(!$src){ return base64_encode($bin); }
        $w = imagesx($src); $h = imagesy($src);
        $maxSide = 600;
        if(max($w,$h) > $maxSide){
            $ratio = $maxSide / max($w,$h);
            $nw = (int)round($w*$ratio); $nh = (int)round($h*$ratio);
            $dst = imagecreatetruecolor($nw,$nh);
            imagealphablending($dst,false); imagesavealpha($dst,true);
            imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
            imagedestroy($src); $src=$dst; $w=$nw; $h=$nh;
        }
        $qualities = [80,70,60,50,40,30,20];
        $out = null;
        foreach($qualities as $q){
            ob_start(); imagewebp($src, null, $q); $webp = ob_get_clean();
            if(strlen($webp) <= $maxBytes){ $out=$webp; break; }
            $out=$webp;
        }
        $attempts=0;
        while($out && strlen($out) > $maxBytes && $attempts<3 && $w>200){
            $w = (int)round($w*0.75); $h = (int)round($h*0.75);
            $dst = imagecreatetruecolor($w,$h);
            imagealphablending($dst,false); imagesavealpha($dst,true);
            imagecopyresampled($dst,$src,0,0,0,0,$w,$h,imagesx($src),imagesy($src));
            imagedestroy($src); $src=$dst;
            ob_start(); imagewebp($src,null,40); $out=ob_get_clean();
            $attempts++;
        }
        imagedestroy($src);
        if(!$out) return null;
        if(strlen($out) > $maxBytes){ return null; }
        return base64_encode($out);
    }
}
