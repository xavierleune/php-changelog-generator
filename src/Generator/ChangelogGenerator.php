<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Generator;

use XLeune\ChangelogGenerator\Model\ApiChange;

class ChangelogGenerator
{
    public function generate(array $changes, string $version, ?string $date = null): string
    {
        $date = $date ?? date('Y-m-d');
        $changelog = "# Changelog\n\n";
        $changelog .= "## [{$version}] - {$date}\n\n";

        $grouped = $this->groupChangesByType($changes);

        if (!empty($grouped['added'])) {
            $changelog .= "### Added\n\n";
            foreach ($grouped['added'] as $change) {
                $changelog .= $this->formatChange($change);
            }
            $changelog .= "\n";
        }

        if (!empty($grouped['modified'])) {
            $changelog .= "### Changed\n\n";
            foreach ($grouped['modified'] as $change) {
                $changelog .= $this->formatChange($change);
            }
            $changelog .= "\n";
        }

        if (!empty($grouped['removed'])) {
            $changelog .= "### Removed\n\n";
            foreach ($grouped['removed'] as $change) {
                $changelog .= $this->formatChange($change);
            }
            $changelog .= "\n";
        }

        return $changelog;
    }

    private function groupChangesByType(array $changes): array
    {
        $grouped = [
            'added' => [],
            'modified' => [],
            'removed' => [],
        ];

        foreach ($changes as $change) {
            if (!$change instanceof ApiChange) {
                continue;
            }

            switch ($change->getType()) {
                case ApiChange::TYPE_ADDED:
                    $grouped['added'][] = $change;
                    break;
                case ApiChange::TYPE_MODIFIED:
                    $grouped['modified'][] = $change;
                    break;
                case ApiChange::TYPE_REMOVED:
                    $grouped['removed'][] = $change;
                    break;
            }
        }

        return $grouped;
    }

    private function formatChange(ApiChange $change): string
    {
        $element = $change->getElement();
        $type = $element->getType();
        $name = $element->getFullyQualifiedName();
        
        $severity = $this->getSeverityBadge($change->getSeverity());
        $description = $change->getDescription();

        if (empty($description)) {
            $description = $this->generateDescription($change);
        }

        return "- {$severity} **{$type}** `{$name}`: {$description}\n";
    }

    private function getSeverityBadge(string $severity): string
    {
        switch ($severity) {
            case ApiChange::SEVERITY_MAJOR:
                return '🔴';
            case ApiChange::SEVERITY_MINOR:
                return '🟡';
            case ApiChange::SEVERITY_PATCH:
                return '🟢';
            default:
                return '⚪';
        }
    }

    private function generateDescription(ApiChange $change): string
    {
        $element = $change->getElement();
        $type = $element->getType();
        $changeType = $change->getType();

        switch ($changeType) {
            case ApiChange::TYPE_ADDED:
                return "New {$type} added";
            case ApiChange::TYPE_REMOVED:
                return "{$type} removed";
            case ApiChange::TYPE_MODIFIED:
                return $this->generateModificationDescription($change);
            default:
                return "Unknown change";
        }
    }

    private function generateModificationDescription(ApiChange $change): string
    {
        $element = $change->getElement();
        $oldElement = $change->getOldElement();
        $type = $element->getType();

        if ($oldElement === null) {
            return "{$type} modified";
        }

        $details = [];

        if (method_exists($element, 'getParameters') && method_exists($oldElement, 'getParameters')) {
            $oldParams = $oldElement->getParameters();
            $newParams = $element->getParameters();
            
            if (count($oldParams) !== count($newParams)) {
                $details[] = "parameter count changed";
            }
        }

        if (method_exists($element, 'getReturnType') && method_exists($oldElement, 'getReturnType')) {
            if ($oldElement->getReturnType() !== $element->getReturnType()) {
                $details[] = "return type changed";
            }
        }

        if (method_exists($element, 'getVisibility') && method_exists($oldElement, 'getVisibility')) {
            if ($oldElement->getVisibility() !== $element->getVisibility()) {
                $details[] = "visibility changed";
            }
        }

        if (empty($details)) {
            return "{$type} signature modified";
        }

        return ucfirst(implode(', ', $details));
    }
}
