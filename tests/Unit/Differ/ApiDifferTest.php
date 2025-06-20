<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Tests\Unit\Differ;

use PHPUnit\Framework\TestCase;
use XLeune\ChangelogGenerator\Differ\ApiDiffer;
use XLeune\ChangelogGenerator\Model\ApiChange;
use XLeune\ChangelogGenerator\Model\ApiSnapshot;
use XLeune\ChangelogGenerator\Model\ClassElement;
use XLeune\ChangelogGenerator\Model\MethodElement;
use XLeune\ChangelogGenerator\Model\FunctionElement;
use XLeune\ChangelogGenerator\Model\ConstantElement;

class ApiDifferTest extends TestCase
{
    private ApiDiffer $differ;

    protected function setUp(): void
    {
        $this->differ = new ApiDiffer();
    }

    public function testNoChanges(): void
    {
        $snapshot1 = new ApiSnapshot();
        $snapshot2 = new ApiSnapshot();

        $class = new ClassElement('TestClass', 'Test');
        $snapshot1->addClass($class);
        $snapshot2->addClass($class);

        $changes = $this->differ->diff($snapshot1, $snapshot2);
        $this->assertEmpty($changes);
    }

    public function testAddedClass(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $newClass = new ClassElement('NewClass', 'Test');
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_ADDED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $change->getSeverity());
        $this->assertEquals('NewClass', $change->getElement()->getName());
    }

    public function testRemovedClass(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('OldClass', 'Test');
        $oldSnapshot->addClass($oldClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_REMOVED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
        $this->assertEquals('OldClass', $change->getElement()->getName());
    }

    public function testAddedMethod(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $newMethod = new MethodElement('newMethod', 'Test');
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_ADDED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $change->getSeverity());
        $this->assertEquals('newMethod', $change->getElement()->getName());
    }

    public function testRemovedMethod(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldMethod = new MethodElement('oldMethod', 'Test');
        $oldClass->addMethod($oldMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_REMOVED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
        $this->assertEquals('oldMethod', $change->getElement()->getName());
    }

    public function testMethodParameterTypeChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [['name' => 'param', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
    }

    public function testMethodReturnTypeChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldMethod = new MethodElement('testMethod', 'Test', [], 'string');
        $newMethod = new MethodElement('testMethod', 'Test', [], 'int');

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
    }

    public function testMethodAddOptionalParameter(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldParams = [['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [
            ['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false],
            ['name' => 'param2', 'type' => 'int', 'defaultValue' => 42, 'isVariadic' => false, 'byRef' => false]
        ];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $change->getSeverity());
    }

    public function testMethodAddRequiredParameter(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldParams = [['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [
            ['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false],
            ['name' => 'param2', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]
        ];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
    }

    public function testMethodVisibilityChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldMethod = new MethodElement('testMethod', 'Test', [], null, false, false, false, 'protected');
        $newMethod = new MethodElement('testMethod', 'Test', [], null, false, false, false, 'public');

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $change->getSeverity());
    }

    public function testMethodStaticModifierChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldMethod = new MethodElement('testMethod', 'Test', [], null, false);
        $newMethod = new MethodElement('testMethod', 'Test', [], null, true);

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
    }

    public function testClassInheritanceChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test', [], [], false, false, null, []);
        $newClass = new ClassElement('TestClass', 'Test', [], [], false, false, 'BaseClass', ['Interface1']);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $change->getSeverity());
    }

    public function testConstantValueChange(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldConstant = new ConstantElement('TEST_CONST', 'Test', 'old_value');
        $newConstant = new ConstantElement('TEST_CONST', 'Test', 'new_value');

        $oldSnapshot->addConstant($oldConstant);
        $newSnapshot->addConstant($newConstant);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
    }

    public function testParameterNameChangeIgnored(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldParams = [['name' => 'oldName', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [['name' => 'newName', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertEmpty($changes); // Parameter name changes should be ignored
    }

    public function testNullableTypeChanges(): void
    {
        $oldSnapshot = new ApiSnapshot();
        $newSnapshot = new ApiSnapshot();

        $oldClass = new ClassElement('TestClass', 'Test');
        $newClass = new ClassElement('TestClass', 'Test');

        $oldParams = [['name' => 'param', 'type' => '?string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $oldClass->addMethod($oldMethod);
        $newClass->addMethod($newMethod);

        $oldSnapshot->addClass($oldClass);
        $newSnapshot->addClass($newClass);

        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);
        $this->assertCount(1, $changes);

        $change = $changes[0];
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $change->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $change->getSeverity());
    }
}