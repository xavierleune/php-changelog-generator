<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Model;

class FunctionElement extends ApiElement
{
    public function __construct(
        string $name,
        string $namespace,
        private array $parameters = [],
        private ?string $returnType = null,
        ?string $docComment = null
    ) {
        $signature = [
            'parameters' => $parameters,
            'returnType' => $returnType,
        ];
        parent::__construct($name, $namespace, $signature, $docComment);
    }

    public function getType(): string
    {
        return 'function';
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getReturnType(): ?string
    {
        return $this->returnType;
    }
}
