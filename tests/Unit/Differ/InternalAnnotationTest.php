<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Unit\Differ;

use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Differ\ApiDiffer;
use Leune\ChangelogGenerator\Model\ApiChange;
use Leune\ChangelogGenerator\Model\ApiSnapshot;
use Leune\ChangelogGenerator\Model\ClassElement;
use Leune\ChangelogGenerator\Model\MethodElement;

class InternalAnnotationTest extends TestCase
{
    private ApiDiffer $differ;

    protected function setUp(): void
    {
        $this->differ = new ApiDiffer();
    }

    public function testAddingInternalAnnotationIsMajorChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** Public class */'
        );

        $newClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** @internal This is now internal */'
        );

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        $this->assertCount(1, $changes);
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $changes[0]->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $changes[0]->getSeverity());
    }

    public function testRemovingInternalAnnotationIsPatch(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** @internal Internal class */'
        );

        $newClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** Public class now */'
        );

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        $this->assertCount(1, $changes);
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $changes[0]->getType());
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $changes[0]->getSeverity());
    }

    public function testInternalClassRemovalIsMinorNotMajor(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $internalClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** @internal Internal class */'
        );

        $oldSnapshot->addClass($internalClass);
        // newSnapshot is empty (class removed)

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        $this->assertCount(1, $changes);
        $this->assertEquals(ApiChange::TYPE_REMOVED, $changes[0]->getType());
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $changes[0]->getSeverity());
    }

    public function testInternalMethodChangesNeverMajor(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldMethod = new MethodElement(
            'testMethod',
            'Test\\Namespace',
            [['name' => 'param1', 'type' => 'string']],
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
            [['name' => 'param1', 'type' => 'int'], ['name' => 'param2', 'type' => 'string']], // Breaking change
            'string', // Breaking change
            false,
            false,
            false,
            'public',
            '/** @internal Internal method */'
        );

        $oldClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** Test class */'
        );

        $newClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** Test class */'
        );

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Should have method change, but not major
        $methodChanges = array_filter($changes, fn($change) => 
            $change->getElement()->getName() === 'testMethod'
        );

        $this->assertCount(1, $methodChanges);
        $methodChange = array_values($methodChanges)[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $methodChange->getType());
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $methodChange->getSeverity());
    }

    public function testPublicClassWithInternalMethodBreakingChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldMethod = new MethodElement(
            'testMethod',
            'Test\\Namespace',
            [['name' => 'param1', 'type' => 'string']],
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
            [['name' => 'param1', 'type' => 'int']], // Breaking change
            'void',
            false,
            false,
            false,
            'public',
            '/** @internal Internal method */'
        );

        $oldClass = new ClassElement('TestClass', 'Test\\Namespace');
        $newClass = new ClassElement('TestClass', 'Test\\Namespace');

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Should not be major because method is @internal
        $methodChanges = array_filter($changes, fn($change) => 
            $change->getElement()->getName() === 'testMethod'
        );

        $this->assertCount(1, $methodChanges);
        $methodChange = array_values($methodChanges)[0];
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $methodChange->getSeverity());
    }

    public function testOldInternalToNewPublicMethodChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldMethod = new MethodElement(
            'testMethod',
            'Test\\Namespace',
            [['name' => 'param1', 'type' => 'string']],
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
            [['name' => 'param1', 'type' => 'int']], // Breaking change but method becomes public
            'void',
            false,
            false,
            false,
            'public',
            '/** Public method now */'
        );

        $oldClass = new ClassElement('TestClass', 'Test\\Namespace');
        $newClass = new ClassElement('TestClass', 'Test\\Namespace');

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Should be patch because @internal was removed (always patch regardless of other changes)
        $methodChanges = array_filter($changes, fn($change) => 
            $change->getElement()->getName() === 'testMethod'
        );

        $this->assertCount(1, $methodChanges);
        $methodChange = array_values($methodChanges)[0];
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $methodChange->getSeverity());
    }
}