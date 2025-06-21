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

    public function isInternal(): bool
    {
        if ($this->docComment === null) {
            return false;
        }
        
        // Match @internal as a standalone annotation (with optional whitespace and word boundary)
        return (bool) preg_match('/\*\s*@internal\b/', $this->docComment);
    }

    abstract public function getType(): string;
}
