<?php
declare(strict_types=1);

// ---------------------------------------------------------
// Minimal QR code encoder (ISO/IEC 18004).
// Byte mode, error-correction level M, versions 1-10
// (auto-selected by data length). Renders to crisp SVG.
// No external dependencies.
// ---------------------------------------------------------

// EC level M characteristics per version:
// version => [ecCodewordsPerBlock, [[numBlocks, dataCodewordsPerBlock], ...]]
function qrEcTable() {
    return [
        1  => [10, [[1, 16]]],
        2  => [16, [[1, 28]]],
        3  => [26, [[1, 44]]],
        4  => [18, [[2, 32]]],
        5  => [24, [[2, 43]]],
        6  => [16, [[4, 27]]],
        7  => [18, [[4, 31]]],
        8  => [22, [[2, 38], [2, 39]]],
        9  => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];
}

function qrAlignPositions() {
    return [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];
}

// GF(256) exp/log tables (primitive polynomial 0x11d).
function qrGf() {
    static $exp = null, $log = null;
    if ($exp !== null) return [$exp, $log];
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11d;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    return [$exp, $log];
}

function qrRsGenerator($degree) {
    [$exp, $log] = qrGf();
    $poly = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($poly) + 1, 0);
        foreach ($poly as $j => $coef) {
            $next[$j] ^= $coef;
            $next[$j + 1] ^= ($coef ? $exp[($log[$coef] + $i) % 255] : 0);
        }
        $poly = $next;
    }
    return $poly;
}

function qrRsEc($data, $ecLen) {
    [$exp, $log] = qrGf();
    $gen = qrRsGenerator($ecLen);
    $res = array_fill(0, $ecLen, 0);
    foreach ($data as $d) {
        $factor = $d ^ $res[0];
        array_shift($res);
        $res[] = 0;
        if ($factor !== 0) {
            foreach ($gen as $i => $g) {
                if ($i === 0) continue;
                $res[$i - 1] ^= $exp[($log[$factor] + $log[$g]) % 255];
            }
        }
    }
    return $res;
}

// Pick the smallest version (1-10) that fits the byte payload at level M.
function qrChooseVersion($len) {
    $table = qrEcTable();
    foreach ($table as $v => [$ecPerBlock, $blocks]) {
        $dataCw = 0;
        foreach ($blocks as [$n, $cw]) $dataCw += $n * $cw;
        $cci = $v <= 9 ? 8 : 16;
        $bits = 4 + $cci + 8 * $len;
        if ($bits <= $dataCw * 8) return $v;
    }
    return 0; // too long
}

function qrEncodeData($data, $version) {
    $table = qrEcTable();
    [$ecPerBlock, $blocks] = $table[$version];
    $dataCw = 0;
    foreach ($blocks as [$n, $cw]) $dataCw += $n * $cw;

    // Bit stream: mode (0100) + char count + bytes
    $bits = '';
    $bits .= '0100';
    $cci = $version <= 9 ? 8 : 16;
    $bits .= str_pad(decbin(strlen($data)), $cci, '0', STR_PAD_LEFT);
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }
    // Terminator (up to 4 bits)
    $capacity = $dataCw * 8;
    $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));
    // Pad to byte boundary
    if (strlen($bits) % 8 !== 0) {
        $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
    }
    // Convert to codewords
    $codewords = [];
    for ($i = 0, $n = strlen($bits); $i < $n; $i += 8) {
        $codewords[] = bindec(substr($bits, $i, 8));
    }
    // Pad codewords with 0xEC / 0x11
    $pad = [0xEC, 0x11];
    $pi = 0;
    while (count($codewords) < $dataCw) {
        $codewords[] = $pad[$pi % 2];
        $pi++;
    }

    // Split into blocks, compute EC, then interleave
    $dataBlocks = [];
    $ecBlocks = [];
    $offset = 0;
    foreach ($blocks as [$numBlocks, $cwPerBlock]) {
        for ($b = 0; $b < $numBlocks; $b++) {
            $blk = array_slice($codewords, $offset, $cwPerBlock);
            $offset += $cwPerBlock;
            $dataBlocks[] = $blk;
            $ecBlocks[] = qrRsEc($blk, $ecPerBlock);
        }
    }

    $result = [];
    $maxData = 0;
    foreach ($dataBlocks as $blk) $maxData = max($maxData, count($blk));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($dataBlocks as $blk) {
            if (isset($blk[$i])) $result[] = $blk[$i];
        }
    }
    for ($i = 0; $i < $ecPerBlock; $i++) {
        foreach ($ecBlocks as $blk) {
            if (isset($blk[$i])) $result[] = $blk[$i];
        }
    }
    return $result;
}

