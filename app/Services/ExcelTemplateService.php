<?php

namespace App\Services;

use ZipArchive;

class ExcelTemplateService
{
    /**
     * Membuat file .xlsx murni (Office Open XML) langsung menggunakan ZipArchive + XML tanpa dependensi external.
     * Mengembalikan raw binary string dari file XLSX.
     */
    /**
     * Ekspor data seluruh pengguna ke format asli XLSX murni (OpenXML) dengan tipe STRING eksplisit
     * sehingga angka panjang (NIK 16 digit & nomor kartu) tidak terpotong menjadi notasi ilmiah (3.17E+15).
     */
    public function exportUsersXlsx($users): string
    {
        $headers = [
            'ID',
            'EMPLOYEE_ID',
            'Nama / Name',
            'NIK',
            'RFID Card',
            'Cabang',
            'Departemen',
            'Tipe',
            'Begin',
            'End',
        ];

        $dataRows = [];
        foreach ($users as $u) {
            $typeLabel = match ((int)$u->user_type) {
                1 => 'MEMBER',
                2 => 'KARYAWAN',
                default => 'UNKNOWN'
            };

            $dataRows[] = [
                (string)$u->ID,
                (string)($u->EMPLOYEE_ID ?? ''),
                (string)($u->NAME ?? ''),
                (string)($u->NoIdentitas ?? ''),
                (string)($u->Card ?? ''),
                (string)($u->branch->name ?? '-'),
                (string)($u->departemen->name ?? '-'),
                $typeLabel,
                $u->BEGIN_DATE ? $u->BEGIN_DATE->format('Y-m-d') : '-',
                $u->END_DATE ? $u->END_DATE->format('Y-m-d') : '-',
            ];
        }

        return $this->buildXlsx($headers, $dataRows);
    }

    public function generateTemplateXlsx(): string
    {
        $headers = [
            'EMPLOYEE_ID',
            'NAME',
            'NoIdentitas',
            'Card',
            'Cabang',
            'Departemen',
            'Tipe (Karyawan / Member)',
            'BEGIN_DATE',
            'END_DATE',
            'BIRTHDAY',
            'JOB_POSITION',
        ];

        // Ambil nama cabang dan departemen pertama yang ada di database secara dinamis
        $firstBranch = \App\Models\Branch::first();
        $firstDept   = \App\Models\Departemen::first();

        $branchName = $firstBranch ? $firstBranch->name : 'Cabang Casablanca';
        $deptName   = $firstDept ? $firstDept->name : 'IT';

        // Tampilkan hanya 1 baris contoh sesuai permintaan Fariz
        $sampleRows = [
            [
                'EMP001',
                'Budi Santoso',
                '3171012304900001',
                '7036491',
                $branchName,
                $deptName,
                'Karyawan',
                date('Y-m-d'),
                '2079-12-31',
                '1990-04-23',
                'Staff Keamanan',
            ],
        ];

        return $this->buildXlsx($headers, $sampleRows);
    }

    protected function buildXlsx(array $headers, array $dataRows): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmpl_') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Template Import Users" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // 5. xl/styles.xml (Header warna merah gelap IDS dengan font putih tebal)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Calibri"/><sz val="11"/></font>
    <font><b/><color rgb="FFFFFFFF"/><name val="Calibri"/><sz val="11"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDC2626"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/></border>
    <border>
      <left style="thin"><color rgb="FFD1D5DB"/></left>
      <right style="thin"><color rgb="FFD1D5DB"/></right>
      <top style="thin"><color rgb="FFD1D5DB"/></top>
      <bottom style="thin"><color rgb="FFD1D5DB"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
  </cellXfs>
</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. Kumpulkan shared strings
        $sharedStrings = [];
        $stringIndexMap = [];
        $getStringIdx = function (string $val) use (&$sharedStrings, &$stringIndexMap) {
            if (isset($stringIndexMap[$val])) {
                return $stringIndexMap[$val];
            }
            $idx = count($sharedStrings);
            $sharedStrings[] = $val;
            $stringIndexMap[$val] = $idx;
            return $idx;
        };

        // Header cells
        $sheetRowsXml = '<row r="1" ht="26" customHeight="1">';
        foreach ($headers as $colIdx => $h) {
            $colLetter = $this->indexToColumnLetter($colIdx);
            $sIdx = $getStringIdx($h);
            $sheetRowsXml .= '<c r="' . $colLetter . '1" t="s" s="1"><v>' . $sIdx . '</v></c>';
        }
        $sheetRowsXml .= '</row>';

        // Data rows
        $rowNum = 2;
        foreach ($dataRows as $row) {
            $sheetRowsXml .= '<row r="' . $rowNum . '" ht="20" customHeight="1">';
            foreach ($row as $colIdx => $val) {
                $colLetter = $this->indexToColumnLetter($colIdx);
                $sIdx = $getStringIdx((string)$val);
                $sheetRowsXml .= '<c r="' . $colLetter . $rowNum . '" t="s" s="2"><v>' . $sIdx . '</v></c>';
            }
            $sheetRowsXml .= '</row>';
            $rowNum++;
        }

        // Shared strings XML
        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
        foreach ($sharedStrings as $str) {
            $clean = htmlspecialchars($str, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $sstXml .= '<si><t>' . $clean . '</t></si>';
        }
        $sstXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sstXml);

        // Sheet1 XML
        $colWidths = '<cols>';
        $widths = [16, 24, 22, 16, 24, 20, 26, 16, 16, 16, 20];
        foreach ($widths as $i => $w) {
            $colNum = $i + 1;
            $colWidths .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $w . '" customWidth="1"/>';
        }
        $colWidths .= '</cols>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  ' . $colWidths . '
  <sheetData>
    ' . $sheetRowsXml . '
  </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);
        return $content;
    }

    protected function indexToColumnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $index = (int)(($index - $modulo) / 26);
        }
        return $letter;
    }
}
