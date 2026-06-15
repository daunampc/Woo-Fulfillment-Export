<?php

defined('ABSPATH') || exit;

final class WFE_Template_Inspector
{
    public function inspect(array $template, int $sheet_index = 0, int $header_row = 1): array
    {
        if (($template['source'] ?? '') === 'manual') {
            return $this->inspect_manual($template);
        }

        if (($template['file_type'] ?? '') === 'csv') {
            return $this->inspect_csv($template);
        }

        if (($template['file_type'] ?? '') === 'xlsx') {
            return $this->inspect_xlsx($template, $sheet_index, $header_row);
        }

        return [
            'sheets' => [],
            'headers' => [],
            'error' => __('Unsupported template type.', 'woo-fulfillment-export'),
        ];
    }

    private function inspect_manual(array $template): array
    {
        $headers = [];
        foreach ((array) ($template['columns'] ?? []) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }

            $key = WFE_Mapping_Repository::sanitize_column_key((string) ($column['column'] ?? WFE_Mapping_Repository::column_letter((int) $index + 1)));
            if ($key === '') {
                continue;
            }

            $headers[$key] = sanitize_text_field((string) ($column['header'] ?? $key));
        }

        return [
            'sheets' => [['index' => 0, 'name' => __('Manual columns', 'woo-fulfillment-export')]],
            'headers' => $headers,
            'error' => '',
        ];
    }

    private function inspect_csv(array $template): array
    {
        $path = (string) ($template['file_path'] ?? '');
        if ($path === '' || !WFE_Template_Repository::is_template_path($path) || !file_exists($path)) {
            return [
                'sheets' => [],
                'headers' => [],
                'error' => __('CSV template file not found.', 'woo-fulfillment-export'),
            ];
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return [
                'sheets' => [],
                'headers' => [],
                'error' => __('Could not read CSV template.', 'woo-fulfillment-export'),
            ];
        }

        $row = fgetcsv($handle);
        fclose($handle);

        $headers = [];
        if (is_array($row)) {
            foreach ($row as $index => $label) {
                $column = WFE_Mapping_Repository::column_letter((int) $index + 1);
                $headers[$column] = sanitize_text_field((string) $label);
            }
        }

        return [
            'sheets' => [['index' => 0, 'name' => __('CSV header', 'woo-fulfillment-export')]],
            'headers' => $headers,
            'error' => '',
        ];
    }

    private function inspect_xlsx(array $template, int $sheet_index, int $header_row): array
    {
        if (!class_exists('ZipArchive')) {
            return [
                'sheets' => [],
                'headers' => [],
                'error' => __('PHP ZipArchive extension is required to inspect XLSX files.', 'woo-fulfillment-export'),
            ];
        }

        $path = (string) ($template['file_path'] ?? '');
        if ($path === '' || !WFE_Template_Repository::is_template_path($path) || !file_exists($path)) {
            return [
                'sheets' => [],
                'headers' => [],
                'error' => __('XLSX template file not found.', 'woo-fulfillment-export'),
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [
                'sheets' => [],
                'headers' => [],
                'error' => __('Could not open XLSX template.', 'woo-fulfillment-export'),
            ];
        }

        $sheets = $this->xlsx_sheets($zip);
        $shared_strings = $this->xlsx_shared_strings($zip);
        $sheet_path = $this->sheet_path_by_index($zip, $sheet_index);
        $headers = [];

        if ($sheet_path !== null) {
            $xml = $zip->getFromName($sheet_path);
            if ($xml !== false) {
                $headers = $this->xlsx_row_values($xml, $shared_strings, $header_row);
            }
        }

        $zip->close();

        return [
            'sheets' => $sheets,
            'headers' => $headers,
            'error' => '',
        ];
    }

    private function xlsx_sheets(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/workbook.xml');
        $sheets = [];

        if ($xml !== false) {
            $dom = new DOMDocument();
            if (@$dom->loadXML($xml)) {
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($xpath->query('//x:sheets/x:sheet') as $index => $sheet) {
                    if ($sheet instanceof DOMElement) {
                        $sheets[] = [
                            'index' => (int) $index,
                            'name' => $sheet->getAttribute('name') ?: sprintf(__('Sheet %d', 'woo-fulfillment-export'), (int) $index + 1),
                        ];
                    }
                }
            }
        }

        if ($sheets) {
            return $sheets;
        }

        $paths = $this->worksheet_paths($zip);
        foreach ($paths as $index => $path) {
            $sheets[] = [
                'index' => (int) $index,
                'name' => basename($path, '.xml'),
            ];
        }

        return $sheets;
    }

    private function xlsx_shared_strings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($xpath->query('//x:si') as $si) {
            $strings[] = $si instanceof DOMElement ? $si->textContent : '';
        }

        return $strings;
    }

    private function xlsx_row_values(string $xml, array $shared_strings, int $row_number): array
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $row = $xpath->query('//x:sheetData/x:row[@r="' . $row_number . '"]')->item(0);
        if (!$row instanceof DOMElement) {
            return [];
        }

        $headers = [];
        $position = 1;
        foreach ($xpath->query('x:c', $row) as $cell) {
            if (!$cell instanceof DOMElement) {
                continue;
            }

            $ref = $cell->getAttribute('r');
            if (preg_match('/^([A-Z]+)/i', $ref, $match)) {
                $column = strtoupper($match[1]);
                $position = WFE_Mapping_Repository::column_index($column);
            } else {
                $column = WFE_Mapping_Repository::column_letter($position);
            }

            $headers[$column] = sanitize_text_field($this->xlsx_cell_value($xpath, $cell, $shared_strings));
            $position++;
        }

        return $headers;
    }

    private function xlsx_cell_value(DOMXPath $xpath, DOMElement $cell, array $shared_strings): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $inline = $xpath->query('x:is', $cell)->item(0);
            return $inline instanceof DOMElement ? $inline->textContent : '';
        }

        $value = $xpath->query('x:v', $cell)->item(0);
        $text = $value instanceof DOMElement ? $value->textContent : '';

        if ($type === 's') {
            $index = absint($text);
            return (string) ($shared_strings[$index] ?? '');
        }

        return (string) $text;
    }

    private function sheet_path_by_index(ZipArchive $zip, int $index): ?string
    {
        $paths = $this->worksheet_paths($zip);
        return $paths[$index] ?? null;
    }

    private function worksheet_paths(ZipArchive $zip): array
    {
        // TODO: Resolve workbook relationship targets for heavily customized XLSX files.
        $paths = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string) $name)) {
                $paths[] = (string) $name;
            }
        }
        sort($paths, SORT_NATURAL);

        return $paths;
    }
}