// Build the module matrix. Returns [matrix(bool[][]), size].
function qrBuildMatrix($data) {
    $version = qrChooseVersion(strlen($data));
    if ($version === 0) return [null, 0];

    $codewords = qrEncodeData($data, $version);
    $size = 17 + 4 * $version;

    $m = [];        // module value (0/1)
    $fn = [];       // function-module flag
    for ($r = 0; $r < $size; $r++) {
        $m[$r] = array_fill(0, $size, 0);
        $fn[$r] = array_fill(0, $size, false);
    }

    $setF = function ($r, $c, $v) use (&$m, &$fn) {
        $m[$r][$c] = $v ? 1 : 0;
        $fn[$r][$c] = true;
    };

    // Finder pattern + separator
    $placeFinder = function ($row, $col) use ($setF, $size) {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r; $cc = $col + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
                $isDark = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                    || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6))
                    || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                $setF($rr, $cc, $isDark ? 1 : 0);
            }
        }
    };
    $placeFinder(0, 0);
    $placeFinder(0, $size - 7);
    $placeFinder($size - 7, 0);

    // Timing patterns
    for ($i = 8; $i < $size - 8; $i++) {
        if (!$fn[6][$i]) $setF(6, $i, ($i % 2 === 0) ? 1 : 0);
        if (!$fn[$i][6]) $setF($i, 6, ($i % 2 === 0) ? 1 : 0);
    }

    // Alignment patterns
    $positions = qrAlignPositions()[$version];
    foreach ($positions as $ar) {
        foreach ($positions as $ac) {
            // Skip if overlapping a finder pattern
            if (($ar <= 8 && $ac <= 8) || ($ar <= 8 && $ac >= $size - 9) || ($ar >= $size - 9 && $ac <= 8)) continue;
            for ($r = -2; $r <= 2; $r++) {
                for ($c = -2; $c <= 2; $c++) {
                    $isDark = (max(abs($r), abs($c)) !== 1);
                    $setF($ar + $r, $ac + $c, $isDark ? 1 : 0);
                }
            }
        }
    }

    // Dark module
    $setF($size - 8, 8, 1);

    // Reserve format info areas
    for ($i = 0; $i <= 8; $i++) {
        if (!$fn[8][$i]) { $fn[8][$i] = true; $m[8][$i] = 0; }
        if (!$fn[$i][8]) { $fn[$i][8] = true; $m[$i][8] = 0; }
    }
    for ($i = 0; $i < 8; $i++) {
        if (!$fn[8][$size - 1 - $i]) { $fn[8][$size - 1 - $i] = true; $m[8][$size - 1 - $i] = 0; }
        if (!$fn[$size - 1 - $i][8]) { $fn[$size - 1 - $i][8] = true; $m[$size - 1 - $i][8] = 0; }
    }

    // Reserve version info (v >= 7)
    if ($version >= 7) {
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $fn[$i][$size - 11 + $j] = true;
                $fn[$size - 11 + $j][$i] = true;
            }
        }
    }

    // Build data bit stream
    $bitstr = '';
    foreach ($codewords as $cw) $bitstr .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);

    // Place data with zigzag
    $bitIdx = 0;
    $len = strlen($bitstr);
    $up = true;
    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col = 5; // skip timing column
        for ($i = 0; $i < $size; $i++) {
            $row = $up ? ($size - 1 - $i) : $i;
            for ($k = 0; $k < 2; $k++) {
                $c = $col - $k;
                if ($fn[$row][$c]) continue;
                $bit = ($bitIdx < $len) ? (int)$bitstr[$bitIdx] : 0;
                $bitIdx++;
                $m[$row][$c] = $bit;
            }
        }
        $up = !$up;
    }

    // Choose best mask via penalty score (or a forced mask for testing).
    $best = null; $bestPenalty = PHP_INT_MAX; $bestMask = 0;
    $forced = isset($GLOBALS['QR_FORCE_MASK']) ? (int)$GLOBALS['QR_FORCE_MASK'] : -1;
    for ($mask = 0; $mask < 8; $mask++) {
        if ($forced >= 0 && $mask !== $forced) continue;
        $cand = qrApplyMask($m, $fn, $size, $mask);
        qrPlaceFormat($cand, $fn, $size, $mask);
        if ($version >= 7) qrPlaceVersion($cand, $size, $version);
        $p = qrPenalty($cand, $size);
        if ($p < $bestPenalty) { $bestPenalty = $p; $best = $cand; $bestMask = $mask; }
    }

    return [$best, $size];
}

function qrMaskBit($mask, $r, $c) {
    switch ($mask) {
        case 0: return ($r + $c) % 2 === 0;
        case 1: return $r % 2 === 0;
        case 2: return $c % 3 === 0;
        case 3: return ($r + $c) % 3 === 0;
        case 4: return ((int)($r / 2) + (int)($c / 3)) % 2 === 0;
        case 5: return (($r * $c) % 2) + (($r * $c) % 3) === 0;
        case 6: return ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0;
        case 7: return ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0;
    }
    return false;
}

function qrApplyMask($m, $fn, $size, $mask) {
    $out = $m;
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            if ($fn[$r][$c]) continue;
            if (qrMaskBit($mask, $r, $c)) $out[$r][$c] ^= 1;
        }
    }
    return $out;
}

