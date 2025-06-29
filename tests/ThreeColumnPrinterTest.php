<?php

namespace Pingiun\FixConflicts\Tests;

use PHPUnit\Framework\TestCase;
use Pingiun\FixConflicts\PackageDiff;
use Pingiun\FixConflicts\ThreeColumnPrinter;
use Symfony\Component\Console\Output\BufferedOutput;

class ThreeColumnPrinterTest extends TestCase
{
    /**
     * Test that lines don't exceed the terminal width for terminals of 80 characters or more
     *
     * @dataProvider terminalWidthProvider
     */
    public function testLinesDontExceedTerminalWidth(int $terminalWidth): void
    {
        $output = new BufferedOutput();
        $printer = new ThreeColumnPrinter($terminalWidth, $output);
        
        // Create a package diff with long names and versions to test truncation
        $diff = new PackageDiff(
            'very-long-package-name-that-should-be-truncated',
            'v1.0.0-very-long-version-that-should-be-truncated',
            'v2.0.0-very-long-version-that-should-be-truncated',
            'v3.0.0-very-long-version-that-should-be-truncated'
        );
        
        // Get the formatted lines
        $lines = $printer->formatDiff($diff);
        
        // Check that each line doesn't exceed the terminal width
        foreach ($lines as $line) {
            // Remove ANSI color codes for accurate length calculation
            $strippedLine = preg_replace('/\<[^>]+\>|\<\/[^>]+\>/', '', $line);
            $this->assertLessThanOrEqual(
                $terminalWidth, 
                mb_strlen($strippedLine), 
                "Line exceeds terminal width of $terminalWidth: $strippedLine"
            );
        }
    }
    
    /**
     * Test with different package diff scenarios
     */
    public function testDifferentPackageDiffScenarios(): void
    {
        $output = new BufferedOutput();
        $printer = new ThreeColumnPrinter(80, $output);
        
        // Test with null versions
        $diff1 = new PackageDiff('package1', null, 'v1.0.0', 'v2.0.0');
        $lines1 = $printer->formatDiff($diff1);
        $this->assertNotEmpty($lines1);
        
        // Test with all versions present
        $diff2 = new PackageDiff('package2', 'v1.0.0', 'v2.0.0', 'v3.0.0');
        $lines2 = $printer->formatDiff($diff2);
        $this->assertNotEmpty($lines2);
        
        // Test with null our version
        $diff3 = new PackageDiff('package3', 'v1.0.0', null, 'v3.0.0');
        $lines3 = $printer->formatDiff($diff3);
        $this->assertNotEmpty($lines3);
        
        // Test with null their version
        $diff4 = new PackageDiff('package4', 'v1.0.0', 'v2.0.0', null);
        $lines4 = $printer->formatDiff($diff4);
        $this->assertNotEmpty($lines4);
    }
    
    /**
     * Data provider for terminal widths
     */
    public static function terminalWidthProvider(): array
    {
        return [
            'Minimum terminal width (80)' => [80],
            'Medium terminal width (100)' => [100],
            'Large terminal width (120)' => [120],
            'Extra large terminal width (150)' => [150],
        ];
    }
}
