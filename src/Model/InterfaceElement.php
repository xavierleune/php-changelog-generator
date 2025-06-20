<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Model;

class InterfaceElement extends ApiElement
{
    public function __construct(
        string $name,
        string $namespace,
        private array $methods = [],
        private array $constants = [],
        private array $extends = [],
        ?string $docComment = null
    ) {
        parent::__construct($name, $namespace, [], $docComment);
    }

    public function getType(): string
    {
        return 'interface';
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

    public function getExtends(): array
    {
        return $this->extends;
    }
}
