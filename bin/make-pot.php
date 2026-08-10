<?php
/**
 * Extracteur .pot maison pour le thème schilo (aucun WP-CLI/xgettext local).
 * Basé sur le tokenizer PHP → gère apostrophes échappées, _x (contexte),
 * _n (pluriel). Ne retient que le domaine 'schilo'.
 *
 * Usage : php make_pot.php <theme_dir> <out_dir>
 */

$themeDir = rtrim($argv[1] ?? '.', '/\\');
$outDir   = rtrim($argv[2] ?? ($themeDir . '/languages'), '/\\');
$GLOBALS['themeAbs'] = str_replace('\\', '/', realpath($themeDir) ?: $themeDir);

// Locales à amorcer (msgstr vides, prêtes à traduire).
$locales = [
    'en_US' => 'English (United States)',
    'de_DE' => 'Deutsch',
    'es_ES' => 'Español',
    'pt_PT' => 'Português',
    'it_IT' => 'Italiano',
];

$funcs = [
    '__'          => ['text' => 0, 'domain' => 1],
    '_e'          => ['text' => 0, 'domain' => 1],
    'esc_html__'  => ['text' => 0, 'domain' => 1],
    'esc_html_e'  => ['text' => 0, 'domain' => 1],
    'esc_attr__'  => ['text' => 0, 'domain' => 1],
    'esc_attr_e'  => ['text' => 0, 'domain' => 1],
    'esc_xml__'   => ['text' => 0, 'domain' => 1],
    '_x'          => ['text' => 0, 'context' => 1, 'domain' => 2],
    '_ex'         => ['text' => 0, 'context' => 1, 'domain' => 2],
    'esc_html_x'  => ['text' => 0, 'context' => 1, 'domain' => 2],
    'esc_attr_x'  => ['text' => 0, 'context' => 1, 'domain' => 2],
    '_n'          => ['text' => 0, 'plural' => 1, 'domain' => 3],
    '_nx'         => ['text' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4],
];

// ── Collecte des fichiers PHP (hors vendor/.git/node_modules/languages) ──
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS)
);
$files = [];
foreach ($rii as $f) {
    $path = str_replace('\\', '/', $f->getPathname());
    if (!preg_match('/\.php$/', $path)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|languages)/#', $path)) continue;
    $files[] = $path;
}
sort($files);

// ── Décodage d'un littéral de chaîne PHP en valeur brute ──
function decode_php_string(string $lit): ?string {
    if ($lit === '') return null;
    $q = $lit[0];
    $inner = substr($lit, 1, -1);
    if ($q === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], $inner);
    }
    if ($q === '"') {
        // Interprète les échappements double-quote courants.
        return preg_replace_callback('/\\\\(["\\\\nrtvf$]|[0-7]{1,3}|x[0-9A-Fa-f]{1,2})/', function ($m) {
            switch ($m[1]) {
                case '"':  return '"';
                case '\\': return '\\';
                case 'n':  return "\n";
                case 'r':  return "\r";
                case 't':  return "\t";
                case 'v':  return "\v";
                case 'f':  return "\f";
                case '$':  return '$';
                default:   return $m[0];
            }
        }, $inner);
    }
    return null; // heredoc/nowdoc non gérés (aucun dans les appels i18n du thème)
}

// ── Encodage PO d'une chaîne (multi-ligne si \n) ──
function po_encode(string $s): string {
    $esc = str_replace(['\\', '"', "\t"], ['\\\\', '\\"', '\\t'], $s);
    $esc = str_replace("\n", "\\n\"\n\"", $esc); // coupe sur les newlines
    return '"' . $esc . '"';
}

// ── Extraction ──
$entries = []; // clé "ctx\x04msgid" => ['msgid','plural','ctxt','refs'=>[]]

