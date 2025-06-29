<?php

namespace Pingiun\FixConflicts;

final readonly class ConflictingComposerJson
{
    /**
     * @var array<string, mixed>
     */
    private array $ours;

    /**
     * @var array<string, mixed>
     */
    private array $theirs;
    /**
     * @var array<string, mixed>
     */
    private array $base;

    public function __construct(
        string $base,
        string $ours, string $theirs
    ) {
        $this->base = json_decode($base, true);
        $this->ours = json_decode($ours, true);
        $this->theirs = json_decode($theirs, true);
    }

    /**
     * Check if the only difference is in the requirements, this is the only part that the conflict fixer can fix
     */
    public function isOnlyDiffInRequirements(): bool
    {
        foreach (array_merge(array_keys($this->ours), array_keys($this->theirs)) as $key) {
            if (in_array($key, ['require', 'require-dev'])) {
                continue;
            }
            if (! array_key_exists($key, $this->ours) || ! array_key_exists($key, $this->theirs)) {
                return false;
            }
            if ($this->ours[$key] !== $this->theirs[$key]) {
                return false;
            }
        }
        // if the requirements are the same, and the dev requirements are the same, there is something weird going on with this conflict but we surely can't fix it
        if (($this->ours['require'] ?? null) === ($this->theirs['require'] ?? null)
            && ($this->ours['require-dev'] ?? null) === ($this->theirs['require-dev'] ?? null)) {
            return false;
        }

        return true;
    }

    /**
     * @return PackageDiff[]
     */
    public function getRequireDiffs(): array
    {
        return $this->getDiffsWithinArray('require');
    }

    /**
     * @return PackageDiff[]
     */
    public function getRequireDevDiffs(): array
    {
        return $this->getDiffsWithinArray('require-dev');
    }

    /**
     * @param string $key
     * @return PackageDiff[]
     */
    private function getDiffsWithinArray(string $key): array
    {
        $diffs = [];
        foreach (array_unique(array_merge(array_keys($this->base[$key] ?? []), array_keys($this->ours[$key] ?? []), array_keys($this->theirs[$key] ?? []))) as $package) {
            // Check if there is any difference in the versions
            $packageName = $package;
            $baseVersion = $this->base[$key][$package] ?? null;
            $oursVersion = $this->ours[$key][$package] ?? null;
            $theirsVersion = $this->theirs[$key][$package] ?? null;
            if ($baseVersion === $oursVersion && $baseVersion === $theirsVersion) {
                continue;
            }
            $diffs[] = new PackageDiff($packageName, $baseVersion, $oursVersion, $theirsVersion);
        }
        return $diffs;
    }
}
