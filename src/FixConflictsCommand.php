<?php

namespace Pingiun\FixConflicts;

use Composer\Semver\VersionParser;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

final class FixConflictsCommand extends Command
{
    #[Override]
    protected function configure(): void
    {
        $this->setName('fix-conflicts');
        $this->addOption('base', null, InputOption::VALUE_OPTIONAL, 'Override the branch to use as the base for the composer.json, the other changes will be made to the base branch version of composer.json');
        $this->addOption('least-as-base', null, InputOption::VALUE_NONE, 'Use the version of composer.json that needs the least changes as the base version');
        $this->setDescription('Difficulty merging a branch that also changed composer.json? Run fix-composer-conflicts. (No command line options needed, this is an interactive command)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validateOptions($input);

        $terminal = new Terminal;
        $terminalWidth = $terminal->getWidth();
        self::chdirToRepo();

        if (! self::isConflicting()) {
            $output->writeln('No conflicts found');

            return 0;
        }

        $conflictSource = self::getConflictSource();
        match ($conflictSource) {
            ConflictSource::MERGE => $output->writeln('<info>You are doing a merge, here `ours` means the branch you were checked out in. `theirs` is the branch you are trying to merge and `base` is best common ancestor of the two branches.</info>'),
            ConflictSource::REBASE => $output->writeln('<info>You are doing a rebase, here `ours` means the target branch you chose to rebase onto. `theirs` is the branch you just had checked out. `base` is the common ancestor of the two branches.</info>'),
            ConflictSource::CHERRY_PICK => $output->writeln('<info>You are cherry-picking, here `ours` is the tree you just had checked out. `theirs` is the tree you are cherry-picking. `base` is the common ancestor of the two trees.</info>'),
        };

        $composerJsons = self::getBothComposerJsons();
        if (! $composerJsons->isOnlyDiffInRequirements()) {
            $output->writeln('Conflicts are not only in requirements, sorry you have to fix this one manually');

            return 1;
        }

        $oursCommit = trim(self::procGetOutput(['git', 'rev-parse', '--verify', 'ORIG_HEAD']));
        $oursRef = explode(' ', trim(self::procGetOutput(['git', 'name-rev', '--always', 'ORIG_HEAD'])), 2)[1];
        $oursRef = preg_replace('/^remotes\/origin\//', '', $oursRef);
        $theirsCommit = trim(self::procGetOutput(['git', 'rev-parse', '--verify', $conflictSource->getHeadName()]));
        $theirsRef = explode(' ', trim(self::procGetOutput(['git', 'name-rev', '--always', '--exclude', 'HEAD', $conflictSource->getHeadName()])), 2)[1];
        $theirsRef = preg_replace('/^remotes\/origin\//', '', $theirsRef);

        $printer = new ThreeColumnPrinter($terminalWidth, $output);
        if (! preg_match('/^[a-f0-9]{40}[a-f0-9]{24}?$/', $oursRef)) {
            $printer->setOursName($oursRef);
        }
        if (! preg_match('/^[a-f0-9]{40}[a-f0-9]{24}?$/', $theirsRef)) {
            $printer->setTheirsName($theirsRef);
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $requireDiffs = $composerJsons->getRequireDiffs();
        $requireDevDiffs = $composerJsons->getRequireDevDiffs();
        $allDiffs = array_merge($requireDiffs, $requireDevDiffs);
        $resolutions = [];
        foreach ($allDiffs as $i => $diff) {
            $manualVersion = null;
            $output->writeln('');
            $output->writeln(self::sprintfn($diff->getDiffType()->getHelpText(), ['packageName' => $diff->name]));
            $printer->printDiff($diff);
            $output->writeln('');
            while (true) {
                $answer = $helper->ask($input, $output, new Question(sprintf('<fg=blue>(%s/%s) How to resolve [o,b,t,n,r,m,a,?]?</> ', $i + 1, count($allDiffs))));
                if ($answer === null) {
                    $output->writeln('ours/base/theirs/newest/remove/manual/abort/help');

                    continue;
                } elseif (preg_match('/^o|ou|our|ours$/i', $answer)) {
                    $choice = ResolutionChoice::OURS;
                } elseif (preg_match('/^b|ba|bas|base$/i', $answer)) {
                    $choice = ResolutionChoice::BASE;
                } elseif (preg_match('/^t|th|the|thei|their|theirs$/i', $answer)) {
                    $choice = ResolutionChoice::THEIRS;
                } elseif (preg_match('/^n|ne|new|newe|newes|newest$/i', $answer)) {
                    $choice = ResolutionChoice::NEWEST;
                } elseif (preg_match('/^r|re|rem|remo|remov|remove$/i', $answer)) {
                    $choice = ResolutionChoice::REMOVE;
                } elseif (preg_match('/^m|ma|man|manu|manua|manual$/i', $answer)) {
                    $choice = ResolutionChoice::MANUAL;
                    $manualVersion = self::getManualVersion($input, $output, $helper);
                    if ($manualVersion === null) {
                        continue;
                    }
                } elseif (preg_match('/^a|ab|abo|abor|abort$/i', $answer)) {
                    $choice = ResolutionChoice::ABORT;
                } elseif (preg_match('/^\?|help$/i', $answer)) {
                    $output->writeln('<info>o - choose the "ours" side</info>');
                    $output->writeln('<info>b - choose the "base" side</info>');
                    $output->writeln('<info>t - choose the "theirs" side</info>');
                    $output->writeln('<info>n - choose the newest side</info>');
                    $output->writeln('<info>r - remove this package</info>');
                    $output->writeln('<info>m - manually set the version for this package</info>');
                    $output->writeln('<info>a - abort</info>');
                    $output->writeln('<info>? - show this help</info>');

                    continue;
                } else {
                    $output->writeln("One of the letters is expected, got '$answer'");

                    continue;
                }
                break;
            }
            if ($choice === ResolutionChoice::ABORT) {
                $output->writeln('Aborting, conflict markers will be left in place');

                return 0;
            }
            $resolutions[] = new DiffResolution($diff, $choice, $manualVersion, $diff->isDevDependency);
        }

        $output->writeln('');
        $output->writeln('<info>Applying '.count($resolutions).' resolutions</info>');

        $hasMainBranch = in_array($oursRef, ['main', 'master']) || in_array($theirsRef, ['main', 'master']);

        if (($base = $input->getOption('base')) != null) {
            if ($oursRef === $base) {
                $packageSetBase = ResolutionChoice::THEIRS;
            } elseif ($theirsRef === $base) {
                $packageSetBase = ResolutionChoice::OURS;
            } else {
                throw new \RuntimeException('Base branch not found');
            }
        } elseif ($input->getOption('least-as-base') || ! $hasMainBranch) {
            $mostUsed = array_count_values(array_map(fn (DiffResolution $x) => $x->choice->name, $resolutions));
            if (
                ($mostUsed[ResolutionChoice::OURS->name] ?? 0) >= ($mostUsed[ResolutionChoice::THEIRS->name] ?? 0)
            ) {
                $packageSetBase = ResolutionChoice::OURS;
            } else {
                $packageSetBase = ResolutionChoice::THEIRS;
            }
        } else {
            if (in_array($oursRef, ['main', 'master'], true)) {
                $packageSetBase = ResolutionChoice::OURS;
            } elseif (in_array($theirsRef, ['main', 'master'], true)) {
                $packageSetBase = ResolutionChoice::THEIRS;
            } else {
                throw new \RuntimeException('Main branch not found');
            }
        }

        $baseComposerJson = $composerJsons->generateComposerJsonWithPackageSetBase($packageSetBase);
        file_put_contents('composer.json', $baseComposerJson);
        if ($packageSetBase === ResolutionChoice::OURS) {
            self::procMustSucceed(['git', 'restore', "--source={$oursCommit}", '--', 'composer.lock']);
        } else {
            self::procMustSucceed(['git', 'restore', "--source={$theirsCommit}", '--', 'composer.lock']);
        }

        $requirePackages = [];
        $requireDevPackages = [];
        $removePackages = [];
        $removeDevPackages = [];
        foreach ($resolutions as $resolution) {
            if ($resolution->choice === $packageSetBase) {
                continue;
            }
            if ($resolution->getAction() === 'remove') {
                if ($resolution->isDevDependency) {
                    $removeDevPackages[] = $resolution->getPackage();
                } else {
                    $removePackages[] = $resolution->getPackage();
                }
            } else {
                if ($resolution->isDevDependency) {
                    $requireDevPackages[] = sprintf("%s:%s", $resolution->getPackage(), $resolution->getVersion());
                } else {
                    $requirePackages[] = sprintf("%s:%s", $resolution->getPackage(), $resolution->getVersion());
                }
            }
        }

        $progressBar = new ProgressBar($output, array_sum([count($removePackages) > 0, count($removeDevPackages) > 0, count($requirePackages) > 0, count($requireDevPackages) > 0]));

        try {
            if ($removePackages) {
                self::procMustSucceed(self::buildRemoveCommand($removePackages, false));
                $progressBar->advance();
            }
            if ($removeDevPackages) {
                self::procMustSucceed(self::buildRemoveCommand($removeDevPackages, true));
                $progressBar->advance();
            }
            if ($requirePackages) {
                self::procMustSucceed(self::buildRequireCommand($requirePackages, false));
                $progressBar->advance();
            }
            if ($requireDevPackages) {
                self::procMustSucceed(self::buildRequireCommand($requireDevPackages, true));
                $progressBar->advance();
            }
            $progressBar->finish();
        } catch (ProcessFailedException $e) {
            $progressBar->clear();
            $output->writeln('Failed to apply resolutions, aborting');
            $output->writeln($e->getNiceMessage());
            $output->writeln($e->output);

            // Restore conflict markers
            self::procMustSucceed(['git', 'restore', '--merge', '--', 'composer.json', 'composer.lock']);

            return 1;
        }

        // If everything went well, add the two files to the index
        self::procMustSucceed(['git', 'add', '--', 'composer.json', 'composer.lock']);

        $output->writeln('');
        if (trim(self::procGetOutput(['git', 'diff', '--cached', '--name-only', '--diff-filter=U'])) !== '') {
            $output->writeln('<info>Fixed composer conflicts, but you still have other conflicts you need to fix!</info>');
        } else {
            $output->writeln('<info>All conflicts resolved, you can now commit!</info>');
        }

        return 0;
    }

    /**
     * Change dir so we can do all operations from the root of the repo
     */
    private static function chdirToRepo(): void
    {
        $newCwd = trim(self::procGetOutput(['git', 'rev-parse', '--show-toplevel']));
        chdir($newCwd);
    }

    /**
     * Check if the composer.json or composer.lock file actually have conflicts
     */
    private static function isConflicting(): bool
    {
        $output = self::procGetOutput(['git', 'diff', '--name-only', '--diff-filter=U', '--no-relative']);
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

    private static function getBothComposerJsons(): ConflictingComposerJson
    {
        $base = self::procGetOutput(['git', 'show', ':1:composer.json']);
        $ours = self::procGetOutput(['git', 'show', ':2:composer.json']);
        $theirs = self::procGetOutput(['git', 'show', ':3:composer.json']);

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
     * @return string result of sprintf call
     */
    private static function sprintfn($format, array $args = []): string
    {
        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys([0 => 0] + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)\(([a-zA-Z_]\w*)\)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = intval($match[0][1]);
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (! array_key_exists($arg_key, $arg_nums)) {
                throw new \InvalidArgumentException("sprintfn(): Missing argument '{$arg_key}'");
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key].'$', $arg_pos, $arg_len);
            $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        return vsprintf($format, array_values($args));
    }

    private static function getManualVersion(InputInterface $input, OutputInterface $output, QuestionHelper $helper): ?string
    {
        while (true) {
            $version = $helper->ask($input, $output, new Question('Enter the version you want to set for this package: '));
            if ($version === null) {
                return null;
            }
            $versionParser = new VersionParser;
            try {
                $versionParser->parseConstraints($version);

                return $version;
            } catch (\Throwable $e) {
                $output->writeln($e->getMessage());
            }
        }
    }

    /**
     * @param  string[]  $command
     * @return string the process output
     *
     * @throws ProcessFailedException When the process exited with a nonzero exit code
     * @throws ProcessStartFailedException When process can't be launched
     * @throws RuntimeException When process is already running
     * @throws ProcessTimedOutException When process timed out
     * @throws ProcessSignaledException When process stopped after receiving signal
     */
    private static function procGetOutput(array $command): string
    {
        $process = new Process($command);
        $ret = $process->run();
        if ($ret !== 0) {
            throw new ProcessFailedException(implode(' ', $command), $process->getOutput().PHP_EOL.$process->getErrorOutput(), 'Command '.implode(' ', $command).' failed');
        }

        return $process->getOutput();
    }

    /**
     * @param  string[]  $command
     *
     * @throws ProcessFailedException When the process exited with a nonzero exit code
     * @throws ProcessStartFailedException When process can't be launched
     * @throws RuntimeException When process is already running
     * @throws ProcessTimedOutException When process timed out
     * @throws ProcessSignaledException When process stopped after receiving signal
     */
    private static function procMustSucceed(array $command): void
    {
        self::procGetOutput($command);
    }

    /**
     * @return string[]
     */
    private static function buildRequireCommand(array $packages, bool $isDev)
    {
        $command = [self::composerBinary(), 'remove', '--ignore-platform-reqs'];
        if ($isDev) {
            $command[] = '--dev';
        }

        return array_merge($command, $packages);
    }

    private static function buildRemoveCommand(array $packages, bool $isDev)
    {
        $command = [self::composerBinary(), 'remove', '--ignore-platform-reqs'];
        if ($isDev) {
            $command[] = '--dev';
        }

        return array_merge($command, $packages);
    }

    private function validateOptions(InputInterface $input)
    {
        if ($input->getOption('base') && $input->getOption('least-as-base')) {
            throw new \InvalidArgumentException('Cannot use --base and --least-as-base together');
        }
    }

    private static function composerBinary(): string
    {
        $composer = getenv('COMPOSER_COMMAND');
        if ($composer) {
            return $composer;
        }

        return 'composer';
    }
}
