<?php

namespace App\Services;

use ZipArchive;
use SimpleXMLElement;
use Exception;

class ExcelReaderService
{
    /**
     * Membaca file spreadsheet (.xlsx, .csv, .xls XML, atau .tsv) dan mengembalikan array 2 dimensi.
     * Baris pertama biasanya adalah header.
     *
     * @param string $filePath
     * @return array
     */
    public function read(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File tidak ditemukan: {$filePath}");
        }

        // Cek ekstensi / MIME
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Jika XLSX (format ZIP XML)
        if ($ext === 'xlsx' || $this->isZipFile($filePath)) {
            $data = $this->readXlsx($filePath);
            if (!empty($data)) {
                return $data;
            }
        }

        // Jika CSV atau TSV
        if ($ext === 'csv' || $ext === 'txt') {
            return $this->readCsv($filePath, ',');
        }

        if ($ext === 'tsv') {
            return $this->readCsv($filePath, "\t");
        }

        // Coba deteksi delimiter CSV secara otomatis
        return $this->autoDetectAndReadDelimited($filePath);
    }

    /**
     * Parser native XLSX (Office Open XML) berbasis ZipArchive + SimpleXML
     */
    protected function readXlsx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        // 1. Baca shared strings
        $sharedStrings = [];
        if (($sharedXmlStr = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = @simplexml_load_string($sharedXmlStr);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)($r->t ?? '');
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Baca sheet1.xml (atau worksheet pertama yang ada)
        $sheetName = 'xl/worksheets/sheet1.xml';
        if ($zip->locateName($sheetName) === false) {
            // Cari worksheet pertama di folder xl/worksheets/
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                    $sheetName = $name;
                    break;
                }
            }
        }

        $rows = [];
        if (($sheetXmlStr = $zip->getFromName($sheetName)) !== false) {
            $sheetXml = @simplexml_load_string($sheetXmlStr);
            if ($sheetXml && isset($sheetXml->sheetData->row)) {
                foreach ($sheetXml->sheetData->row as $r) {
                    $colData = [];
                    $maxColIndex = 0;

                    foreach ($r->c as $c) {
                        $cellRef = (string)$c['r']; // e.g. A1, B2, AA5
                        $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                        $colIdx = $this->columnLetterToIndex($colLetters);
                        if ($colIdx > $maxColIndex) {
                            $maxColIndex = $colIdx;
                        }

                        $type = (string)($c['t'] ?? '');
                        $val = '';

                        if (isset($c->v)) {
                            $rawVal = (string)$c->v;
                            if ($type === 's') {
                                $val = $sharedStrings[(int)$rawVal] ?? '';
                            } elseif ($type === 'b') {
                                $val = $rawVal === '1' ? 'TRUE' : 'FALSE';
                            } else {
                                $val = $rawVal;
                            }
                        } elseif (isset($c->is->t)) {
                            $val = (string)$c->is->t;
                        }

                        $colData[$colIdx] = trim($val);
                    }

                    // Susun row sesuai urutan kolom 0, 1, 2...
                    if (!empty($colData)) {
                        $rowArr = [];
                        for ($i = 0; $i <= $maxColIndex; $i++) {
                            $rowArr[$i] = $colData[$i] ?? '';
                        }
                        // Lewati baris yang seluruhnya kosong
                        if (array_filter($rowArr, fn($v) => $v !== '')) {
                            $rows[] = $rowArr;
                        }
                    }
                }
            }
        }

        $zip->close();
        return $rows;
    }

    /**
     * Konversi huruf kolom Excel (A, B, ..., Z, AA, AB) ke index 0-based
     */
    protected function columnLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $len = strlen($letters);
        $idx = 0;
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $idx - 1;
    }

    /**
     * Baca file format CSV
     */
    protected function readCsv(string $filePath, string $delimiter = ','): array
    {
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 10000, $delimiter)) !== false) {
                if (array_filter($data, fn($v) => trim((string)$v) !== '')) {
                    $rows[] = array_map(fn($v) => trim((string)$v), $data);
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    /**
     * Otomatis deteksi koma (,), titik koma (;), atau tab (\t)
     */
    protected function autoDetectAndReadDelimited(string $filePath): array
    {
        $firstLine = '';
        if (($handle = fopen($filePath, 'r')) !== false) {
            $firstLine = fgets($handle);
            fclose($handle);
        }

        $delimiters = [',', ';', "\t"];
        $bestDelimiter = ',';
        $maxCount = 0;

        foreach ($delimiters as $delim) {
            $count = substr_count($firstLine, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        return $this->readCsv($filePath, $bestDelimiter);
    }

    protected function isZipFile(string $filePath): bool
    {
        $fh = fopen($filePath, 'rb');
        if (!$fh) return false;
        $bytes = fread($fh, 4);
        fclose($fh);
        return $bytes === "PK\x03\x04";
    }
}
