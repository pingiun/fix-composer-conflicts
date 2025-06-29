<?php

namespace Pingiun\FixConflicts;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ThreeColumnPrinter
{
    private OutputInterface $output;

    private int $widthPerColumn;

    private int $terminalWidth;

    public function __construct(int $terminalWidth, OutputInterface $output)
    {
        $this->output = $output;
        $this->terminalWidth = $terminalWidth;
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
        $lines = $this->formatDiff($diff);
        foreach ($lines as $line) {
            $this->output->writeln($line);
        }
    }

    /**
     * Format the diff into an array of lines without printing
     *
     * @param  PackageDiff  $diff  The package diff to format
     * @return string[] Array of formatted lines
     */
    public function formatDiff(PackageDiff $diff): array
    {
        $lines = [];
        $lines = array_merge($lines, $this->formatHeader());
        $lines = array_merge($lines, $this->formatContent($diff));
        $lines = array_merge($lines, $this->formatFooter());

        return $lines;
    }

    /**
     * Format the header into an array of lines
     *
     * @return string[] Array of formatted header lines
     */
    private function formatHeader(): array
    {
        $lines = [];
        $lines[] = sprintf('┌%s┬%s┬%s┐',
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH)
        );
        $lines[] = sprintf('│<b>%s</b>│<b>%s</b>│<b>%s</b>│',
            str_pad('Ours:', $this->widthPerColumn, ' ', STR_PAD_BOTH),
            str_pad('Base:', $this->widthPerColumn, ' ', STR_PAD_BOTH),
            str_pad('Theirs:', $this->widthPerColumn, ' ', STR_PAD_BOTH)
        );
        $lines[] = sprintf('├%s┼%s┼%s┤',
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH)
        );
        $lines[] = sprintf('│%s│%s│%s│',
            str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
            str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
            str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH)
        );

        return $lines;
    }

    /**
     * Format the content into an array of lines
     *
     * @param  PackageDiff  $diff  The package diff to format
     * @return string[] Array of formatted content lines
     */
    private function formatContent(PackageDiff $diff): array
    {
        $lines = [];

        // Calculate the maximum width for each column's content
        // We need to account for the border characters and padding
        // The total width is terminalWidth, and we need to subtract 4 for the border characters (│)
        // Then divide by 3 for the three columns
        $maxContentWidth = max(1, intval(($this->terminalWidth - 4) / 3));

        // For each column, we need to ensure the total content (name + version + formatting) fits
        // within maxContentWidth. We'll allocate space for the version first, then use remaining
        // space for the name.

        // Get the actual width of each version
        $oursVersionWidth = mb_strlen($diff->oursVersion ?? '');
        $baseVersionWidth = mb_strlen($diff->baseVersion ?? '');
        $theirsVersionWidth = mb_strlen($diff->theirsVersion ?? '');

        // Calculate the maximum allowed version width
        // We need to leave at least 1 character for the name and 7 for quotes, colon, and comma
        $maxAllowedVersionWidth = max(1, intval($maxContentWidth / 2));

        // Use the actual version width if it fits, otherwise use the maximum allowed
        $versionMaxWidth = min(max($oursVersionWidth, $baseVersionWidth, $theirsVersionWidth), $maxAllowedVersionWidth);

        // Allocate remaining space to the package name
        // The total content width must not exceed maxContentWidth
        $nameMaxWidth = max(1, $maxContentWidth - $versionMaxWidth - 7); // 7 for the quotes, colon, and comma

        // Format the content for each column
        $oursContent = $diff->oursVersion === null ?
            str_pad('-', $maxContentWidth, ' ', STR_PAD_BOTH) :
            $this->formatColumnContent($diff->name, $diff->oursVersion, $nameMaxWidth, $versionMaxWidth);
        $baseContent = $diff->baseVersion === null
            ? str_pad('-', $maxContentWidth, ' ', STR_PAD_BOTH)
            : $this->formatColumnContent($diff->name, $diff->baseVersion, $nameMaxWidth, $versionMaxWidth);
        $theirsContent = $diff->theirsVersion === null ?
            str_pad('-', $maxContentWidth, ' ', STR_PAD_BOTH)
            : $this->formatColumnContent($diff->name, $diff->theirsVersion, $nameMaxWidth, $versionMaxWidth);

        // Pad the content to fit the column width
        $paddedOurs = str_pad($oursContent, $this->widthPerColumn, ' ', STR_PAD_BOTH);
        $paddedBase = str_pad($baseContent, $this->widthPerColumn, ' ', STR_PAD_BOTH);
        $paddedTheirs = str_pad($theirsContent, $this->widthPerColumn, ' ', STR_PAD_BOTH);

        // Calculate padding
        $oursLeftPad = strlen($paddedOurs) - strlen(ltrim($paddedOurs, ' '));
        $oursRightPad = strlen($paddedOurs) - strlen(rtrim($paddedOurs, ' '));

        $baseLeftPad = strlen($paddedBase) - strlen(ltrim($paddedBase, ' '));
        $baseRightPad = strlen($paddedBase) - strlen(rtrim($paddedBase, ' '));

        $theirsLeftPad = strlen($paddedTheirs) - strlen(ltrim($paddedTheirs, ' '));
        $theirsRightPad = strlen($paddedTheirs) - strlen(rtrim($paddedTheirs, ' '));

        // Format the line with proper padding
        $lines[] = sprintf('│%s%s%s│%s%s%s│%s%s%s│',
            str_repeat(' ', $oursLeftPad),
            $diff->oursVersion === null
                ? '<fg=gray>-</>'
                : $this->formatColumnContentWithTags($diff->name, $diff->oursVersion, $nameMaxWidth, $versionMaxWidth),
            str_repeat(' ', $oursRightPad),
            str_repeat(' ', $baseLeftPad),
            $diff->baseVersion === null
                ? '<fg=gray>-</>'
                : $this->formatColumnContentWithTags($diff->name, $diff->baseVersion, $nameMaxWidth, $versionMaxWidth),
            str_repeat(' ', $baseRightPad),
            str_repeat(' ', $theirsLeftPad),
            $diff->theirsVersion === null
                ? '<fg=gray>-</>'
                : $this->formatColumnContentWithTags($diff->name, $diff->theirsVersion, $nameMaxWidth, $versionMaxWidth),
            str_repeat(' ', $theirsRightPad)
        );

        $lines[] = sprintf('│%s│%s│%s│',
            str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
            str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH),
            str_pad(' ', $this->widthPerColumn, ' ', STR_PAD_BOTH)
        );

        return $lines;
    }

    /**
     * Format column content without tags
     *
     * @param  string  $name  The package name
     * @param  string|null  $version  The package version
     * @param  int  $nameMaxWidth  Maximum width for the name
     * @param  int  $versionMaxWidth  Maximum width for the version
     * @return string The formatted column content
     */
    private function formatColumnContent(string $name, ?string $version, int $nameMaxWidth, int $versionMaxWidth): string
    {
        $truncatedName = $this->truncateText($name, $nameMaxWidth);
        // Prioritize showing the full version, but truncate if it exceeds the maximum width
        $versionText = $this->truncateText($version ?? '', $versionMaxWidth);

        return sprintf('"%s": "%s",', $truncatedName, $versionText);
    }

    /**
     * Format column content with tags
     *
     * @param  string  $name  The package name
     * @param  string|null  $version  The package version
     * @param  int  $nameMaxWidth  Maximum width for the name
     * @param  int  $versionMaxWidth  Maximum width for the version
     * @return string The formatted column content with tags
     */
    private function formatColumnContentWithTags(string $name, ?string $version, int $nameMaxWidth, int $versionMaxWidth): string
    {
        $truncatedName = $this->truncateText($name, $nameMaxWidth);
        // Prioritize showing the full version, but truncate if it exceeds the maximum width
        $versionText = $this->truncateText($version ?? '', $versionMaxWidth);

        return sprintf('<s>"%s"</s>: <v>"%s"</v>,', $truncatedName, $versionText);
    }

    /**
     * Format the footer into an array of lines
     *
     * @return string[] Array of formatted footer lines
     */
    private function formatFooter(): array
    {
        $lines = [];
        $lines[] = sprintf('└%s┴%s┴%s┘',
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH),
            mb_str_pad('─', $this->widthPerColumn, '─', STR_PAD_BOTH)
        );

        return $lines;
    }

    /**
     * Truncate text to fit within a specified width
     *
     * @param  string|null  $text  The text to truncate
     * @param  int  $width  The maximum width
     * @return string The truncated text
     */
    private function truncateText(?string $text, int $width): string
    {
        if ($text === null) {
            return '';
        }

        if (mb_strlen($text) <= $width) {
            return $text;
        }

        return mb_substr($text, 0, $width - 1).'…';
    }

    /**
     * Get the terminal width used by this printer
     *
     * @return int The terminal width
     */
    public function getTerminalWidth(): int
    {
        return $this->terminalWidth;
    }

    /**
     * Get the width per column
     *
     * @return int The width per column
     */
    public function getWidthPerColumn(): int
    {
        return $this->widthPerColumn;
    }
}
