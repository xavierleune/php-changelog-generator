<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Parser\PhpParser;
use Leune\ChangelogGenerator\Differ\ApiDiffer;
use Leune\ChangelogGenerator\Analyzer\SemVerAnalyzer;
use Leune\ChangelogGenerator\Generator\ChangelogGenerator;
use Leune\ChangelogGenerator\Model\ApiChange;

class EndToEndTest extends TestCase
{
    private string $testDataDir;
    private PhpParser $parser;
    private ApiDiffer $differ;
    private SemVerAnalyzer $analyzer;
    private ChangelogGenerator $generator;

    protected function setUp(): void
    {
        $this->testDataDir = __DIR__ . '/../fixtures';
        $this->parser = new PhpParser();
        $this->differ = new ApiDiffer();
        $this->analyzer = new SemVerAnalyzer();
        $this->generator = new ChangelogGenerator();

        // Create test fixtures directory
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testDataDir)) {
            $this->removeDirectory($this->testDataDir);
        }
    }

        public function testCompleteWorkflowWithNoChanges(): void
    {
        // Create identical codebases
        $v1Dir = $this->testDataDir . '/v1';
        $v2Dir = $this->testDataDir . '/v2';
        
        $this->createTestCode($v1Dir, 'v1');
        $this->createTestCode($v2Dir, 'v1'); // Same as v1

        // Parse both versions
        $oldSnapshot = $this->parser->parseDirectory($v1Dir);
        $newSnapshot = $this->parser->parseDirectory($v2Dir);

        // Diff
        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Verify no changes detected
        $this->assertEmpty($changes);

        // Analyze severity
        $severity = $this->analyzer->analyzeSeverity($changes);
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $severity);

        // Generate changelog
        $changelog = $this->generator->generate($changes, '1.0.1');
        $this->assertStringNotContainsString('### Added', $changelog);
        $this->assertStringContainsString('### Changed', $changelog);
        $this->assertStringContainsString('No API changes detected', $changelog);
        $this->assertStringNotContainsString('### Removed', $changelog);
    }

    public function testCompleteWorkflowWithMinorChanges(): void
    {
        $v1Dir = $this->testDataDir . '/v1';
        $v2Dir = $this->testDataDir . '/v2';
        
        $this->createTestCode($v1Dir, 'v1');
        $this->createTestCode($v2Dir, 'v2'); // With new method

        // Parse both versions
        $oldSnapshot = $this->parser->parseDirectory($v1Dir);
        $newSnapshot = $this->parser->parseDirectory($v2Dir);

        // Diff
        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Verify changes detected
        $this->assertCount(1, $changes);
        $this->assertEquals(ApiChange::TYPE_ADDED, $changes[0]->getType());
        $this->assertEquals('newMethod', $changes[0]->getElement()->getName());

        // Analyze severity
        $severity = $this->analyzer->analyzeSeverity($changes);
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $severity);

        // Check version recommendation
        $recommendedVersion = $this->analyzer->getRecommendedVersion('1.0.0', $changes);
        $this->assertEquals('1.1.0', $recommendedVersion);

        // Generate changelog
        $changelog = $this->generator->generate($changes, $recommendedVersion);
        $this->assertStringContainsString('### Added', $changelog);
        $this->assertStringContainsString('🟡 **method** `Test\TestClass::newMethod`: New method added', $changelog);
    }

    public function testCompleteWorkflowWithMajorChanges(): void
    {
        $v1Dir = $this->testDataDir . '/v1';
        $v2Dir = $this->testDataDir . '/v2';
        
        $this->createTestCode($v1Dir, 'v1');
        $this->createTestCode($v2Dir, 'breaking'); // With breaking changes

        // Parse both versions
        $oldSnapshot = $this->parser->parseDirectory($v1Dir);
        $newSnapshot = $this->parser->parseDirectory($v2Dir);

        // Diff
        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Verify breaking changes detected
        $this->assertNotEmpty($changes);
        
        $hasMajorChange = false;
        foreach ($changes as $change) {
            if ($change->getSeverity() === ApiChange::SEVERITY_MAJOR) {
                $hasMajorChange = true;
                break;
            }
        }
        $this->assertTrue($hasMajorChange, 'Should have detected major changes');

        // Analyze severity
        $severity = $this->analyzer->analyzeSeverity($changes);
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $severity);

        // Check version recommendation
        $recommendedVersion = $this->analyzer->getRecommendedVersion('1.0.0', $changes);
        $this->assertEquals('2.0.0', $recommendedVersion);

        // Generate changelog
        $changelog = $this->generator->generate($changes, $recommendedVersion);
        $this->assertStringContainsString('🔴', $changelog); // Should contain major change badge
    }

    public function testCompleteWorkflowWithNullableTypes(): void
    {
        $v1Dir = $this->testDataDir . '/v1';
        $v2Dir = $this->testDataDir . '/v2';
        
        $this->createTestCode($v1Dir, 'nullable_old');
        $this->createTestCode($v2Dir, 'nullable_new');

        // Parse both versions
        $oldSnapshot = $this->parser->parseDirectory($v1Dir);
        $newSnapshot = $this->parser->parseDirectory($v2Dir);

        // Diff
        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Verify nullable type change detected
        $this->assertCount(1, $changes);
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $changes[0]->getType());
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $changes[0]->getSeverity());

        // Generate changelog
        $changelog = $this->generator->generate($changes, '2.0.0');
        $this->assertStringContainsString('Parameter $param type changed from ?string to string', $changelog);
    }

    public function testCompleteWorkflowWithUnionTypes(): void
    {
        $v1Dir = $this->testDataDir . '/v1';
        $v2Dir = $this->testDataDir . '/v2';
        
        $this->createTestCode($v1Dir, 'union_old');
        $this->createTestCode($v2Dir, 'union_new');

        // Parse both versions
        $oldSnapshot = $this->parser->parseDirectory($v1Dir);
        $newSnapshot = $this->parser->parseDirectory($v2Dir);

        // Diff
        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Verify union type change detected
        $this->assertCount(1, $changes);
        $this->assertEquals(ApiChange::TYPE_MODIFIED, $changes[0]->getType());

        // Generate changelog
        $changelog = $this->generator->generate($changes, '2.0.0');
        $this->assertStringContainsString('Parameter $value type changed from string to string|int', $changelog);
    }

    public function testParameterNameChangeIgnored(): void
    {
        $v1Dir = $this->testDataDir . '/v1';
        $v2Dir = $this->testDataDir . '/v2';
        
        $this->createTestCode($v1Dir, 'param_name_old');
        $this->createTestCode($v2Dir, 'param_name_new');

        // Parse both versions
        $oldSnapshot = $this->parser->parseDirectory($v1Dir);
        $newSnapshot = $this->parser->parseDirectory($v2Dir);

        // Diff
        $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

        // Verify no changes detected for parameter name change
        $this->assertEmpty($changes);
    }

    private function createTestCode(string $dir, string $version): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        switch ($version) {
            case 'v1':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function existingMethod(string $param): string
    {
        return $param;
    }
}');
                break;

            case 'v2':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function existingMethod(string $param): string
    {
        return $param;
    }

    public function newMethod(): void
    {
    }
}');
                break;

            case 'breaking':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function existingMethod(int $param): string
    {
        return (string) $param;
    }
}');
                break;

            case 'nullable_old':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function testMethod(?string $param): ?int
    {
        return $param ? strlen($param) : null;
    }
}');
                break;

            case 'nullable_new':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function testMethod(string $param): ?int
    {
        return strlen($param);
    }
}');
                break;

            case 'union_old':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function testMethod(string $value): string
    {
        return $value;
    }
}');
                break;

            case 'union_new':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function testMethod(string|int $value): string
    {
        return (string) $value;
    }
}');
                break;

            case 'param_name_old':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function testMethod(string $oldName): string
    {
        return $oldName;
    }
}');
                break;

            case 'param_name_new':
                file_put_contents($dir . '/Test.php', '<?php
namespace Test;

class TestClass
{
    public function testMethod(string $newName): string
    {
        return $newName;
    }
}');
                break;
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}