<?php

declare(strict_types=1);

namespace Pingiun\FixConflicts\Tests;

use PHPUnit\Framework\TestCase;
use Pingiun\FixConflicts\FixConflictsCommand;
use Symfony\Component\Console\Output\BufferedOutput;

class TreePrinterTest extends TestCase
{
    public function testPrintsTree()
    {
        $output = new BufferedOutput();
        $node = [
            'name' => 'bigbridge/testing',
            'base' => null,
            'ours' => 'v1.0.0',
            'theirs' => 'v2.0.0',
            'result' => 'v2.0.0',
        ];
        $diffs = [];
        $diffs['bigbridge/testing'] = $node;
        $parents = [];
        $parents['bigbridge/testing'] = [
            'direct-requires' => true,
            'direct-dev-requires' => false,
        ];
        FixConflictsCommand::reportDiff($output, 'bigbridge/testing', $diffs, $parents);
        $this->assertEquals("→ bigbridge/testing\n  └─ (direct dependency)\n", $output->fetch());
    }

    public function testPrintsLargerTree()
    {
        $output = new BufferedOutput();
        $node = [
            'name' => 'bigbridge/testing',
            'base' => null,
            'ours' => 'v1.0.0',
            'theirs' => 'v2.0.0',
            'result' => 'v2.0.0',
        ];
        $diffs = [];
        $diffs['bigbridge/testing'] = $node;
        $parents = [];
        $parents['bigbridge/testing'] = [
            'direct-requires' => true,
            'direct-dev-requires' => false,
        ];
        FixConflictsCommand::reportDiffs($output, [], $diffs, [], []);;
    }
}