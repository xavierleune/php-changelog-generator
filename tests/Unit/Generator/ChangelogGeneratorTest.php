<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Generator\ChangelogGenerator;
use Leune\ChangelogGenerator\Model\ApiChange;
use Leune\ChangelogGenerator\Model\ClassElement;
use Leune\ChangelogGenerator\Model\MethodElement;
use Leune\ChangelogGenerator\Model\ConstantElement;

class ChangelogGeneratorTest extends TestCase
{
    private ChangelogGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ChangelogGenerator();
    }

    public function testEmptyChangelog(): void
    {
        $changelog = $this->generator->generate([], '1.0.0', '2023-01-01');

        $this->assertStringContainsString('# Changelog', $changelog);
        $this->assertStringContainsString('## [1.0.0] - 2023-01-01', $changelog);
        $this->assertStringNotContainsString('### Added', $changelog);
        $this->assertStringNotContainsString('### Changed', $changelog);
        $this->assertStringNotContainsString('### Removed', $changelog);
    }

    public function testAddedClassChange(): void
    {
        $class = new ClassElement('NewClass', 'Test');
        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $class
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringContainsString('### Added', $changelog);
        $this->assertStringContainsString('🟡 **class** `Test\NewClass`: New class added', $changelog);
    }

    public function testRemovedMethodChange(): void
    {
        $method = new MethodElement('removedMethod', 'Test');
        $change = new ApiChange(
            ApiChange::TYPE_REMOVED,
            ApiChange::SEVERITY_MAJOR,
            $method
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('### Removed', $changelog);
        $this->assertStringContainsString('🔴 **method** `Test\removedMethod`: method removed', $changelog);
    }

    public function testParameterTypeChange(): void
    {
        $oldParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [['name' => 'param', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('### Changed', $changelog);
        $this->assertStringContainsString('Parameter $param type changed from string to int', $changelog);
    }

    public function testReturnTypeChange(): void
    {
        $oldMethod = new MethodElement('testMethod', 'Test', [], 'string');
        $newMethod = new MethodElement('testMethod', 'Test', [], 'int');

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Return type changed from string to int', $changelog);
    }

    public function testNullableTypeChange(): void
    {
        $oldParams = [['name' => 'param', 'type' => '?string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Parameter $param type changed from ?string to string', $changelog);
    }

    public function testParameterBecameOptional(): void
    {
        $oldParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => 'default', 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_PATCH,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '1.0.1');

        $this->assertStringContainsString('Parameter $param became optional', $changelog);
    }

    public function testParameterBecameRequired(): void
    {
        $oldParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => 'default', 'isVariadic' => false, 'byRef' => false]];
        $newParams = [['name' => 'param', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Parameter $param became required', $changelog);
    }

    public function testAddedParameters(): void
    {
        $oldParams = [['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];
        $newParams = [
            ['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false],
            ['name' => 'param2', 'type' => 'int', 'defaultValue' => 42, 'isVariadic' => false, 'byRef' => false],
            ['name' => 'param3', 'type' => 'bool', 'defaultValue' => true, 'isVariadic' => false, 'byRef' => false]
        ];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_PATCH,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '1.0.1');

        $this->assertStringContainsString('Added 2 parameters', $changelog);
    }

    public function testRemovedParameters(): void
    {
        $oldParams = [
            ['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false],
            ['name' => 'param2', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]
        ];
        $newParams = [['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Removed 1 parameter', $changelog);
    }

    public function testVisibilityChange(): void
    {
        $oldMethod = new MethodElement('testMethod', 'Test', [], null, false, false, false, 'protected');
        $newMethod = new MethodElement('testMethod', 'Test', [], null, false, false, false, 'public');

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MINOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringContainsString('Visibility changed from protected to public', $changelog);
    }

    public function testStaticModifierChanges(): void
    {
        $oldMethod = new MethodElement('testMethod', 'Test', [], null, false);
        $newMethod = new MethodElement('testMethod', 'Test', [], null, true);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Became static', $changelog);
    }

    public function testAbstractModifierChanges(): void
    {
        $oldMethod = new MethodElement('testMethod', 'Test', [], null, false, true);
        $newMethod = new MethodElement('testMethod', 'Test', [], null, false, false);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('No longer abstract', $changelog);
    }

    public function testFinalModifierChanges(): void
    {
        $oldMethod = new MethodElement('testMethod', 'Test', [], null, false, false, false);
        $newMethod = new MethodElement('testMethod', 'Test', [], null, false, false, true);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Became final', $changelog);
    }

    public function testClassInheritanceChanges(): void
    {
        $oldClass = new ClassElement('TestClass', 'Test', [], [], false, false, null, []);
        $newClass = new ClassElement('TestClass', 'Test', [], [], false, false, 'BaseClass', ['Interface1', 'Interface2']);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MINOR,
            $newClass,
            $oldClass
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringContainsString('Now extends BaseClass', $changelog);
        $this->assertStringContainsString('now implements Interface1, Interface2', $changelog);
    }

    public function testConstantValueChange(): void
    {
        $oldConstant = new ConstantElement('TEST_CONST', 'Test', 'old_value');
        $newConstant = new ConstantElement('TEST_CONST', 'Test', 'new_value');

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newConstant,
            $oldConstant
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString("Value changed from 'old_value' to 'new_value'", $changelog);
    }

    public function testMultipleChanges(): void
    {
        $addedClass = new ClassElement('NewClass', 'Test');
        $removedMethod = new MethodElement('removedMethod', 'Test');

        $changes = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, $addedClass),
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, $removedMethod)
        ];

        $changelog = $this->generator->generate($changes, '2.0.0');

        $this->assertStringContainsString('### Added', $changelog);
        $this->assertStringContainsString('### Removed', $changelog);
        $this->assertStringContainsString('NewClass', $changelog);
        $this->assertStringContainsString('removedMethod', $changelog);
    }

    public function testSeverityBadges(): void
    {
        $majorChange = new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test'));
        $minorChange = new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test', 'Test'));
        $patchChange = new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test', 'Test'));

        $changes = [$majorChange, $minorChange, $patchChange];
        $changelog = $this->generator->generate($changes, '2.0.0');

        $this->assertStringContainsString('🔴', $changelog); // Major
        $this->assertStringContainsString('🟡', $changelog); // Minor
        $this->assertStringContainsString('🟢', $changelog); // Patch
    }

    public function testComplexParameterAndReturnTypeChanges(): void
    {
        $oldParams = [
            ['name' => 'param1', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false],
            ['name' => 'param2', 'type' => 'int', 'defaultValue' => 42, 'isVariadic' => false, 'byRef' => false]
        ];
        $newParams = [
            ['name' => 'param1', 'type' => '?string', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false],
            ['name' => 'param2', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'byRef' => false]
        ];

        $oldMethod = new MethodElement('testMethod', 'Test', $oldParams, 'void', true);
        $newMethod = new MethodElement('testMethod', 'Test', $newParams, '?int', false);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Parameter $param1 type changed from string to ?string', $changelog);
        $this->assertStringContainsString('parameter $param2 became required', $changelog);
        $this->assertStringContainsString('return type changed from void to ?int', $changelog);
        $this->assertStringContainsString('no longer static', $changelog);
    }
}