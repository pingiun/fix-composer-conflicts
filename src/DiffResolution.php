<?php

namespace Pingiun\FixConflicts;

use Composer\Semver\VersionParser;

final readonly class DiffResolution
{
    public function __construct(
        public PackageDiff $diff,
        public ResolutionChoice $choice,
        public ?string $newVersion = null,
        public bool $isDevDependency = false,
    ) {}

    public function getAction(): string
    {
        return match ($this->choice) {
            ResolutionChoice::OURS, ResolutionChoice::BASE, ResolutionChoice::THEIRS, ResolutionChoice::NEWEST, ResolutionChoice::MANUAL => 'require',
            ResolutionChoice::REMOVE => 'remove',
            ResolutionChoice::ABORT => throw new \LogicException('Cannot resolve diff with abort choice'),
        };
    }

    public function getPackage(): string
    {
        return $this->diff->name;
    }

    public function getVersion(): ?string
    {
        return match ($this->choice) {
            ResolutionChoice::OURS => $this->diff->oursVersion,
            ResolutionChoice::BASE => $this->diff->baseVersion,
            ResolutionChoice::THEIRS => $this->diff->theirsVersion,
            ResolutionChoice::NEWEST => self::getNewestVersion([$this->diff->oursVersion, $this->diff->baseVersion, $this->diff->theirsVersion]),
            ResolutionChoice::MANUAL => $this->newVersion,
            ResolutionChoice::REMOVE, ResolutionChoice::ABORT => throw new \LogicException('Cannot resolve diff with remove or ¬abort choice'),
        };
    }

    /**
     * @param  array<?string>  $array
     */
    private static function getNewestVersion(array $array): string
    {
        // Return the version with the highest minimum bound
        $versionParser = new VersionParser;
        $highestVersion = null;
        foreach ($array as $version) {
            if ($version === null) {
                continue;
            }
            $parsedVersion = $versionParser->parseConstraints($version);
            if ($highestVersion === null || $parsedVersion->getLowerBound() > $highestVersion->getLowerBound()) {
                $highestVersion = $parsedVersion;
            }
        }

        return $highestVersion->getPrettyString();
    }
}
