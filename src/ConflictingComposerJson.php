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
        string $ours,
        string $theirs
    ) {
        $this->base = json_decode($base, true, flags: JSON_THROW_ON_ERROR);
        $this->ours = json_decode($ours, true, flags: JSON_THROW_ON_ERROR);
        $this->theirs = json_decode($theirs, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Check if the only conflict is in the requirements, this is the only part that the conflict fixer can fix
     */
    public function isOnlyDiffInRequirements(): bool
    {
        foreach (array_merge(array_keys($this->ours), array_keys($this->theirs)) as $key) {
            if (in_array($key, ['require', 'require-dev'])) {
                continue;
            }
            // If there is a change in a key from base, which is only present in ours, or only present in theirs, this is fine
            // If the same change from base is made in ours and theirs, this is also fine
            // But if a different change from base was made in ours in respect to theirs, we cannot fix this automatically
            if (array_key_exists($key, $this->base)) {
                // If key exists in base, check if changes in both files are different
                $oursChanged = array_key_exists($key, $this->ours) && $this->ours[$key] !== $this->base[$key];
                $theirsChanged = array_key_exists($key, $this->theirs) && $this->theirs[$key] !== $this->base[$key];

                if ($oursChanged && $theirsChanged && $this->ours[$key] !== $this->theirs[$key]) {
                    return false;
                }
            } else {
                // If the key is new and exists in both files, they must be equal
                if (array_key_exists($key, $this->ours) && array_key_exists($key, $this->theirs)
                    && $this->ours[$key] !== $this->theirs[$key]) {
                    return false;
                }
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
     * Generate a composer.json with all keys merged from theirs and ours, except for the require and require-dev keys.
     * The require and require-dev keys are set to the $packageSetBase version
     *
     * @throws \JsonException
     */
    public function generateComposerJsonWithPackageSetBase(ResolutionChoice $packageSetBase): string
    {
        $composerJson = $this->base;

        if ($packageSetBase === ResolutionChoice::OURS) {
            if (array_key_exists('require', $this->ours)) {
                $composerJson['require'] = $this->ours['require'];
            } else {
                unset($composerJson['require']);
            }
            if (array_key_exists('require-dev', $this->ours)) {
                $composerJson['require-dev'] = $this->ours['require-dev'];
            } else {
                unset($composerJson['require-dev']);
            }
        } else {
            if (array_key_exists('require', $this->theirs)) {
                $composerJson['require'] = $this->theirs['require'];
            } else {
                unset($composerJson['require']);
            }
            if (array_key_exists('require-dev', $this->theirs)) {
                $composerJson['require-dev'] = $this->theirs['require-dev'];
            } else {
                unset($composerJson['require-dev']);
            }
        }

        foreach (array_merge(array_keys($this->ours), array_keys($this->theirs)) as $key) {
            if (in_array($key, ['require', 'require-dev'])) {
                continue;
            }
            // We already know that any changes in the keys are only done in one of the ours/theirs versions
            // So we can merge by taking the new value every time
            if (array_key_exists($key, $this->ours) && $composerJson[$key] !== $this->ours[$key]) {
                $composerJson[$key] = $this->ours[$key];
            } elseif (array_key_exists($key, $this->theirs) && $composerJson[$key] !== $this->theirs[$key]) {
                $composerJson[$key] = $this->theirs[$key];
            }
        }

        return json_encode($composerJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
     * @return PackageDiff[]
     */
    private function getDiffsWithinArray(string $key): array
    {
        $diffs = [];
        foreach (array_unique(array_merge(array_keys($this->base[$key] ?? []), array_keys($this->ours[$key] ?? []), array_keys($this->theirs[$key] ?? []))) as $package) {
            // Check if there is any difference in the versions
            /** @var string $packageName */
            $packageName = $package;
            $baseVersion = $this->base[$key][$package] ?? null;
            $oursVersion = $this->ours[$key][$package] ?? null;
            $theirsVersion = $this->theirs[$key][$package] ?? null;
            if ($baseVersion === $oursVersion && $baseVersion === $theirsVersion) {
                continue;
            }
            $diffs[] = new PackageDiff($packageName, $baseVersion, $oursVersion, $theirsVersion, isDevDependency: $key === 'require-dev');
        }

        return $diffs;
    }
}
