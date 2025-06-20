<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Model;

class ConstantElement extends ApiElement
{
    public function __construct(
        string $name,
        string $namespace,
        private mixed $value = null,
        private ?string $type = null,
        ?string $docComment = null
    ) {
        $signature = [
            'value' => $value,
            'type' => $type,
        ];
        parent::__construct($name, $namespace, $signature, $docComment);
    }

    public function getType(): string
    {
        return 'constant';
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getValueType(): ?string
    {
        return $this->type;
    }
}
