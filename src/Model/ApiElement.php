<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Model;

abstract class ApiElement
{
    public function __construct(
        protected string $name,
        protected string $namespace,
        protected array $signature = [],
        protected ?string $docComment = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getFullyQualifiedName(): string
    {
        return $this->namespace . '\\' . $this->name;
    }

    public function getSignature(): array
    {
        return $this->signature;
    }

    public function getDocComment(): ?string
    {
        return $this->docComment;
    }

    abstract public function getType(): string;
}
