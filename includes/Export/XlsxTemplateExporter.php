<?php

defined('ABSPATH') || exit;

final class WFE_Xlsx_Template_Exporter
{
    public function download(array $template, array $mapping, array $rows): void
    {
<<<<<<< HEAD
        if (!class_exists('ZipArchive')) {
            wp_die('PHP ZipArchive extension is required to export XLSX files.');
        }

=======
>>>>>>> 33573ee (first commit)
        $tmp = wp_tempnam('wfe-export.xlsx');
        if (!$tmp) {
            wp_die('Could not prepare export file.');
        }

<<<<<<< HEAD
        if (($template['source'] ?? 'upload') === 'manual') {
            $this->create_simple_workbook($tmp, $template, $mapping, $rows);
            $this->send_file($tmp);
        }

        $source = $template['file_path'];
        if (!WFE_Template_Repository::is_template_path((string) $source) || !copy($source, $tmp)) {
=======
        $this->save_file($template, $mapping, $rows, $tmp);
        $this->send_file($tmp);
    }

    public function save_file(array $template, array $mapping, array $rows, string $target): void
    {
        if (!class_exists('ZipArchive')) {
            wp_die('PHP ZipArchive extension is required to export XLSX files.');
        }

        if (($template['source'] ?? 'upload') === 'manual') {
            $this->create_simple_workbook($target, $template, $mapping, $rows);
            return;
        }

        $source = $template['file_path'];
        if (!WFE_Template_Repository::is_template_path((string) $source) || !copy($source, $target)) {
>>>>>>> 33573ee (first commit)
            wp_die('Could not prepare export file.');
        }

        $sheet_index = max(0, absint($mapping['sheet_index'] ?? 0));
        $start_row = max(1, absint($mapping['start_row'] ?? 2));
        $columns = WFE_Mapping_Repository::export_columns($template, $mapping);

        $zip = new ZipArchive();
<<<<<<< HEAD
        if ($zip->open($tmp) !== true) {
=======
        if ($zip->open($target) !== true) {
>>>>>>> 33573ee (first commit)
            wp_die('Could not open XLSX template.');
        }

        $sheet_path = $this->sheet_path_by_index($zip, $sheet_index);
        if ($sheet_path === null) {
            $zip->close();
            wp_die('Worksheet not found in template.');
        }

        $xml = $zip->getFromName($sheet_path);
        if ($xml === false) {
            $zip->close();
            wp_die('Could not read worksheet XML.');
        }

        $xml = $this->write_rows_to_sheet_xml($xml, $rows, $columns, $start_row);
        $zip->deleteName($sheet_path);
        $zip->addFromString($sheet_path, $xml);
        $zip->close();
<<<<<<< HEAD

        $this->send_file($tmp);
=======
>>>>>>> 33573ee (first commit)
    }

    private function send_file(string $tmp): void
    {
        $filename = sanitize_file_name('fulfillment-orders-' . current_time('Y-m-d-His') . '.xlsx');

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function sheet_path_by_index(ZipArchive $zip, int $index): ?string
    {
        $target = 'xl/worksheets/sheet' . ($index + 1) . '.xml';
        if ($zip->locateName($target) !== false) {
            return $target;
        }

        $paths = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string) $name)) {
                $paths[] = (string) $name;
            }
        }
        sort($paths, SORT_NATURAL);
        return $paths[$index] ?? null;
    }

    private function write_rows_to_sheet_xml(string $xml, array $rows, array $columns, int $start_row): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $sheetData = $xpath->query('//x:sheetData')->item(0);
        if (!$sheetData) {
            $worksheet = $dom->documentElement;
            $sheetData = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheetData');
            $worksheet->appendChild($sheetData);
        }

        foreach ($rows as $offset => $rowData) {
            $rowNumber = $start_row + $offset;
            $rowNode = $this->get_or_create_row($dom, $xpath, $sheetData, $rowNumber);

            foreach ($columns as $column => $column_config) {
                $cellRef = strtoupper($column) . $rowNumber;
                $value = WFE_Mapping_Repository::value_for_column($column_config, is_array($rowData) ? $rowData : []);
                $cell = $this->get_or_create_cell($dom, $xpath, $rowNode, $cellRef, strtoupper((string) $column));
                $this->set_inline_string($dom, $cell, $value);
            }
        }

        $this->update_dimension($dom, $xpath, $columns, $start_row, count($rows));

        return $dom->saveXML();
    }

    private function get_or_create_row(DOMDocument $dom, DOMXPath $xpath, DOMElement $sheetData, int $rowNumber): DOMElement
    {
        $existing = $xpath->query('x:row[@r="' . $rowNumber . '"]', $sheetData)->item(0);
        if ($existing instanceof DOMElement) {
            return $existing;
        }

        $row = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
        $row->setAttribute('r', (string) $rowNumber);

        $insertBefore = null;
        foreach ($sheetData->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'row' && (int) $child->getAttribute('r') > $rowNumber) {
                $insertBefore = $child;
                break;
            }
        }

        if ($insertBefore) {
            $sheetData->insertBefore($row, $insertBefore);
        } else {
            $sheetData->appendChild($row);
        }

        return $row;
    }

    private function get_or_create_cell(DOMDocument $dom, DOMXPath $xpath, DOMElement $rowNode, string $cellRef, string $column): DOMElement
    {
        $existing = $xpath->query('x:c[@r="' . $cellRef . '"]', $rowNode)->item(0);
        if ($existing instanceof DOMElement) {
            return $existing;
        }

        $cell = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
        $cell->setAttribute('r', $cellRef);

        $insertBefore = null;
        $newIndex = $this->column_index($column);
        foreach ($rowNode->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->tagName !== 'c') {
                continue;
            }
            preg_match('/^([A-Z]+)/', $child->getAttribute('r'), $m);
            $childIndex = $this->column_index($m[1] ?? 'A');
            if ($childIndex > $newIndex) {
                $insertBefore = $child;
                break;
            }
        }

        if ($insertBefore) {
            $rowNode->insertBefore($cell, $insertBefore);
        } else {
            $rowNode->appendChild($cell);
        }

        return $cell;
    }

    private function set_inline_string(DOMDocument $dom, DOMElement $cell, string $value): void
    {
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        $cell->setAttribute('t', 'inlineStr');
        $is = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'is');
        $t = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't');
        $t->appendChild($dom->createTextNode($value));
        if (trim($value) !== $value || strpos($value, "\n") !== false) {
            $t->setAttribute('xml:space', 'preserve');
        }
        $is->appendChild($t);
        $cell->appendChild($is);
    }

    private function update_dimension(DOMDocument $dom, DOMXPath $xpath, array $columns, int $startRow, int $count): void
    {
        if (!$columns || $count <= 0) {
            return;
        }
        $maxColumn = 'A';
        foreach (array_keys($columns) as $column) {
            if ($this->column_index($column) > $this->column_index($maxColumn)) {
                $maxColumn = $column;
            }
        }
        $lastRow = $startRow + $count - 1;
        $dimension = $xpath->query('//x:dimension')->item(0);
        if ($dimension instanceof DOMElement) {
            $dimension->setAttribute('ref', 'A1:' . $maxColumn . $lastRow);
        }
    }

    private function column_index(string $column): int
    {
        return WFE_Mapping_Repository::column_index($column);
    }

    private function create_simple_workbook(string $tmp, array $template, array $mapping, array $rows): void
    {
        $columns = WFE_Mapping_Repository::export_columns($template, $mapping);
        if (!$columns) {
            wp_die('No mapped columns found for XLSX export.');
        }

        $sheet_xml = $this->simple_sheet_xml($columns, $rows);

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            wp_die('Could not create XLSX export file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->content_types_xml());
        $zip->addFromString('_rels/.rels', $this->root_rels_xml());
        $zip->addFromString('docProps/app.xml', $this->app_xml());
        $zip->addFromString('docProps/core.xml', $this->core_xml());
        $zip->addFromString('xl/workbook.xml', $this->workbook_xml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbook_rels_xml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
        $zip->close();
    }

    private function simple_sheet_xml(array $columns, array $rows): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $worksheet = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'worksheet');
        $worksheet->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $dom->appendChild($worksheet);

        $last_column = $this->last_column($columns);
        $last_row = max(1, count($rows) + 1);
        $dimension = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'dimension');
        $dimension->setAttribute('ref', 'A1:' . $last_column . $last_row);
        $worksheet->appendChild($dimension);

        $sheet_data = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheetData');
        $worksheet->appendChild($sheet_data);

        $header_row = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
        $header_row->setAttribute('r', '1');
        $sheet_data->appendChild($header_row);

        foreach ($columns as $column_key => $column) {
            $cell = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
            $cell->setAttribute('r', $column_key . '1');
            $header_row->appendChild($cell);
            $this->set_inline_string($dom, $cell, (string) ($column['header'] ?? $column_key));
        }

        foreach ($rows as $offset => $row_data) {
            $row_number = $offset + 2;
            $row = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
            $row->setAttribute('r', (string) $row_number);
            $sheet_data->appendChild($row);

            foreach ($columns as $column_key => $column) {
                $cell = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
                $cell->setAttribute('r', $column_key . $row_number);
                $row->appendChild($cell);
                $this->set_inline_string($dom, $cell, WFE_Mapping_Repository::value_for_column($column, is_array($row_data) ? $row_data : []));
            }
        }

        return $dom->saveXML();
    }

    private function last_column(array $columns): string
    {
        $last = 'A';
        foreach (array_keys($columns) as $column) {
            if ($this->column_index((string) $column) > $this->column_index($last)) {
                $last = (string) $column;
            }
        }
        return $last;
    }

    private function content_types_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function root_rels_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbook_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Fulfillment" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbook_rels_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    private function app_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Woo Fulfillment Export</Application>'
            . '</Properties>';
    }

    private function core_xml(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $safe_now = htmlspecialchars($now, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Woo Fulfillment Export</dc:creator>'
            . '<cp:lastModifiedBy>Woo Fulfillment Export</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $safe_now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $safe_now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }
}
