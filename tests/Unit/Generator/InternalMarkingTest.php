<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Generator\ChangelogGenerator;
use Leune\ChangelogGenerator\Model\ApiChange;
use Leune\ChangelogGenerator\Model\ClassElement;
use Leune\ChangelogGenerator\Model\MethodElement;

class InternalMarkingTest extends TestCase
{
    private ChangelogGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ChangelogGenerator();
    }

    public function testInternalElementsAreMarkedInChangelog(): void
    {
        $internalClass = new ClassElement(
            'InternalClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** @internal Internal test class */'
        );

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $internalClass
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringContainsString('*@internal*', $changelog);
        $this->assertStringContainsString('Test\Namespace\InternalClass', $changelog);
    }

    public function testPublicElementsNotMarkedAsInternal(): void
    {
        $publicClass = new ClassElement(
            'PublicClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** Public test class */'
        );

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $publicClass
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringNotContainsString('*@internal*', $changelog);
        $this->assertStringContainsString('Test\Namespace\PublicClass', $changelog);
    }

    public function testInternalAnnotationChangeDescriptions(): void
    {
        $oldMethod = new MethodElement(
            'testMethod',
            'Test\\Namespace',
            [],
            'void',
            false,
            false,
            false,
            'public',
            '/** Public method */'
        );

        $newMethod = new MethodElement(
            'testMethod',
            'Test\\Namespace',
            [],
            'void',
            false,
            false,
            false,
            'public',
            '/** @internal Now internal method */'
        );

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringContainsString('*@internal*', $changelog);
        $this->assertStringContainsString('Marked as @internal', $changelog);
    }

    public function testRemovingInternalAnnotationDescription(): void
    {
        $oldMethod = new MethodElement(
            'testMethod',
            'Test\\Namespace',
            [],
            'void',
            false,
            false,
            false,
            'public',
            '/** @internal Internal method */'
        );

        $newMethod = new MethodElement(
            'testMethod',
            'Test\\Namespace',
            [],
            'void',
            false,
            false,
            false,
            'public',
            '/** Now public method */'
        );

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_PATCH,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringNotContainsString('*@internal*', $changelog); // New method is not internal
        $this->assertStringContainsString('No longer @internal', $changelog);
    }

    public function testMixedInternalAndPublicChanges(): void
    {
        $internalClass = new ClassElement(
            'InternalClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** @internal Internal class */'
        );

        $publicClass = new ClassElement(
            'PublicClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** Public class */'
        );

        $changes = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, $internalClass),
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, $publicClass)
        ];

        $changelog = $this->generator->generate($changes, '1.1.0');

        // Should contain both, but only internal one should be marked
        $this->assertStringContainsString('InternalClass` *@internal*', $changelog);
        $this->assertStringContainsString('PublicClass`:', $changelog);
        $this->assertStringNotContainsString('PublicClass` *@internal*', $changelog);
    }
}