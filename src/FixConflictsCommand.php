<?php

namespace Pingiun\FixConflicts;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Process;

final class FixConflictsCommand extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this->setName('fix-conflicts');
        $this->setDescription('Difficulty merging a branch that also changed composer.json? Run composer fix-conflicts. (No command line options needed, this is an interactive command)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $terminal = new Terminal;
        $terminalWidth = $terminal->getWidth();
        $output->writeln('Fixing Conflicts');

        self::chdirToRepo();

        if (! self::isConflicting()) {
            $output->writeln('No conflicts found');

            return 0;
        }

        $conflictSource = self::getConflictSource();
        $composerJsons = self::getBothComposerJsons($conflictSource);
        if (! $composerJsons->isOnlyDiffInRequirements()) {
            $output->writeln('Conflicts are not only in requirements, sorry you have to fix this one manually');

            return 1;
        }

        $printer = new ThreeColumnPrinter($terminalWidth, $output);

        foreach ($composerJsons->getRequireDiffs() as $diff) {
            $output->writeln(self::sprintfn($diff->getDiffType()->getHelpText(), ['packageName' => $diff->name]));
            $ourVersion = sprintf('    "%s": "%s",', $diff->name, $diff->oursVersion);
            $baseVersion = sprintf('    "%s": "%s",', $diff->name, $diff->baseVersion);
            $theirVersion = sprintf('    "%s": "%s",', $diff->name, $diff->theirsVersion);

            $printer->printDiff($diff);
        }

        return 0;
    }

    /**
     * Change dir so we can do all operations from the root of the repo
     */
    private static function chdirToRepo(): void
    {
        $process = new Process(['git', 'rev-parse', '--show-toplevel']);
        $process->run();
        $newCwd = trim($process->getOutput());
        chdir($newCwd);
    }

    /**
     * Check if the composer.json or composer.lock file actually have conflicts
     */
    private static function isConflicting(): bool
    {
        $process = new Process(['git', 'diff', '--name-only', '--diff-filter=U', '--no-relative']);
        $process->run();
        $output = $process->getOutput();
        $lines = explode(PHP_EOL, $output);
        $foundJson = in_array('composer.json', $lines);
        $foundLock = in_array('composer.lock', $lines);

        return $foundJson || $foundLock;
    }

    /**
     * Get the source of the conflict
     *
     *
     * @throws \RuntimeException
     */
    private static function getConflictSource(): ConflictSource
    {
        if (file_exists('.git/MERGE_HEAD')) {
            return ConflictSource::MERGE;
        }
        if (file_exists('.git/CHERRY_PICK_HEAD')) {
            return ConflictSource::CHERRY_PICK;
        }
        if (file_exists('.git/REBASE_HEAD')) {
            return ConflictSource::REBASE;
        }
        throw new \RuntimeException('Could not determine conflict source');
    }

    private static function getBothComposerJsons(ConflictSource $source): ConflictingComposerJson
    {
        $process = new Process(['git', 'show', ':1:composer.json']);
        $process->run();
        $base = $process->getOutput();
        $process = new Process(['git', 'show', ':2:composer.json']);
        $process->run();
        $ours = $process->getOutput();
        $process = new Process(['git', 'show', ':3:composer.json']);
        $process->run();
        $theirs = $process->getOutput();

        return new ConflictingComposerJson($base, $ours, $theirs);
    }

    /**
     * version of sprintf for cases where named arguments are desired (python syntax)
     *
     * with sprintf: sprintf('second: %2$s ; first: %1$s', '1st', '2nd');
     *
     * with sprintfn: sprintfn('second: %(second)s ; first: %(first)s', array(
     *  'first' => '1st',
     *  'second'=> '2nd'
     * ));
     *
     * @param  string  $format  sprintf format string, with any number of named arguments
     * @param  array<string, mixed>  $args  array of [ 'arg_name' => 'arg value', ... ] replacements to be made
     * @return string|false result of sprintf call, or bool false on error
     */
    private static function sprintfn($format, array $args = []): string|false
    {
        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys([0 => 0] + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)\(([a-zA-Z_]\w*)\)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = $match[0][1];
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (! array_key_exists($arg_key, $arg_nums)) {
                trigger_error("sprintfn(): Missing argument '{$arg_key}'", E_USER_WARNING);

                return false;
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key].'$', $arg_pos, $arg_len);
            $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        return vsprintf($format, array_values($args));
    }
}
