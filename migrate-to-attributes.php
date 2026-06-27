#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * AcidORM annotation → PHP attribute migrator
 *
 * Usage:
 *   php migrate-to-attributes.php <directory> [--dry-run] [--verbose]
 */

// ─── CLI ─────────────────────────────────────────────────────────────────────

$args    = array_slice($argv, 1);
$dryRun  = in_array('--dry-run', $args, true);
$verbose = in_array('--verbose', $args, true);
$dirs    = array_values(array_filter($args, fn(string $a): bool => !str_starts_with($a, '--')));

if (empty($dirs)) {
    fwrite(STDERR, "Usage: php migrate-to-attributes.php <directory> [--dry-run] [--verbose]\n");
    exit(1);
}

$dir = rtrim($dirs[0], '/');
if (!is_dir($dir)) {
    fwrite(STDERR, "Error: '$dir' is not a directory.\n");
    exit(1);
}

// ─── Annotation map ───────────────────────────────────────────────────────────
//
// key   = lowercase annotation name
// value = [AttributeClass, 'marker'|'value'|'classname'|'named-arg:KEY'|'complex']
//
const ANNOT_MAP = [
    // class-level
    'name'           => ['Name',           'value'],
    'plural'         => ['Plural',         'value'],
    'historybinding' => ['HistoryBinding', 'named-arg:key'],

    // property-level
    'label'          => ['Label',          'value'],
    'dontmap'        => ['DontMap',        'marker'],
    'historydontmap' => ['HistoryDontMap', 'marker'],
    'onetoone'       => ['OneToOne',       'complex'],
    'onetomany'      => ['OneToMany',      'complex'],
    'manytomany'     => ['ManyToMany',     'complex'],
    'enum'           => ['EnumAttr',       'classname'],
    'formatter'      => ['Formatter',      'classname'],
];

const ATTR_NAMESPACE = 'AcidORM\\Attributes';

// ─── Conversion helpers ───────────────────────────────────────────────────────

function escStr(string $s): string
{
    return str_replace(["\\", "'"], ['\\\\', "\\'"], $s);
}

/**
 * Parse key=value, key2=value2, ... into PHP named arguments.
 * Booleans and numbers stay unquoted; everything else gets single-quoted.
 */
function parseComplexArgs(string $inner): string
{
    $args = [];
    foreach (preg_split('/\s*,\s*/', trim($inner)) as $pair) {
        $pair = trim($pair);
        if (!preg_match('/^(\w+)\s*=\s*(.+)$/', $pair, $m)) continue;
        $k = $m[1];
        $v = trim($m[2]);
        if (in_array(strtolower($v), ['true', 'false', 'null'], true)) {
            $args[] = "$k: " . strtolower($v);
        } elseif (is_numeric($v)) {
            $args[] = "$k: $v";
        } else {
            $args[] = "$k: '" . escStr($v) . "'";
        }
    }
    return implode(', ', $args);
}

/**
 * Convert one annotation to a PHP attribute string, or return null if unknown.
 * $usedClasses accumulates short class names that were used.
 */
function convertAnnotation(string $annotName, string $rest, array &$usedClasses): ?string
{
    $key = strtolower($annotName);
    if (!array_key_exists($key, ANNOT_MAP)) {
        return null;
    }

    [$attrClass, $type] = ANNOT_MAP[$key];
    $usedClasses[$attrClass] = true;
    $rest = trim($rest);

    if ($type === 'marker') {
        return "#[$attrClass]";
    }

    if ($type === 'value') {
        return "#[$attrClass('" . escStr($rest) . "')]";
    }

    if ($type === 'classname') {
        $cls = trim($rest, '() ');
        return "#[$attrClass(className: '" . escStr($cls) . "')]";
    }

    if (str_starts_with($type, 'named-arg:')) {
        $namedKey = substr($type, strlen('named-arg:'));
        return "#[$attrClass($namedKey: '" . escStr($rest) . "')]";
    }

    if ($type === 'complex') {
        if (!preg_match('/^\(\s*(.*?)\s*\)$/s', $rest, $m)) {
            return "#[$attrClass]";
        }
        $args = parseComplexArgs($m[1]);
        return "#[$attrClass($args)]";
    }

    return null;
}

