<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Model;

class ApiSnapshot
{
    private array $classes = [];
    private array $interfaces = [];
    private array $functions = [];
    private array $constants = [];
    private array $fileChecksums = [];

    public function addClass(ClassElement $class): void
    {
        $this->classes[$class->getFullyQualifiedName()] = $class;
    }

    public function addInterface(InterfaceElement $interface): void
    {
        $this->interfaces[$interface->getFullyQualifiedName()] = $interface;
    }

    public function addFunction(FunctionElement $function): void
    {
        $this->functions[$function->getFullyQualifiedName()] = $function;
    }

    public function addConstant(ConstantElement $constant): void
    {
        $this->constants[$constant->getFullyQualifiedName()] = $constant;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function getConstants(): array
    {
        return $this->constants;
    }

    public function getAllElements(): array
    {
        return array_merge(
            $this->classes,
            $this->interfaces,
            $this->functions,
            $this->constants
        );
    }

    public function addFileChecksum(string $relativePath, string $checksum): void
    {
        $this->fileChecksums[$relativePath] = $checksum;
    }

    public function getFileChecksums(): array
    {
        return $this->fileChecksums;
    }
}
