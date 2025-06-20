<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Model;

class ApiChange
{
    public const TYPE_ADDED = 'added';
    public const TYPE_REMOVED = 'removed';
    public const TYPE_MODIFIED = 'modified';

    public const SEVERITY_PATCH = 'patch';
    public const SEVERITY_MINOR = 'minor';
    public const SEVERITY_MAJOR = 'major';

    public function __construct(
        private string $type,
        private string $severity,
        private ApiElement $element,
        private ?ApiElement $oldElement = null,
        private string $description = '',
        private array $details = []
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getElement(): ApiElement
    {
        return $this->element;
    }

    public function getOldElement(): ?ApiElement
    {
        return $this->oldElement;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function addDetail(string $key, mixed $value): void
    {
        $this->details[$key] = $value;
    }
}
