<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Differ;

use Leune\ChangelogGenerator\Model\ApiChange;
use Leune\ChangelogGenerator\Model\ApiElement;
use Leune\ChangelogGenerator\Model\ApiSnapshot;
use Leune\ChangelogGenerator\Model\ClassElement;
use Leune\ChangelogGenerator\Model\InterfaceElement;

class ApiDiffer
{
    public function diff(ApiSnapshot $oldSnapshot, ApiSnapshot $newSnapshot): array
    {
        $changes = [];

        $changes = array_merge($changes, $this->diffClasses($oldSnapshot, $newSnapshot));
        $changes = array_merge($changes, $this->diffInterfaces($oldSnapshot, $newSnapshot));
        $changes = array_merge($changes, $this->diffFunctions($oldSnapshot, $newSnapshot));
        $changes = array_merge($changes, $this->diffConstants($oldSnapshot, $newSnapshot));

        return $changes;
    }

    private function diffClasses(ApiSnapshot $oldSnapshot, ApiSnapshot $newSnapshot): array
    {
        $changes = [];
        $oldClasses = $oldSnapshot->getClasses();
        $newClasses = $newSnapshot->getClasses();

        foreach ($newClasses as $fqn => $newClass) {
            if (!isset($oldClasses[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_ADDED,
                    ApiChange::SEVERITY_MINOR,
                    $newClass,
                    null,
                    "Added class {$fqn}"
                );
            } else {
                $changes = array_merge($changes, $this->diffClass($oldClasses[$fqn], $newClass));
            }
        }

        foreach ($oldClasses as $fqn => $oldClass) {
            if (!isset($newClasses[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_REMOVED,
                    ApiChange::SEVERITY_MAJOR,
                    $oldClass,
                    null,
                    "Removed class {$fqn}"
                );
            }
        }

        return $changes;
    }

    private function diffClass(ClassElement $oldClass, ClassElement $newClass): array
    {
        $changes = [];

        if ($this->hasClassSignatureChanged($oldClass, $newClass)) {
            $severity = $this->determineClassChangeSeverity($oldClass, $newClass);
            $changes[] = new ApiChange(
                ApiChange::TYPE_MODIFIED,
                $severity,
                $newClass,
                $oldClass,
                "Modified class {$newClass->getFullyQualifiedName()}"
            );
        }

        $changes = array_merge($changes, $this->diffMethods($oldClass, $newClass));
        $changes = array_merge($changes, $this->diffClassConstants($oldClass, $newClass));

        return $changes;
    }

    private function diffInterfaces(ApiSnapshot $oldSnapshot, ApiSnapshot $newSnapshot): array
    {
        $changes = [];
        $oldInterfaces = $oldSnapshot->getInterfaces();
        $newInterfaces = $newSnapshot->getInterfaces();

        foreach ($newInterfaces as $fqn => $newInterface) {
            if (!isset($oldInterfaces[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_ADDED,
                    ApiChange::SEVERITY_MINOR,
                    $newInterface,
                    null,
                    "Added interface {$fqn}"
                );
            } else {
                $changes = array_merge($changes, $this->diffInterface($oldInterfaces[$fqn], $newInterface));
            }
        }

        foreach ($oldInterfaces as $fqn => $oldInterface) {
            if (!isset($newInterfaces[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_REMOVED,
                    ApiChange::SEVERITY_MAJOR,
                    $oldInterface,
                    null,
                    "Removed interface {$fqn}"
                );
            }
        }

        return $changes;
    }

    private function diffInterface(InterfaceElement $oldInterface, InterfaceElement $newInterface): array
    {
        $changes = [];

        $changes = array_merge($changes, $this->diffMethods($oldInterface, $newInterface));
        $changes = array_merge($changes, $this->diffInterfaceConstants($oldInterface, $newInterface));

        return $changes;
    }

    private function diffMethods($oldContainer, $newContainer): array
    {
        $changes = [];
        $oldMethods = $oldContainer->getMethods();
        $newMethods = $newContainer->getMethods();

        foreach ($newMethods as $name => $newMethod) {
            if (!isset($oldMethods[$name])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_ADDED,
                    ApiChange::SEVERITY_MINOR,
                    $newMethod,
                    null,
                    "Added method {$newContainer->getFullyQualifiedName()}::{$name}"
                );
            } else {
                $methodChanges = $this->diffMethod($oldMethods[$name], $newMethod);
                $changes = array_merge($changes, $methodChanges);
            }
        }

        foreach ($oldMethods as $name => $oldMethod) {
            if (!isset($newMethods[$name])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_REMOVED,
                    ApiChange::SEVERITY_MAJOR,
                    $oldMethod,
                    null,
                    "Removed method {$oldContainer->getFullyQualifiedName()}::{$name}"
                );
            }
        }

        return $changes;
    }

    private function diffMethod($oldMethod, $newMethod): array
    {
        $changes = [];

        if ($this->hasMethodSignatureChanged($oldMethod, $newMethod)) {
            $severity = $this->determineMethodChangeSeverity($oldMethod, $newMethod);
            $changes[] = new ApiChange(
                ApiChange::TYPE_MODIFIED,
                $severity,
                $newMethod,
                $oldMethod,
                "Modified method {$newMethod->getNamespace()}\\{$newMethod->getName()}"
            );
        }

        return $changes;
    }

    private function diffFunctions(ApiSnapshot $oldSnapshot, ApiSnapshot $newSnapshot): array
    {
        $changes = [];
        $oldFunctions = $oldSnapshot->getFunctions();
        $newFunctions = $newSnapshot->getFunctions();

        foreach ($newFunctions as $fqn => $newFunction) {
            if (!isset($oldFunctions[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_ADDED,
                    ApiChange::SEVERITY_MINOR,
                    $newFunction,
                    null,
                    "Added function {$fqn}"
                );
            } else {
                $functionChanges = $this->diffFunction($oldFunctions[$fqn], $newFunction);
                $changes = array_merge($changes, $functionChanges);
            }
        }

        foreach ($oldFunctions as $fqn => $oldFunction) {
            if (!isset($newFunctions[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_REMOVED,
                    ApiChange::SEVERITY_MAJOR,
                    $oldFunction,
                    null,
                    "Removed function {$fqn}"
                );
            }
        }

        return $changes;
    }

    private function diffFunction($oldFunction, $newFunction): array
    {
        $changes = [];

        if ($this->hasFunctionSignatureChanged($oldFunction, $newFunction)) {
            $severity = $this->determineFunctionChangeSeverity($oldFunction, $newFunction);
            $changes[] = new ApiChange(
                ApiChange::TYPE_MODIFIED,
                $severity,
                $newFunction,
                $oldFunction,
                "Modified function {$newFunction->getFullyQualifiedName()}"
            );
        }

        return $changes;
    }

    private function diffConstants(ApiSnapshot $oldSnapshot, ApiSnapshot $newSnapshot): array
    {
        $changes = [];
        $oldConstants = $oldSnapshot->getConstants();
        $newConstants = $newSnapshot->getConstants();

        foreach ($newConstants as $fqn => $newConstant) {
            if (!isset($oldConstants[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_ADDED,
                    ApiChange::SEVERITY_MINOR,
                    $newConstant,
                    null,
                    "Added constant {$fqn}"
                );
            } else {
                $constantChanges = $this->diffConstant($oldConstants[$fqn], $newConstant);
                $changes = array_merge($changes, $constantChanges);
            }
        }

        foreach ($oldConstants as $fqn => $oldConstant) {
            if (!isset($newConstants[$fqn])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_REMOVED,
                    ApiChange::SEVERITY_MAJOR,
                    $oldConstant,
                    null,
                    "Removed constant {$fqn}"
                );
            }
        }

        return $changes;
    }

    private function diffConstant($oldConstant, $newConstant): array
    {
        $changes = [];

        if ($oldConstant->getValue() !== $newConstant->getValue()) {
            $changes[] = new ApiChange(
                ApiChange::TYPE_MODIFIED,
                ApiChange::SEVERITY_MAJOR,
                $newConstant,
                $oldConstant,
                "Modified constant {$newConstant->getFullyQualifiedName()}"
            );
        }

        return $changes;
    }

    private function diffClassConstants($oldContainer, $newContainer): array
    {
        $changes = [];
        $oldConstants = $oldContainer->getConstants();
        $newConstants = $newContainer->getConstants();

        foreach ($newConstants as $name => $newConstant) {
            if (!isset($oldConstants[$name])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_ADDED,
                    ApiChange::SEVERITY_MINOR,
                    $newConstant,
                    null,
                    "Added constant {$newContainer->getFullyQualifiedName()}::{$name}"
                );
            } else {
                $constantChanges = $this->diffConstant($oldConstants[$name], $newConstant);
                $changes = array_merge($changes, $constantChanges);
            }
        }

        foreach ($oldConstants as $name => $oldConstant) {
            if (!isset($newConstants[$name])) {
                $changes[] = new ApiChange(
                    ApiChange::TYPE_REMOVED,
                    ApiChange::SEVERITY_MAJOR,
                    $oldConstant,
                    null,
                    "Removed constant {$oldContainer->getFullyQualifiedName()}::{$name}"
                );
            }
        }

        return $changes;
    }

    private function diffInterfaceConstants($oldContainer, $newContainer): array
    {
        return $this->diffClassConstants($oldContainer, $newContainer);
    }

    private function hasClassSignatureChanged(ClassElement $oldClass, ClassElement $newClass): bool
    {
        return $oldClass->isAbstract() !== $newClass->isAbstract()
            || $oldClass->isFinal() !== $newClass->isFinal()
            || $oldClass->getExtends() !== $newClass->getExtends()
            || $oldClass->getImplements() !== $newClass->getImplements();
    }

    private function hasMethodSignatureChanged($oldMethod, $newMethod): bool
    {
        return $this->getEffectiveSignature($oldMethod) !== $this->getEffectiveSignature($newMethod);
    }

    private function hasFunctionSignatureChanged($oldFunction, $newFunction): bool
    {
        return $this->getEffectiveSignature($oldFunction) !== $this->getEffectiveSignature($newFunction);
    }

    private function getEffectiveSignature(ApiElement $element): array
    {
        $signature = [];

        if (method_exists($element, 'getParameters')) {
            $signature['parameters'] = $this->normalizeParameters($element->getParameters());
        }

        if (method_exists($element, 'getReturnType')) {
            $signature['returnType'] = $element->getReturnType();
        }

        if (method_exists($element, 'getVisibility')) {
            $signature['visibility'] = $element->getVisibility();
        }

        if (method_exists($element, 'isStatic')) {
            $signature['isStatic'] = $element->isStatic();
        }

        if (method_exists($element, 'isAbstract')) {
            $signature['isAbstract'] = $element->isAbstract();
        }

        if (method_exists($element, 'isFinal')) {
            $signature['isFinal'] = $element->isFinal();
        }

        return $signature;
    }

    private function normalizeParameters(array $parameters): array
    {
        return array_map(function ($param) {
            return [
                'type' => $param['type'] ?? null,
                'hasDefault' => isset($param['defaultValue']),
                'isVariadic' => $param['isVariadic'] ?? false,
                'byRef' => $param['byRef'] ?? false,
            ];
        }, $parameters);
    }

    private function determineClassChangeSeverity(ClassElement $oldClass, ClassElement $newClass): string
    {
        if ($oldClass->isFinal() !== $newClass->isFinal()
            || $oldClass->isAbstract() !== $newClass->isAbstract()) {
            return ApiChange::SEVERITY_MAJOR;
        }

        // Changing parent class is major, but adding one is minor
        $oldExtends = $oldClass->getExtends();
        $newExtends = $newClass->getExtends();
        if ($oldExtends !== $newExtends) {
            if ($oldExtends !== null && $newExtends !== null) {
                // Changing from one parent to another is major
                return ApiChange::SEVERITY_MAJOR;
            } elseif ($oldExtends !== null && $newExtends === null) {
                // Removing parent is major
                return ApiChange::SEVERITY_MAJOR;
            }
            // Adding parent (null -> something) is minor
        }

        if ($oldClass->getImplements() !== $newClass->getImplements()) {
            return ApiChange::SEVERITY_MINOR;
        }

        // If we reach here and there were inheritance changes, it's minor
        if ($oldExtends !== $newExtends) {
            return ApiChange::SEVERITY_MINOR;
        }

        return ApiChange::SEVERITY_PATCH;
    }

    private function determineMethodChangeSeverity($oldMethod, $newMethod): string
    {
        $oldParams = $this->normalizeParameters($oldMethod->getParameters());
        $newParams = $this->normalizeParameters($newMethod->getParameters());

        if ($this->isParameterChangeBreaking($oldParams, $newParams)) {
            return ApiChange::SEVERITY_MAJOR;
        }

        if ($oldMethod->getReturnType() !== $newMethod->getReturnType()) {
            if ($this->isReturnTypeChangeBreaking($oldMethod->getReturnType(), $newMethod->getReturnType())) {
                return ApiChange::SEVERITY_MAJOR;
            }
            return ApiChange::SEVERITY_MINOR;
        }

        // Check for breaking modifier changes
        if (method_exists($oldMethod, 'isStatic') && method_exists($newMethod, 'isStatic')) {
            if ($oldMethod->isStatic() !== $newMethod->isStatic()) {
                return ApiChange::SEVERITY_MAJOR;
            }
        }

        if (method_exists($oldMethod, 'isAbstract') && method_exists($newMethod, 'isAbstract')) {
            if ($oldMethod->isAbstract() !== $newMethod->isAbstract()) {
                return ApiChange::SEVERITY_MAJOR;
            }
        }

        if (method_exists($oldMethod, 'isFinal') && method_exists($newMethod, 'isFinal')) {
            if ($oldMethod->isFinal() !== $newMethod->isFinal()) {
                return ApiChange::SEVERITY_MAJOR;
            }
        }

        if (method_exists($oldMethod, 'getVisibility') && method_exists($newMethod, 'getVisibility')) {
            if ($oldMethod->getVisibility() !== $newMethod->getVisibility()) {
                return ApiChange::SEVERITY_MINOR;
            }
        }

        return ApiChange::SEVERITY_PATCH;
    }

    private function determineFunctionChangeSeverity($oldFunction, $newFunction): string
    {
        return $this->determineMethodChangeSeverity($oldFunction, $newFunction);
    }

    private function isParameterChangeBreaking(array $oldParams, array $newParams): bool
    {
        $oldRequiredCount = count(array_filter($oldParams, fn($p) => !$p['hasDefault']));
        $newRequiredCount = count(array_filter($newParams, fn($p) => !$p['hasDefault']));

        if ($newRequiredCount > $oldRequiredCount) {
            return true;
        }

        for ($i = 0; $i < min(count($oldParams), count($newParams)); $i++) {
            if ($oldParams[$i]['type'] !== $newParams[$i]['type']
                || $oldParams[$i]['byRef'] !== $newParams[$i]['byRef']) {
                return true;
            }
        }

        return false;
    }

    private function isReturnTypeChangeBreaking(?string $oldType, ?string $newType): bool
    {
        if ($oldType === null && $newType !== null) {
            return true;
        }

        if ($oldType !== null && $newType === null) {
            return true;
        }

        return $oldType !== $newType;
    }
}
