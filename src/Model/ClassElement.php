<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Model;

class ClassElement extends ApiElement
{
    public function __construct(
        string $name,
        string $namespace,
        private array $methods = [],
        private array $constants = [],
        private bool $isAbstract = false,
        private bool $isFinal = false,
        private ?string $extends = null,
        private array $implements = [],
        ?string $docComment = null
    ) {
        parent::__construct($name, $namespace, [], $docComment);
    }

    public function getType(): string
    {
        return 'class';
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function addMethod(MethodElement $method): void
    {
        $this->methods[$method->getName()] = $method;
    }

    public function getConstants(): array
    {
        return $this->constants;
    }

    public function addConstant(ConstantElement $constant): void
    {
        $this->constants[$constant->getName()] = $constant;
    }

    public function isAbstract(): bool
    {
        return $this->isAbstract;
    }

    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    public function getExtends(): ?string
    {
        return $this->extends;
    }

    public function getImplements(): array
    {
        return $this->implements;
    }
}
