<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use XLeune\ChangelogGenerator\Analyzer\SemVerAnalyzer;
use XLeune\ChangelogGenerator\Model\ApiChange;
use XLeune\ChangelogGenerator\Model\MethodElement;

class SemVerAnalyzerTest extends TestCase
{
    private SemVerAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new SemVerAnalyzer();
    }

    public function testNoChanges(): void
    {
        $changes = [];
        
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $this->analyzer->analyzeSeverity($changes));
        $this->assertFalse($this->analyzer->shouldBumpMajor($changes));
        $this->assertFalse($this->analyzer->shouldBumpMinor($changes));
    }

    public function testPatchChanges(): void
    {
        $changes = [
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test', 'Test'))
        ];
        
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $this->analyzer->analyzeSeverity($changes));
        $this->assertFalse($this->analyzer->shouldBumpMajor($changes));
        $this->assertFalse($this->analyzer->shouldBumpMinor($changes));
    }

    public function testMinorChanges(): void
    {
        $changes = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test', 'Test')),
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test2', 'Test'))
        ];
        
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($changes));
        $this->assertFalse($this->analyzer->shouldBumpMajor($changes));
        $this->assertTrue($this->analyzer->shouldBumpMinor($changes));
    }

    public function testMajorChanges(): void
    {
        $changes = [
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test')),
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test2', 'Test')),
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test3', 'Test'))
        ];
        
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($changes));
        $this->assertTrue($this->analyzer->shouldBumpMajor($changes));
        $this->assertFalse($this->analyzer->shouldBumpMinor($changes));
    }

    public function testVersionRecommendationPatch(): void
    {
        $changes = [
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test', 'Test'))
        ];
        
        $this->assertEquals('1.0.1', $this->analyzer->getRecommendedVersion('1.0.0', $changes));
        $this->assertEquals('2.5.11', $this->analyzer->getRecommendedVersion('2.5.10', $changes));
    }

    public function testVersionRecommendationMinor(): void
    {
        $changes = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test', 'Test'))
        ];
        
        $this->assertEquals('1.1.0', $this->analyzer->getRecommendedVersion('1.0.0', $changes));
        $this->assertEquals('1.1.0', $this->analyzer->getRecommendedVersion('1.0.5', $changes));
        $this->assertEquals('2.6.0', $this->analyzer->getRecommendedVersion('2.5.10', $changes));
    }

    public function testVersionRecommendationMajor(): void
    {
        $changes = [
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test'))
        ];
        
        $this->assertEquals('2.0.0', $this->analyzer->getRecommendedVersion('1.0.0', $changes));
        $this->assertEquals('2.0.0', $this->analyzer->getRecommendedVersion('1.5.10', $changes));
        $this->assertEquals('3.0.0', $this->analyzer->getRecommendedVersion('2.5.10', $changes));
    }

    public function testVersionRecommendationWithPartialVersions(): void
    {
        $changes = [
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test', 'Test'))
        ];
        
        $this->assertEquals('1.0.1', $this->analyzer->getRecommendedVersion('1.0', $changes));
        $this->assertEquals('1.0.1', $this->analyzer->getRecommendedVersion('1', $changes));
    }

    public function testVersionRecommendationEdgeCases(): void
    {
        $patchChanges = [
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test', 'Test'))
        ];
        
        $minorChanges = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test', 'Test'))
        ];
        
        $majorChanges = [
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test'))
        ];
        
        // Version with only major number
        $this->assertEquals('0.0.1', $this->analyzer->getRecommendedVersion('0', $patchChanges));
        $this->assertEquals('0.1.0', $this->analyzer->getRecommendedVersion('0', $minorChanges));
        $this->assertEquals('1.0.0', $this->analyzer->getRecommendedVersion('0', $majorChanges));
        
        // Empty version should be treated as 0.0.0
        $this->assertEquals('0.0.1', $this->analyzer->getRecommendedVersion('', $patchChanges));
        $this->assertEquals('0.1.0', $this->analyzer->getRecommendedVersion('', $minorChanges));
        $this->assertEquals('1.0.0', $this->analyzer->getRecommendedVersion('', $majorChanges));
    }

    public function testMixedSeverityPriorityMajor(): void
    {
        // Even if there are more minor/patch changes, major takes precedence
        $changes = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test1', 'Test')),
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test2', 'Test')),
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test3', 'Test')),
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test4', 'Test')),
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test5', 'Test'))
        ];
        
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($changes));
        $this->assertEquals('2.0.0', $this->analyzer->getRecommendedVersion('1.0.0', $changes));
    }

    public function testMixedSeverityPriorityMinor(): void
    {
        // Minor takes precedence over patch
        $changes = [
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test1', 'Test')),
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test2', 'Test')),
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test3', 'Test')),
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test4', 'Test'))
        ];
        
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($changes));
        $this->assertEquals('1.1.0', $this->analyzer->getRecommendedVersion('1.0.0', $changes));
    }

    public function testAnalyzeWithNonApiChangeObjects(): void
    {
        // Test that analyzer handles non-ApiChange objects gracefully
        $changes = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test', 'Test')),
            'not an ApiChange object',
            null,
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test2', 'Test'))
        ];
        
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($changes));
    }
}