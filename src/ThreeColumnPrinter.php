<?php

namespace Pingiun\FixConflicts;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class ThreeColumnPrinter
{
    private OutputInterface $output;
    private int $widthPerColumn;

    public function __construct(int $terminalWidth, OutputInterface $output)
    {
        $this->output = $output;
        $this->widthPerColumn = intval(($terminalWidth - 4) / 3);

        $boldStyle = new OutputFormatterStyle(options: ['bold']);
        $stringStyle = new OutputFormatterStyle('blue');
        $versionStyle = new OutputFormatterStyle('green');
        $addedStyle = new OutputFormatterStyle(background: 'green', options: ['bold']);
        $removedStyle = new OutputFormatterStyle(background: 'red', options: ['bold']);
        $changedStyle = new OutputFormatterStyle(background: 'yellow', options: ['bold']);
        $output->getFormatter()->setStyle('b', $boldStyle);
        $output->getFormatter()->setStyle('s', $stringStyle);
        $output->getFormatter()->setStyle('v', $versionStyle);
        $output->getFormatter()->setStyle('a', $addedStyle);
        $output->getFormatter()->setStyle('r', $removedStyle);
        $output->getFormatter()->setStyle('c', $changedStyle);
    }

    public function printDiff(PackageDiff $diff): void
    {
        $this->printHeader();
        $this->printContent($diff);
        $this->printFooter();
    }

    private function printHeader(): void
    {
        $this->output->writeln(
            sprintf('┌%s┬%s┬%s┐',
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH)
            )
        );
        $this->output->writeln(
            sprintf('│<b>%s</b>│<b>%s</b>│<b>%s</b>│',
                str_pad('Ours:', $this->widthPerColumn, ' ', STR_PAD_BOTH),
                str_pad('Base:', $this->widthPerColumn, ' ', STR_PAD_BOTH),
                str_pad('Theirs:', $this->widthPerColumn, ' ', STR_PAD_BOTH)
            )
        );
        $this->output->writeln(
            sprintf('├%s┼%s┼%s┤',
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH)
            )
        );
        $this->output->writeln(
            sprintf('│%s│%s│%s│',
                str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
                str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
                str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH)
            )
        );
    }

    private function printContent(PackageDiff $diff): void
    {
        $oursText = sprintf('"%s": "%s",', $diff->name, $diff->oursVersion);
        $baseText = '-';
        if ($diff->baseVersion !== null) {
            $baseText = sprintf('"%s": "%s",', $diff->name, $diff->baseVersion);
        }
        $theirsText = sprintf('"%s": "%s",', $diff->name, $diff->theirsVersion);

        $paddedOurs = str_pad($oursText, $this->widthPerColumn, ' ', STR_PAD_BOTH);
        $paddedBase = str_pad($baseText, $this->widthPerColumn, ' ', STR_PAD_BOTH);
        $paddedTheirs = str_pad($theirsText, $this->widthPerColumn, ' ', STR_PAD_BOTH);

        $oursLeftPad = strlen($paddedOurs) - strlen(ltrim($paddedOurs, ' '));
        $oursRightPad = strlen($paddedOurs) - strlen(rtrim($paddedOurs, ' '));

        $baseLeftPad = strlen($paddedBase) - strlen(ltrim($paddedBase, ' '));
        $baseRightPad = strlen($paddedBase) - strlen(rtrim($paddedBase, ' '));

        $theirsLeftPad = strlen($paddedTheirs) - strlen(ltrim($paddedTheirs, ' '));
        $theirsRightPad = strlen($paddedTheirs) - strlen(rtrim($paddedTheirs, ' '));

        $this->output->writeln(
            sprintf('│%s%s%s│%s%s%s│%s%s%s│',
                str_repeat(' ', $oursLeftPad),
                sprintf('<s>"%s"</s>: <v>"%s"</v>,', $diff->name, $diff->oursVersion),
                str_repeat(' ', $oursRightPad),
                str_repeat(' ', $baseLeftPad),
                sprintf('<s>"%s"</s>: <v>"%s"</v>,', $diff->name, $diff->baseVersion),
                str_repeat(' ', $baseRightPad),
                str_repeat(' ', $theirsLeftPad),
                sprintf('<s>"%s"</s>: <v>"%s"</v>,', $diff->name, $diff->theirsVersion),
                str_repeat(' ', $theirsRightPad)
            )
        );
        $this->output->writeln(
            sprintf('│%s│%s│%s│',
                str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
                str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
                str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH)
            )
        );
    }

    private function printFooter(): void
    {
        $this->output->writeln(
            sprintf('└%s┴%s┴%s┘',
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
                mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH)
            )
        );
    }
}