function qrPlaceFormat(&$m, $fn, $size, $mask) {
    // EC level M = 0b00. Format data = (level << 3) | mask
    $fmt = (0b00 << 3) | $mask;
    $bch = $fmt << 10;
    for ($i = 14; $i >= 10; $i--) {
        if (($bch >> $i) & 1) $bch ^= 0x537 << ($i - 10);
    }
    $bits = (($fmt << 10) | $bch) ^ 0x5412;
    $b = function ($i) use ($bits) { return ($bits >> $i) & 1; };

    // Copy 1 (around top-left finder)
    $hcols = [0, 1, 2, 3, 4, 5, 7, 8];
    $hbits = [14, 13, 12, 11, 10, 9, 8, 7];
    foreach ($hcols as $k => $c) $m[8][$c] = $b($hbits[$k]);
    $vrows = [0, 1, 2, 3, 4, 5, 7];
    foreach ($vrows as $k => $r) $m[$r][8] = $b($k); // bits 0..6

    // Copy 2 (around bottom-left & top-right finders)
    for ($i = 0; $i <= 6; $i++) $m[$size - 1 - $i][8] = $b(14 - $i); // bits 14..8
    for ($j = 0; $j <= 7; $j++) $m[8][$size - 8 + $j] = $b(7 - $j);  // bits 7..0

    // Dark module (set last so it is never overwritten)
    $m[$size - 8][8] = 1;
}

function qrPlaceVersion(&$m, $size, $version) {
    $bch = $version << 12;
    for ($i = 17; $i >= 12; $i--) {
        if (($bch >> $i) & 1) $bch ^= 0x1f25 << ($i - 12);
    }
    $bits = ($version << 12) | $bch;
    for ($i = 0; $i < 18; $i++) {
        $bit = ($bits >> $i) & 1;
        $r = (int)($i / 3);
        $c = $i % 3;
        $m[$r][$size - 11 + $c] = $bit;
        $m[$size - 11 + $c][$r] = $bit;
    }
}

function qrPenalty($m, $size) {
    $penalty = 0;
    // Rule 1: runs of 5+ same color in row/col
    for ($r = 0; $r < $size; $r++) {
        $run = 1;
        for ($c = 1; $c < $size; $c++) {
            if ($m[$r][$c] === $m[$r][$c - 1]) { $run++; if ($run === 5) $penalty += 3; elseif ($run > 5) $penalty++; }
            else $run = 1;
        }
    }
    for ($c = 0; $c < $size; $c++) {
        $run = 1;
        for ($r = 1; $r < $size; $r++) {
            if ($m[$r][$c] === $m[$r - 1][$c]) { $run++; if ($run === 5) $penalty += 3; elseif ($run > 5) $penalty++; }
            else $run = 1;
        }
    }
    // Rule 2: 2x2 blocks
    for ($r = 0; $r < $size - 1; $r++) {
        for ($c = 0; $c < $size - 1; $c++) {
            $v = $m[$r][$c];
            if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) $penalty += 3;
        }
    }
    // Rule 3: finder-like patterns
    $pat1 = [1,0,1,1,1,0,1,0,0,0,0];
    $pat2 = [0,0,0,0,1,0,1,1,1,0,1];
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size - 10; $c++) {
            $match1 = true; $match2 = true;
            for ($k = 0; $k < 11; $k++) {
                if ($m[$r][$c + $k] !== $pat1[$k]) $match1 = false;
                if ($m[$r][$c + $k] !== $pat2[$k]) $match2 = false;
            }
            if ($match1 || $match2) $penalty += 40;
        }
    }
    for ($c = 0; $c < $size; $c++) {
        for ($r = 0; $r < $size - 10; $r++) {
            $match1 = true; $match2 = true;
            for ($k = 0; $k < 11; $k++) {
                if ($m[$r + $k][$c] !== $pat1[$k]) $match1 = false;
                if ($m[$r + $k][$c] !== $pat2[$k]) $match2 = false;
            }
            if ($match1 || $match2) $penalty += 40;
        }
    }
    // Rule 4: dark module proportion
    $dark = 0;
    for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) $dark += $m[$r][$c];
    $total = $size * $size;
    $percent = ($dark * 100) / $total;
    $prev = (int)(abs($percent - 50) / 5);
    $penalty += $prev * 10;
    return $penalty;
}

// Render the QR for $data as an SVG string (square, $quiet-module border).
function qrSvg($data, $scale = 8, $quiet = 4) {
    [$m, $size] = qrBuildMatrix($data);
    if ($m === null) return '';
    $dim = ($size + 2 * $quiet) * $scale;

    $rects = '';
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            if ($m[$r][$c]) {
                $x = ($c + $quiet) * $scale;
                $y = ($r + $quiet) * $scale;
                $rects .= '<rect x="' . $x . '" y="' . $y . '" width="' . $scale . '" height="' . $scale . '"/>';
            }
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" '
        . 'viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges">'
        . '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/>'
        . '<g fill="#0b0b0c">' . $rects . '</g></svg>';
}

// Debug helper: matrix as rows of 0/1 (used by the verification harness).
function qrMatrixString($data) {
    [$m, $size] = qrBuildMatrix($data);
    if ($m === null) return '';
    $out = [];
    for ($r = 0; $r < $size; $r++) $out[] = implode('', $m[$r]);
    return implode("\n", $out);
}
