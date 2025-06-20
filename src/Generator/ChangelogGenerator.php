<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Generator;

use Leune\ChangelogGenerator\Model\ApiChange;

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
        $description = $this->generateDescription($change);

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

        // Analyze parameter changes
        if (method_exists($element, 'getParameters') && method_exists($oldElement, 'getParameters')) {
            $paramChanges = $this->analyzeParameterChanges($oldElement->getParameters(), $element->getParameters());
            $details = array_merge($details, $paramChanges);
        }

        // Analyze return type changes
        if (method_exists($element, 'getReturnType') && method_exists($oldElement, 'getReturnType')) {
            $returnChange = $this->analyzeReturnTypeChange($oldElement->getReturnType(), $element->getReturnType());
            if ($returnChange) {
                $details[] = $returnChange;
            }
        }

        // Analyze visibility changes
        if (method_exists($element, 'getVisibility') && method_exists($oldElement, 'getVisibility')) {
            if ($oldElement->getVisibility() !== $element->getVisibility()) {
                $details[] = "visibility changed from {$oldElement->getVisibility()} to {$element->getVisibility()}";
            }
        }

        // Analyze static modifier changes
        if (method_exists($element, 'isStatic') && method_exists($oldElement, 'isStatic')) {
            if ($oldElement->isStatic() !== $element->isStatic()) {
                $staticChange = $element->isStatic() ? 'became static' : 'no longer static';
                $details[] = $staticChange;
            }
        }

        // Analyze abstract modifier changes
        if (method_exists($element, 'isAbstract') && method_exists($oldElement, 'isAbstract')) {
            if ($oldElement->isAbstract() !== $element->isAbstract()) {
                $abstractChange = $element->isAbstract() ? 'became abstract' : 'no longer abstract';
                $details[] = $abstractChange;
            }
        }

        // Analyze final modifier changes
        if (method_exists($element, 'isFinal') && method_exists($oldElement, 'isFinal')) {
            if ($oldElement->isFinal() !== $element->isFinal()) {
                $finalChange = $element->isFinal() ? 'became final' : 'no longer final';
                $details[] = $finalChange;
            }
        }

        // For classes, analyze inheritance changes
        if ($type === 'class') {
            $inheritanceChanges = $this->analyzeClassInheritanceChanges($oldElement, $element);
            $details = array_merge($details, $inheritanceChanges);
        }

        // For constants, analyze value changes
        if ($type === 'constant') {
            $valueChange = $this->analyzeConstantValueChange($oldElement, $element);
            if ($valueChange) {
                $details[] = $valueChange;
            }
        }

        if (empty($details)) {
            return "{$type} signature modified";
        }

        return ucfirst(implode(', ', $details));
    }

    private function analyzeParameterChanges(array $oldParams, array $newParams): array
    {
        $changes = [];
        $oldCount = count($oldParams);
        $newCount = count($newParams);

        if ($oldCount !== $newCount) {
            if ($newCount > $oldCount) {
                $added = $newCount - $oldCount;
                $changes[] = "added {$added} parameter" . ($added > 1 ? 's' : '');
            } else {
                $removed = $oldCount - $newCount;
                $changes[] = "removed {$removed} parameter" . ($removed > 1 ? 's' : '');
            }
        }

        // Analyze type changes for existing parameters
        $minCount = min($oldCount, $newCount);
        for ($i = 0; $i < $minCount; $i++) {
            $oldParam = $oldParams[$i];
            $newParam = $newParams[$i];

            if (($oldParam['type'] ?? null) !== ($newParam['type'] ?? null)) {
                $paramName = $newParam['name'] ?? "param{$i}";
                $oldType = $oldParam['type'] ?? 'mixed';
                $newType = $newParam['type'] ?? 'mixed';
                $changes[] = "parameter \${$paramName} type changed from {$oldType} to {$newType}";
            }

            // Check if parameter became optional/required
            $oldHasDefault = isset($oldParam['defaultValue']);
            $newHasDefault = isset($newParam['defaultValue']);
            
            if ($oldHasDefault !== $newHasDefault) {
                $paramName = $newParam['name'] ?? "param{$i}";
                if ($newHasDefault) {
                    $changes[] = "parameter \${$paramName} became optional";
                } else {
                    $changes[] = "parameter \${$paramName} became required";
                }
            }
        }

        return $changes;
    }

    private function analyzeReturnTypeChange(?string $oldType, ?string $newType): ?string
    {
        if ($oldType === $newType) {
            return null;
        }

        $oldTypeStr = $oldType ?? 'mixed';
        $newTypeStr = $newType ?? 'mixed';

        return "return type changed from {$oldTypeStr} to {$newTypeStr}";
    }

    private function analyzeClassInheritanceChanges($oldClass, $newClass): array
    {
        $changes = [];

        if (method_exists($oldClass, 'getExtends') && method_exists($newClass, 'getExtends')) {
            $oldExtends = $oldClass->getExtends();
            $newExtends = $newClass->getExtends();

            if ($oldExtends !== $newExtends) {
                if ($oldExtends === null && $newExtends !== null) {
                    $changes[] = "now extends {$newExtends}";
                } elseif ($oldExtends !== null && $newExtends === null) {
                    $changes[] = "no longer extends {$oldExtends}";
                } elseif ($oldExtends !== null && $newExtends !== null) {
                    $changes[] = "extends changed from {$oldExtends} to {$newExtends}";
                }
            }
        }

        if (method_exists($oldClass, 'getImplements') && method_exists($newClass, 'getImplements')) {
            $oldImplements = $oldClass->getImplements();
            $newImplements = $newClass->getImplements();

            $added = array_diff($newImplements, $oldImplements);
            $removed = array_diff($oldImplements, $newImplements);

            if (!empty($added)) {
                $changes[] = "now implements " . implode(', ', $added);
            }
            if (!empty($removed)) {
                $changes[] = "no longer implements " . implode(', ', $removed);
            }
        }

        return $changes;
    }

    private function analyzeConstantValueChange($oldConstant, $newConstant): ?string
    {
        if (method_exists($oldConstant, 'getValue') && method_exists($newConstant, 'getValue')) {
            $oldValue = $oldConstant->getValue();
            $newValue = $newConstant->getValue();

            if ($oldValue !== $newValue) {
                $oldValueStr = is_string($oldValue) ? "'{$oldValue}'" : (string) $oldValue;
                $newValueStr = is_string($newValue) ? "'{$newValue}'" : (string) $newValue;
                return "value changed from {$oldValueStr} to {$newValueStr}";
            }
        }

        return null;
    }
}
