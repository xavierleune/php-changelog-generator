<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Model;

class MethodElement extends ApiElement
{
    public function __construct(
        string $name,
        string $namespace,
        private array $parameters = [],
        private ?string $returnType = null,
        private bool $isStatic = false,
        private bool $isAbstract = false,
        private bool $isFinal = false,
        private string $visibility = 'public',
        ?string $docComment = null,
        private ?string $parentClass = null
    ) {
        $signature = [
            'parameters' => $parameters,
            'returnType' => $returnType,
            'isStatic' => $isStatic,
            'isAbstract' => $isAbstract,
            'isFinal' => $isFinal,
            'visibility' => $visibility,
        ];
        parent::__construct($name, $namespace, $signature, $docComment);
    }

    public function getType(): string
    {
        return 'method';
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getReturnType(): ?string
    {
        return $this->returnType;
    }

    public function isStatic(): bool
    {
        return $this->isStatic;
    }

    public function isAbstract(): bool
    {
        return $this->isAbstract;
    }

    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function getFullyQualifiedName(): string
    {
        if ($this->parentClass !== null) {
            return $this->namespace . '\\' . $this->parentClass . '::' . $this->name;
        }
        
        return parent::getFullyQualifiedName();
    }

    public function setParentClass(string $parentClass): void
    {
        $this->parentClass = $parentClass;
    }
}
