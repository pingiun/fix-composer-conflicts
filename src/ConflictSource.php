<?php

namespace Pingiun\FixConflicts;

enum ConflictSource
{
    case MERGE;
    case REBASE;
    case CHERRY_PICK;

    public function getHeadName(): string
    {
        return match ($this) {
            self::MERGE => 'MERGE_HEAD',
            self::REBASE => 'REBASE_HEAD',
            self::CHERRY_PICK => 'CHERRY_PICK_HEAD',
        };
    }
}
