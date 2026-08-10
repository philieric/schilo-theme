<?php
/**
 * Bibliothèque de parsing/écriture PO minimale pour le thème schilo.
 */

/** Parse un fichier .po/.pot → liste d'entrées. */
function po_parse(string $file): array {
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $entries = [];
    $cur = null;
    $mode = null; // 'ctxt','id','plural','str','str0','str1'
    $flush = function () use (&$cur, &$entries) {
        if ($cur !== null && ($cur['msgid'] !== '' )) {
            $entries[] = $cur;
        }
        $cur = null;
    };
    foreach ($lines as $ln) {
        if (preg_match('/^msgctxt "(.*)"$/', $ln, $m)) { if ($cur && isset($cur['msgid'])) $flush(); $cur = ['ctxt'=>po_unescape($m[1]),'msgid'=>'','plural'=>null,'str'=>'','str0'=>'','str1'=>'','refs'=>[]]; $mode='ctxt'; continue; }
        if (preg_match('/^msgid "(.*)"$/', $ln, $m)) {
            if ($cur && $cur['msgid'] !== '') $flush();
            if ($cur === null) $cur = ['ctxt'=>null,'msgid'=>'','plural'=>null,'str'=>'','str0'=>'','str1'=>'','refs'=>[]];
            $cur['msgid'] = po_unescape($m[1]); $mode='id'; continue;
        }
        if (preg_match('/^msgid_plural "(.*)"$/', $ln, $m)) { $cur['plural']=po_unescape($m[1]); $mode='plural'; continue; }
        if (preg_match('/^msgstr\[0\] "(.*)"$/', $ln, $m)) { $cur['str0']=po_unescape($m[1]); $mode='str0'; continue; }
        if (preg_match('/^msgstr\[1\] "(.*)"$/', $ln, $m)) { $cur['str1']=po_unescape($m[1]); $mode='str1'; continue; }
        if (preg_match('/^msgstr "(.*)"$/', $ln, $m)) { $cur['str']=po_unescape($m[1]); $mode='str'; continue; }
        if (preg_match('/^"(.*)"$/', $ln, $m)) { // continuation
            $v = po_unescape($m[1]);
            switch ($mode) {
                case 'id': $cur['msgid'].=$v; break;
                case 'plural': $cur['plural'].=$v; break;
                case 'str': $cur['str'].=$v; break;
                case 'str0': $cur['str0'].=$v; break;
                case 'str1': $cur['str1'].=$v; break;
                case 'ctxt': $cur['ctxt'].=$v; break;
            }
            continue;
        }
        if (preg_match('/^#: (.*)$/', $ln, $m)) { if($cur) $cur['refs'][]=$m[1]; else { $cur=['ctxt'=>null,'msgid'=>'','plural'=>null,'str'=>'','str0'=>'','str1'=>'','refs'=>[$m[1]]]; } continue; }
        if (trim($ln) === '') { if ($cur && $cur['msgid'] !== '') $flush(); }
    }
    if ($cur && $cur['msgid'] !== '') $flush();
    return $entries;
}

function po_unescape(string $s): string {
    return strtr($s, ['\\n'=>"\n", '\\t'=>"\t", '\\"'=>'"', '\\\\'=>'\\']);
}

function po_escape(string $s): string {
    $s = strtr($s, ['\\'=>'\\\\', '"'=>'\\"', "\t"=>'\\t']);
    $s = str_replace("\n", "\\n\"\n\"", $s);
    return '"' . $s . '"';
}

/** Extrait les placeholders printf d'une chaîne. */
function placeholders(string $s): array {
    preg_match_all('/%(\d+\$)?[sd]/', $s, $m);
    sort($m[0]);
    return $m[0];
}
