<?php

namespace Pingiun\FixConflicts;

enum DiffType
{
    /** Package was not present in the base, but was only added in the `ours` tree */
    case ADD_OURS;
    /** Package was not present in the base, but was only added in the `theirs` tree */
    case ADD_THEIRS;
    /** Added in both but with different versions */
    case ADD_BOTH;
    /** Package had older version in the base, and was upgraded to a higher version in the `ours` tree */
    case UPGRADE_OURS;
    /** Package had older version in the base, and was upgraded to a higher version in the `theirs` tree */
    case UPGRADE_THEIRS;
    /** Package had older version in the base, and was upgraded to a higher version both trees, but different new versions */
    case UPGRADE_BOTH;
    case DOWNGRADE_OURS;
    case DOWNGRADE_THEIRS;
    case DOWNGRADE_BOTH;
    /** Package was downgraded in the `ours` tree, but upgraded in the `theirs` tree */
    case DOWNGRADE_OURS_UPGRADE_THEIRS;
    /** Package was upgraded in the `ours` tree, but downgraded in the `theirs` tree */
    case UPGRADE_OURS_DOWNGRADE_THEIRS;
    /** Package was present in the base, but deleted in the `ours` tree */
    case DELETE_OURS;
    /** Package was present in the base, but deleted in the `theirs` tree */
    case DELETE_THEIRS;

    public function getHelpText(): string
    {
        return match ($this) {
            self::ADD_OURS => '%(packageName)s that was not present in the base yet, was added in the `ours` tree',
            self::ADD_THEIRS => '%(packageName)s that was not present in the base yet, was added in the `theirs` tree',
            self::ADD_BOTH => '%(packageName)s that was not present in the base yet, was added in both trees with different versions',
            self::UPGRADE_OURS => '%(packageName)s that was present in the base, was upgraded in the `ours` tree',
            self::UPGRADE_THEIRS => '%(packageName)s that was present in the base, was upgraded in the `theirs` tree',
            self::UPGRADE_BOTH => '%(packageName)s that was present in the base, was upgraded both trees, but to different versions',
            self::DOWNGRADE_OURS => '%(packageName)s that was present in the base, was downgraded in the `ours` tree',
            self::DOWNGRADE_THEIRS => '%(packageName)s that was present in the base, was downgraded in the `theirs` tree',
            self::DOWNGRADE_BOTH => '%(packageName)s that was present in the base, was downgraded both trees, but to different versions',
            self::DOWNGRADE_OURS_UPGRADE_THEIRS => '%(packageName)s that was present in the base, was downgraded in the `ours` tree, but upgraded in the `theirs` tree',
            self::UPGRADE_OURS_DOWNGRADE_THEIRS => '%(packageName)s that was present in the base, was upgraded in the `ours` tree, but downgraded in the `theirs` tree',
            self::DELETE_OURS => '%(packageName)s that was present in the base, was deleted in the `ours` tree',
            self::DELETE_THEIRS => '%(packageName)s that was present in the base, was deleted in the `theirs` tree',
        };
    }
}