foreach ($files as $file) {
    $src = file_get_contents($file);
    $tokens = token_get_all($src);
    $rel = ltrim(str_replace(str_replace('\\', '/', $GLOBALS['themeAbs']), '', str_replace('\\', '/', $file)), '/');

    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING || !isset($funcs[$t[1]])) continue;
        $fname = $t[1];
        $line  = $t[2];

        // Pas un appel de méthode (->foo / ::foo)
        $j = $i - 1;
        while ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j--;
        if ($j >= 0 && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) continue;
        if ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION) continue;

        // Doit être suivi de '('
        $k = $i + 1;
        while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;
        if ($k >= $n || $tokens[$k] !== '(') continue;

        // Parse des arguments de premier niveau
        $depth = 0; $args = []; $cur = null; $curIsSingleString = true; $curStr = null; $started = false;
        for ($m = $k; $m < $n; $m++) {
            $tk = $tokens[$m];
            if ($tk === '(') { $depth++; if ($depth === 1) { $started = true; continue; } }
            elseif ($tk === ')') { $depth--; if ($depth === 0) { // fin
                    $args[] = $curIsSingleString ? ['str', $curStr] : ['expr', null];
                    break;
                } }
            if (!$started || $depth < 1) continue;

            if ($depth === 1 && $tk === ',') {
                $args[] = $curIsSingleString ? ['str', $curStr] : ['expr', null];
                $curIsSingleString = true; $curStr = null;
                continue;
            }
            if ($depth === 1 && is_array($tk) && $tk[0] === T_WHITESPACE) continue;
            // contenu de l'argument courant
            if (is_array($tk) && $tk[0] === T_CONSTANT_ENCAPSED_STRING && $curStr === null) {
                $curStr = decode_php_string($tk[1]);
                if ($curStr === null) $curIsSingleString = false;
            } elseif (is_array($tk) && $tk[0] === T_WHITESPACE) {
                // espaces ok
            } else {
                // tout autre token dans l'arg (concat, variable, nombre…) => pas une string simple
                if (!(is_array($tk) && $tk[0] === T_CONSTANT_ENCAPSED_STRING)) {
                    $curIsSingleString = false;
                } else {
                    $curIsSingleString = false; // 2e string = concat
                }
            }
        }

        $sig = $funcs[$fname];
        $domIdx = $sig['domain'];
        if (!isset($args[$domIdx]) || $args[$domIdx][0] !== 'str' || $args[$domIdx][1] !== 'schilo') continue;

        $textIdx = $sig['text'];
        if (!isset($args[$textIdx]) || $args[$textIdx][0] !== 'str' || $args[$textIdx][1] === null) continue;
        $msgid = $args[$textIdx][1];

        $plural = null;
        if (isset($sig['plural']) && isset($args[$sig['plural']]) && $args[$sig['plural']][0] === 'str') {
            $plural = $args[$sig['plural']][1];
        }
        $ctxt = null;
        if (isset($sig['context']) && isset($args[$sig['context']]) && $args[$sig['context']][0] === 'str') {
            $ctxt = $args[$sig['context']][1];
        }

        $key = ($ctxt ?? '') . "\x04" . $msgid;
        if (!isset($entries[$key])) {
            $entries[$key] = ['msgid' => $msgid, 'plural' => $plural, 'ctxt' => $ctxt, 'refs' => []];
        }
        if ($plural && !$entries[$key]['plural']) $entries[$key]['plural'] = $plural;
        $entries[$key]['refs'][] = $rel . ':' . $line;
    }
}

// Tri par 1re référence pour un .pot stable
uasort($entries, function ($a, $b) {
    return strcmp($a['refs'][0] ?? '', $b['refs'][0] ?? '');
});

// ── En-tête .pot ──
$date = date('Y-m-d H:iO');
$header = <<<POT
# Traductions du thème schilo.
# Copyright (C) schilo.org
# This file is distributed under the same license as the schilo theme.
msgid ""
msgstr ""
"Project-Id-Version: schilo 1.9.16\\n"
"Report-Msgid-Bugs-To: \\n"
"POT-Creation-Date: {$date}\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: \\n"
"Language-Team: \\n"
"Language: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Generator: schilo make_pot.php\\n"
"X-Domain: schilo\\n"

POT;

$out = $header;
foreach ($entries as $e) {
    $refs = array_values(array_unique($e['refs']));
    // Une ligne #: par ~5 refs pour rester lisible
    foreach (array_chunk($refs, 6) as $chunk) {
        $out .= '#: ' . implode(' ', $chunk) . "\n";
    }
    if ($e['ctxt'] !== null) {
        $out .= 'msgctxt ' . po_encode($e['ctxt']) . "\n";
    }
    $out .= 'msgid ' . po_encode($e['msgid']) . "\n";
    if ($e['plural'] !== null) {
        $out .= 'msgid_plural ' . po_encode($e['plural']) . "\n";
        $out .= "msgstr[0] \"\"\n";
        $out .= "msgstr[1] \"\"\n\n";
    } else {
        $out .= "msgstr \"\"\n\n";
    }
}

if (!is_dir($outDir)) mkdir($outDir, 0755, true);
file_put_contents($outDir . '/schilo.pot', $out);

echo "Fichiers scannés : " . count($files) . "\n";
echo "Chaînes uniques  : " . count($entries) . "\n";
echo "Écrit : " . $outDir . "/schilo.pot\n";

// ── Génération des .po d'amorçage (une par locale, msgstr vides) ──
$revDate = date('Y-m-d H:iO');
foreach ($locales as $loc => $team) {
    $po = $out;
    // Renseigne l'en-tête spécifique à la langue.
    $po = str_replace('"Language: \\n"', '"Language: ' . $loc . '\\n"', $po);
    $po = str_replace('"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"', '"PO-Revision-Date: ' . $revDate . '\\n"', $po);
    $po = str_replace('"Language-Team: \\n"', '"Language-Team: ' . $team . '\\n"', $po);
    // pt_BR utiliserait plural=(n > 1) ; pt_PT et les autres = (n != 1).
    file_put_contents($outDir . '/schilo-' . $loc . '.po', $po);
    echo "Écrit : {$outDir}/schilo-{$loc}.po\n";
}
