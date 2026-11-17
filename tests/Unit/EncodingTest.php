<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Mencegah teks rusak seperti "â†’" (seharusnya "→") atau "Â·" (seharusnya "·") lolos ke tampilan. */
class EncodingTest extends TestCase
{
    public function test_no_mojibake_in_source_files()
    {
        $root = dirname(__DIR__, 2);
        $bad = [];
        foreach (['resources/js', 'app', 'routes'] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}"));
            foreach ($it as $file) {
                if (! $file->isFile() || ! preg_match('/\.(tsx?|php)$/', $file->getFilename())) {
                    continue;
                }
                $text = file_get_contents($file->getPathname());
                if (preg_match('/â€|â†|Â·|Â |Ã[\x{80}-\x{BF}]/u', $text) || str_starts_with($text, "\xEF\xBB\xBF")) {
                    $bad[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }
        $this->assertSame([], $bad, 'File berisi teks rusak / BOM: '.implode(', ', $bad));
    }
}
