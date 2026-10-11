<?php
/**
 * Offline regression coverage for valid XLSX namespace layouts.
 * Run: php database/tests/xlsx-reader.php
 */
require __DIR__ . '/../../rosariosis/ProgramFunctions/AbugidaXlsx.fnc.php';
if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
    fwrite(STDERR, "The zip and SimpleXML extensions are required.\n");
    exit(1);
}
$path = tempnam(sys_get_temp_dir(), 'abg-xlsx-');
try {
    foreach (['default' => '', 'prefixed' => 'x:'] as $name => $prefix) {
        $namespace = $prefix ? 'xmlns:x' : 'xmlns';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml',
            '<'.$prefix.'sst '.$namespace.'="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<'.$prefix.'si><'.$prefix.'t>Student ID</'.$prefix.'t></'.$prefix.'si></'.$prefix.'sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<'.$prefix.'worksheet '.$namespace.'="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<'.$prefix.'sheetData><'.$prefix.'row r="1">'.
            '<'.$prefix.'c r="A1" t="s"><'.$prefix.'v>0</'.$prefix.'v></'.$prefix.'c>'.
            '<'.$prefix.'c r="B1" t="inlineStr"><'.$prefix.'is><'.$prefix.'t>Final Score</'.$prefix.'t></'.$prefix.'is></'.$prefix.'c>'.
            '</'.$prefix.'row><'.$prefix.'row r="2"><'.$prefix.'c r="A2"><'.$prefix.'v>8</'.$prefix.'v></'.$prefix.'c>'.
            '<'.$prefix.'c r="B2"><'.$prefix.'v>87</'.$prefix.'v></'.$prefix.'c></'.$prefix.'row>'.
            '</'.$prefix.'sheetData></'.$prefix.'worksheet>');
        $zip->close();
        $error = '';
        $actual = AbugidaReadXlsx($path, $error);
        if ($actual !== [['Student ID', 'Final Score'], ['8', '87']]) {
            throw new RuntimeException($name.' namespace failed: '.$error);
        }
        echo 'PASS: '.$name." namespace, shared strings, inline strings, and numbers\n";
    }
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<broken');
    $zip->close();
    $error = '';
    if (AbugidaReadXlsx($path, $error) !== false || $error !== 'The first worksheet is not readable.') {
        throw new RuntimeException('Malformed worksheet was not rejected.');
    }
    echo "PASS: malformed worksheet rejected\n";
} finally {
    unlink($path);
}