// ─── Docblock processor ───────────────────────────────────────────────────────

/**
 * Process a raw docblock string. Returns:
 *   [$attrLines, $rebuiltDoc|null]
 *
 * $attrLines     = array of '#[Foo(...)]' strings (no indent)
 * $rebuiltDoc    = cleaned docblock without ORM annotations, or null if nothing left
 */
function processDocblock(string $docblock, array &$usedClasses): array
{
    // Step 1: remove the opening /**  (with any leading whitespace)
    $inner = preg_replace('/^[ \t]*\/\*\*/m', '', $docblock);
    // Step 2: remove the closing */  (with any trailing whitespace)
    $inner = preg_replace('/\*\/[ \t]*$/m', '', $inner);
    // Step 3: remove leading whitespace + the lone * from each interior line
    //         e.g. "    * @label Foo"  →  "@label Foo"
    $inner = preg_replace('/^[ \t]*\*[ \t]?/m', '', $inner);

    $attrLines    = [];
    $keepDocLines = [];

    foreach (preg_split('/\r?\n/', $inner) as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (!preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)(.*)$/', $line, $m)) {
            $keepDocLines[] = $line;
            continue;
        }

        $attr = convertAnnotation($m[1], $m[2], $usedClasses);
        if ($attr !== null) {
            $attrLines[] = $attr;
        } else {
            $keepDocLines[] = $line; // unknown annotation, keep in docblock
        }
    }

    $rebuiltDoc = null;
    if (!empty($keepDocLines)) {
        $parts = ['/**'];
        foreach ($keepDocLines as $l) {
            $parts[] = " * $l";
        }
        $parts[]    = ' */';
        $rebuiltDoc = implode("\n", $parts);
    }

    return [$attrLines, $rebuiltDoc];
}

// ─── File converter ───────────────────────────────────────────────────────────

function convertFile(string $content): string
{
    $usedClasses = [];

    // Match docblocks: capture leading indent + the whole /** ... */
    // The 's' flag makes . match newlines.
    $converted = preg_replace_callback(
        '/^([ \t]*)\/\*\*(.*?)\*\//ms',
        function (array $m) use (&$usedClasses): string {
            $indent   = $m[1];
            $docblock = $m[0];

            [$attrLines, $rebuiltDoc] = processDocblock($docblock, $usedClasses);

            if (empty($attrLines)) {
                return $docblock; // nothing to convert
            }

            $parts = [];

            if ($rebuiltDoc !== null) {
                // Indent the rebuilt docblock
                $indentedDoc = $indent . str_replace("\n", "\n$indent", $rebuiltDoc);
                $parts[] = $indentedDoc;
            }

            foreach ($attrLines as $attr) {
                $parts[] = $indent . $attr;
            }

            return implode("\n", $parts);
        },
        $content
    );

    if (empty($usedClasses)) {
        return $converted;
    }

    return addUseStatements($converted, array_keys($usedClasses));
}

// ─── Use-statement injection ──────────────────────────────────────────────────

