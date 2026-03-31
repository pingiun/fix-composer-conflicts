<?php

declare(strict_types=1);

namespace Pingiun\FixConflicts;

class ConflictingComposerLock {

    /**
     * @var array{'packages': array{'name': string, 'version': string}[]}
     */
    private array $ours;
    /**
     * @var array<string, string>
     */
    private array $oursMapping;

    /**
     * @var array{'packages': array{'name': string, 'version': string}[]}
     */
    private array $theirs;
    /**
     * @var array<string, string>
     */
    private array $theirsMapping;

    /**
     * @var array{'packages': array{'name': string, 'version': string}[]}
     */
    private array $base;

    /**
     * @var array<string, string>
     */
    private array $baseMapping;

    public function __construct(
        string $base,
        string $ours,
        string $theirs,
    ) {
        $this->base = json_decode($base, true, flags: JSON_THROW_ON_ERROR);
        $this->baseMapping = self::lockfileToMapping($this->base);
        $this->ours = json_decode($ours, true, flags: JSON_THROW_ON_ERROR);
        $this->oursMapping = self::lockfileToMapping($this->ours);
        $this->theirs = json_decode($theirs, true, flags: JSON_THROW_ON_ERROR);
        $this->theirsMapping = self::lockfileToMapping($this->theirs);
    }

    /**
     * @param array{'packages': array{'name': string, 'version': string}[]} $lock
     * @return array<string, string>
     */
    private static function lockfileToMapping(array $lock): array
    {
        $mapping = [];
        foreach ($lock['packages'] as $package) {
            $mapping[$package['name']] = $package['version'];
        }
        return $mapping;
    }

    /**
     * @param array{'packages': array{'name': string, 'version': string}[]} $lock
     * @return array<string, array{'name': string, 'base': string|null, 'ours': string|null, 'theirs': string|null, 'result': string|null}>
     */
    public function diffWithResultingLock(array $lock): array
    {
        $diffInfo = [];
        $mapping = self::lockfileToMapping($lock);
        foreach (array_merge(array_keys($this->oursMapping), array_keys($this->theirsMapping), array_keys($this->baseMapping), array_keys($mapping)) as $name) {
            $diffInfo[$name] = [
                'name' => $name,
                'base' => $this->baseMapping[$name] ?? null,
                'ours' => $this->oursMapping[$name] ?? null,
                'theirs' => $this->theirsMapping[$name] ?? null,
                'result' => $mapping[$name] ?? null,
            ];
        }
        return $diffInfo;
    }
}