<?php
/**
 * Compile les .po du thème schilo en .mo (format gettext binaire), sans msgfmt.
 * Usage : php mo_compile.php <languages_dir>
 */
require __DIR__ . "/po-lib.php";

function po_header_string(string $file): string {
    $raw = str_replace("\r\n", "\n", file_get_contents($file));
    [$hdr, ] = explode("\n\n", $raw, 2);
    $out = ''; $collect = false;
    foreach (explode("\n", $hdr) as $l) {
        if (preg_match('/^msgstr "(.*)"$/', $l, $m)) { $collect = true; $out .= po_unescape($m[1]); continue; }
        if ($collect && preg_match('/^"(.*)"$/', $l, $m)) { $out .= po_unescape($m[1]); }
        elseif ($collect && trim($l) === '') break;
    }
    return $out;
}

function mo_write(string $file, array $map): void {
    ksort($map, SORT_STRING);
    $keys = array_keys($map);
    $n = count($keys);
    $dataStart = 28 + 8 * $n + 8 * $n; // header + table originaux + table traductions (hash=0)

    $origTable = ''; $trTable = ''; $origData = ''; $trData = '';
    $pos = $dataStart;
    foreach ($keys as $k) {
        $len = strlen($k);
        $origTable .= pack('VV', $len, $pos);
        $origData  .= $k . "\0";
        $pos += $len + 1;
    }
    foreach ($keys as $k) {
        $v = $map[$k]; $len = strlen($v);
        $trTable .= pack('VV', $len, $pos);
        $trData  .= $v . "\0";
        $pos += $len + 1;
    }
    $header = pack('V', 0x950412de) . pack('V', 0) . pack('V', $n)
            . pack('V', 28) . pack('V', 28 + 8 * $n)
            . pack('V', 0)  . pack('V', 28 + 8 * $n + 8 * $n);
    file_put_contents($file, $header . $origTable . $trTable . $origData . $trData);
}

$dir = rtrim($argv[1], '/\\');
foreach (['en_US','de_DE','es_ES','pt_PT','it_IT'] as $loc) {
    $po = $dir . '/schilo-' . $loc . '.po';
    $mo = $dir . '/schilo-' . $loc . '.mo';
    if (!is_file($po)) { echo "  MANQUE $po\n"; continue; }

    $map = ['' => po_header_string($po)];
    $n = 0;
    foreach (po_parse($po) as $e) {
        if ($e['plural'] !== null) {
            if ($e['str0'] === '' && $e['str1'] === '') continue;
            $map[$e['msgid'] . "\0" . $e['plural']] = $e['str0'] . "\0" . $e['str1'];
        } else {
            if ($e['str'] === '') continue;
            $orig = ($e['ctxt'] !== null && $e['ctxt'] !== '') ? $e['ctxt'] . "\x04" . $e['msgid'] : $e['msgid'];
            $map[$orig] = $e['str'];
        }
        $n++;
    }
    mo_write($mo, $map);
    echo sprintf("  %-6s : %d entrées → %s (%d octets)\n", $loc, $n, basename($mo), filesize($mo));
}