function addUseStatements(string $content, array $shortClasses): string
{
    $ns = ATTR_NAMESPACE;

    // Which classes still need a use statement?
    $toAdd = [];
    foreach ($shortClasses as $cls) {
        $fqn = "$ns\\$cls";
        // Already present as `use Fqn;`
        if (preg_match('/^use\s+' . preg_quote($fqn, '/') . '\s*;/m', $content)) continue;
        // Already present as `use AcidORM\Attributes;` (whole namespace)
        if (preg_match('/^use\s+' . preg_quote($ns, '/') . '\s*;/m', $content)) continue;
        $toAdd[] = "use $fqn;";
    }

    if (empty($toAdd)) return $content;

    sort($toAdd);
    $block = implode("\n", $toAdd) . "\n";

    // Strategy 1: append after the LAST `use ...;` line (before first class/trait/interface)
    $classPos = findFirstClassPos($content);

    if (preg_match_all('/^use\s+[^;]+;/m', $content, $useMatches, PREG_OFFSET_CAPTURE)) {
        $lastUseOffset = 0;
        $lastUseLen    = 0;
        foreach ($useMatches[0] as [$match, $offset]) {
            if ($offset < $classPos) {
                $lastUseOffset = $offset;
                $lastUseLen    = strlen($match);
            }
        }
        if ($lastUseOffset > 0) {
            $insertAt = $lastUseOffset + $lastUseLen;
            // Skip to end of that line
            $nl = strpos($content, "\n", $insertAt);
            $insertAt = ($nl !== false) ? $nl + 1 : $insertAt;
            return substr($content, 0, $insertAt) . $block . substr($content, $insertAt);
        }
    }

    // Strategy 2: after namespace declaration
    if (preg_match('/^namespace\s+[^;]+;/m', $content, $nsMatch, PREG_OFFSET_CAPTURE)) {
        $insertAt = $nsMatch[0][1] + strlen($nsMatch[0][0]);
        $nl = strpos($content, "\n", $insertAt);
        $insertAt = ($nl !== false) ? $nl + 1 : $insertAt;
        return substr($content, 0, $insertAt) . "\n" . $block . substr($content, $insertAt);
    }

    // Strategy 3: after <?php / declare
    if (preg_match('/^<\?php[^\n]*\n(?:declare[^\n]+\n)?/m', $content, $phpMatch, PREG_OFFSET_CAPTURE)) {
        $insertAt = $phpMatch[0][1] + strlen($phpMatch[0][0]);
        return substr($content, 0, $insertAt) . "\n" . $block . substr($content, $insertAt);
    }

    return $content . "\n" . $block;
}

function findFirstClassPos(string $content): int
{
    if (preg_match('/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+/m', $content, $m, PREG_OFFSET_CAPTURE)) {
        return $m[0][1];
    }
    return PHP_INT_MAX;
}

// ─── Runner ───────────────────────────────────────────────────────────────────

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$total   = 0;
$changed = 0;
$errors  = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $total++;
    $path = $file->getPathname();

    $original = file_get_contents($path);
    if ($original === false) {
        fwrite(STDERR, "Cannot read: $path\n");
        $errors++;
        continue;
    }

    try {
        $result = convertFile($original);
    } catch (\Throwable $e) {
        fwrite(STDERR, "Error processing $path: " . $e->getMessage() . "\n");
        $errors++;
        continue;
    }

    if ($result === $original) {
        if ($verbose) echo "  (unchanged) $path\n";
        continue;
    }

    $changed++;
    $prefix = $dryRun ? '[dry-run] ' : '';
    echo "{$prefix}Updated: $path\n";

    if ($verbose) {
        showDiff($original, $result);
    }

    if (!$dryRun) {
        if (file_put_contents($path, $result) === false) {
            fwrite(STDERR, "Cannot write: $path\n");
            $errors++;
        }
    }
}

printf("\nScanned %d PHP files – %d updated%s.\n",
    $total, $changed, $errors ? ", $errors error(s)" : '');

if ($dryRun) echo "(dry-run mode, no files modified)\n";
exit($errors ? 1 : 0);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function showDiff(string $original, string $result): void
{
    $origLines   = explode("\n", $original);
    $resultLines = explode("\n", $result);
    $maxOrig     = count($origLines);
    $maxResult   = count($resultLines);

    for ($i = 0; $i < max($maxOrig, $maxResult); $i++) {
        $o = $origLines[$i]   ?? null;
        $r = $resultLines[$i] ?? null;
        if ($o !== $r) {
            if ($o !== null) echo "  \033[31m- $o\033[0m\n";
            if ($r !== null) echo "  \033[32m+ $r\033[0m\n";
        }
    }
}
