<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Analyzer;

use Leune\ChangelogGenerator\Model\ApiChange;

class SemVerAnalyzer
{
    public function analyzeSeverity(
        array $changes,
        string $currentVersion = '1.0.0',
        bool $strictSemver = false
    ): string {
        $hasMajor = false;
        $hasMinor = false;

        foreach ($changes as $change) {
            if (!$change instanceof ApiChange) {
                continue;
            }

            switch ($change->getSeverity()) {
                case ApiChange::SEVERITY_MAJOR:
                    $hasMajor = true;
                    break;
                case ApiChange::SEVERITY_MINOR:
                    $hasMinor = true;
                    break;
            }
        }

        // Pre-1.0.0 behavior: major changes become minor unless strict mode is enabled
        if ($hasMajor) {
            if (!$strictSemver && $this->isPreRelease($currentVersion)) {
                return ApiChange::SEVERITY_MINOR;
            }
            return ApiChange::SEVERITY_MAJOR;
        }

        if ($hasMinor) {
            return ApiChange::SEVERITY_MINOR;
        }

        return ApiChange::SEVERITY_PATCH;
    }

    public function shouldBumpMajor(array $changes, string $currentVersion = '1.0.0', bool $strictSemver = false): bool
    {
        return $this->analyzeSeverity($changes, $currentVersion, $strictSemver) === ApiChange::SEVERITY_MAJOR;
    }

    public function shouldBumpMinor(array $changes, string $currentVersion = '1.0.0', bool $strictSemver = false): bool
    {
        return $this->analyzeSeverity($changes, $currentVersion, $strictSemver) === ApiChange::SEVERITY_MINOR;
    }

    public function getRecommendedVersion(string $currentVersion, array $changes, bool $strictSemver = false): string
    {
        $severity = $this->analyzeSeverity($changes, $currentVersion, $strictSemver);
        $versionParts = explode('.', $currentVersion);

        $major = (int) ($versionParts[0] ?? 0);
        $minor = (int) ($versionParts[1] ?? 0);
        $patch = (int) ($versionParts[2] ?? 0);

        switch ($severity) {
            case ApiChange::SEVERITY_MAJOR:
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case ApiChange::SEVERITY_MINOR:
                $minor++;
                $patch = 0;
                break;
            case ApiChange::SEVERITY_PATCH:
                $patch++;
                break;
        }

        return "{$major}.{$minor}.{$patch}";
    }

    private function isPreRelease(string $version): bool
    {
        $versionParts = explode('.', $version);
        $major = (int) ($versionParts[0] ?? 0);
        
        return $major === 0;
    }
}
