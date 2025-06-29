<?php

namespace Pingiun\FixConflicts;

use Composer\Semver\Comparator;
use Composer\Semver\Constraint\Constraint;

final class PackageDiff {
    public function __construct(
        public string $name,
        public ?string $baseVersion,
        public ?string $oursVersion,
        public ?string $theirsVersion,
    )
    {
    }

    public function getDiffType(): DiffType
    {
        // Package was added somewhere
        if ($this->baseVersion === null) {
            if ($this->oursVersion !== null && $this->theirsVersion !== null) {
                return DiffType::ADD_BOTH;
            }
            if ($this->oursVersion !== null) {
                return DiffType::ADD_OURS;
            }
            if ($this->theirsVersion !== null) {
                return DiffType::ADD_THEIRS;
            }
        }
        // Package was deleted somewhere
        if ($this->oursVersion === null) {
            return DiffType::DELETE_OURS;
        }
        if ($this->theirsVersion === null) {
            return DiffType::DELETE_THEIRS;
        }
        $versionParser = new \Composer\Semver\VersionParser();
        $baseConstraint = $versionParser->parseConstraints($this->baseVersion);
        $oursConstraint = $versionParser->parseConstraints($this->oursVersion);
        $theirsConstraint = $versionParser->parseConstraints($this->theirsVersion);
        if ($oursConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '>') && $theirsConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '>')) {
            return DiffType::UPGRADE_BOTH;
        }
        if ($oursConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '<') && $theirsConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '<')) {
            return DiffType::DOWNGRADE_BOTH;
        }
        if ($oursConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '<') && $theirsConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '>')) {
            return DiffType::DOWNGRADE_OURS_UPGRADE_THEIRS;
        }
        if ($oursConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '>') && $theirsConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '<')) {
            return DiffType::UPGRADE_OURS_DOWNGRADE_THEIRS;
        }
        if ($oursConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '>')) {
            return DiffType::UPGRADE_OURS;
        }
        if ($theirsConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '>')) {
            return DiffType::UPGRADE_THEIRS;
        }
        if ($oursConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '<')) {
            return DiffType::DOWNGRADE_OURS;
        }
        if ($theirsConstraint->getLowerBound()->compareTo($baseConstraint->getLowerBound(), '<')) {
            return DiffType::DOWNGRADE_THEIRS;
        }
        throw new \RuntimeException("Unreachable");
    }
}
