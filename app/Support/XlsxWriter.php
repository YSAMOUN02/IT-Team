<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * A small Excel (.xlsx) writer: several sheets of plain rows, a bold header row that stays
 * on screen (frozen) with filter buttons, and column widths that fit the text. Numbers are
 * stored as numbers, so Excel can sum and sort them; Khmer text works as it is (UTF-8).
 */
class XlsxWriter
{
    /** @var array<int, array{name: string, header: array, rows: array}> */
    private array $sheets = [];

    public function sheet(string $name, array $header, iterable $rows): self
    {
        $name = mb_substr(trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name)), 0, 31) ?: 'Sheet'.(count($this->sheets) + 1);
        $this->sheets[] = ['name' => $name, 'header' => array_values($header), 'rows' => array_map('array_values', is_array($rows) ? $rows : iterator_to_array($rows, false))];

        return $this;
    }

    public function save(string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is needed to make Excel files.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot write $path");
        }

        $sheets = $this->sheets ?: [['name' => 'Sheet1', 'header' => [], 'rows' => []]];
        $overrides = '';
        $entries = '';
        $rels = '';
        foreach ($sheets as $i => $sheet) {
            $n = $i + 1;
            $zip->addFromString("xl/worksheets/sheet$n.xml", $this->sheetXml($sheet));
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $entries .= '<sheet name="'.$this->esc($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }
        $styles = count($sheets) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$entries.'</sheets>'
            .$this->filterNames($sheets)
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels
            .'<Relationship Id="rId'.$styles.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        // Style 0: normal. Style 1: header (bold, white on dark blue). Style 2: wrapped text.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F2A5C"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>'
            .'</styleSheet>');
        $zip->close();
    }

    /** Send the workbook to the browser as a download; the temporary file is removed afterwards. */
    public function download(string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->save($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function sheetXml(array $sheet): string
    {
        $all = $sheet['header'] ? array_merge([$sheet['header']], $sheet['rows']) : $sheet['rows'];
        $columns = max(array_map('count', $all ?: [[]])) ?: 1;

        // Width: the longest text in the column (header included), 8 to 60 characters.
        $cols = '';
        for ($c = 0; $c < $columns; $c++) {
            $width = 8;
            foreach ($all as $row) {
                $width = max($width, min(60, mb_strwidth((string) ($row[$c] ?? '')) + 2));
            }
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $data = '';
        foreach ($all as $r => $row) {
            $header = $sheet['header'] && $r === 0;
            $data .= '<row r="'.($r + 1).'">';
            foreach ($row as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $ref = self::column($c).($r + 1);
                $style = $header ? ' s="1"' : (is_string($value) && mb_strlen($value) > 60 ? ' s="2"' : '');
                if (! $header && (is_int($value) || is_float($value))) {
                    $data .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $data .= '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$this->esc((string) $value).'</t></is></c>';
                }
            }
            $data .= '</row>';
        }

        $last = self::column($columns - 1).max(1, count($all));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0">'
            .($sheet['header'] ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '')
            .'</sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.$data.'</sheetData>'
            .($sheet['header'] ? '<autoFilter ref="A1:'.$last.'"/>' : '')
            .'</worksheet>';
    }

    /** Excel needs a hidden name per sheet for its filter buttons. */
    private function filterNames(array $sheets): string
    {
        $names = '';
        foreach ($sheets as $i => $sheet) {
            if (! $sheet['header']) {
                continue;
            }
            $all = count($sheet['rows']) + 1;
            $columns = max(array_map('count', array_merge([$sheet['header']], $sheet['rows'])));
            $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">\''
                .$this->esc(str_replace("'", "''", $sheet['name'])).'\'!$A$1:$'.self::column($columns - 1).'$'.$all.'</definedName>';
        }

        return $names ? '<definedNames>'.$names.'</definedNames>' : '';
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    public static function column(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26).$name;
        }

        return $name;
    }

    /** XML-safe text, without the control characters XML does not allow. */
    private function esc(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
