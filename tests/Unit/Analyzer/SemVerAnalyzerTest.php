<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Analyzer\SemVerAnalyzer;
use Leune\ChangelogGenerator\Model\ApiChange;
use Leune\ChangelogGenerator\Model\MethodElement;

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
        // For pre-1.0.0, major changes become minor unless strict mode is used
        $this->assertEquals('0.1.0', $this->analyzer->getRecommendedVersion('0', $majorChanges));
        $this->assertEquals('1.0.0', $this->analyzer->getRecommendedVersion('0', $majorChanges, true));
        
        // Empty version should be treated as 0.0.0
        $this->assertEquals('0.0.1', $this->analyzer->getRecommendedVersion('', $patchChanges));
        $this->assertEquals('0.1.0', $this->analyzer->getRecommendedVersion('', $minorChanges));
        // For pre-1.0.0, major changes become minor unless strict mode is used
        $this->assertEquals('0.1.0', $this->analyzer->getRecommendedVersion('', $majorChanges));
        $this->assertEquals('1.0.0', $this->analyzer->getRecommendedVersion('', $majorChanges, true));
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

    public function testPreReleaseVersionBehaviorDefault(): void
    {
        // For pre-1.0.0 versions, major changes should be treated as minor by default
        $majorChanges = [
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test'))
        ];
        
        // Test with various pre-1.0.0 versions
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($majorChanges, '0.1.0'));
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($majorChanges, '0.5.10'));
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($majorChanges, '0.0.1'));
        
        // Version recommendations should bump minor, not major
        $this->assertEquals('0.2.0', $this->analyzer->getRecommendedVersion('0.1.0', $majorChanges));
        $this->assertEquals('0.6.0', $this->analyzer->getRecommendedVersion('0.5.10', $majorChanges));
        $this->assertEquals('0.1.0', $this->analyzer->getRecommendedVersion('0.0.1', $majorChanges));
    }

    public function testPreReleaseVersionBehaviorStrictMode(): void
    {
        // With strict mode, major changes should remain major even for pre-1.0.0
        $majorChanges = [
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test'))
        ];
        
        // Test with strict mode enabled
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($majorChanges, '0.1.0', true));
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($majorChanges, '0.5.10', true));
        
        // Version recommendations should bump to 1.0.0
        $this->assertEquals('1.0.0', $this->analyzer->getRecommendedVersion('0.1.0', $majorChanges, true));
        $this->assertEquals('1.0.0', $this->analyzer->getRecommendedVersion('0.5.10', $majorChanges, true));
    }

    public function testPreReleaseVersionMinorAndPatchUnaffected(): void
    {
        // Minor and patch changes should behave the same regardless of version
        $minorChanges = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, new MethodElement('test', 'Test'))
        ];
        
        $patchChanges = [
            new ApiChange(ApiChange::TYPE_MODIFIED, ApiChange::SEVERITY_PATCH, new MethodElement('test', 'Test'))
        ];
        
        // Both strict and non-strict should behave the same for minor/patch
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($minorChanges, '0.1.0', false));
        $this->assertEquals(ApiChange::SEVERITY_MINOR, $this->analyzer->analyzeSeverity($minorChanges, '0.1.0', true));
        
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $this->analyzer->analyzeSeverity($patchChanges, '0.1.0', false));
        $this->assertEquals(ApiChange::SEVERITY_PATCH, $this->analyzer->analyzeSeverity($patchChanges, '0.1.0', true));
        
        // Version recommendations should be the same
        $this->assertEquals('0.2.0', $this->analyzer->getRecommendedVersion('0.1.0', $minorChanges, false));
        $this->assertEquals('0.2.0', $this->analyzer->getRecommendedVersion('0.1.0', $minorChanges, true));
        
        $this->assertEquals('0.1.1', $this->analyzer->getRecommendedVersion('0.1.0', $patchChanges, false));
        $this->assertEquals('0.1.1', $this->analyzer->getRecommendedVersion('0.1.0', $patchChanges, true));
    }

    public function testStableVersionsUnaffectedByStrictMode(): void
    {
        // Versions >= 1.0.0 should behave the same regardless of strict mode
        $majorChanges = [
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test'))
        ];
        
        // Test with 1.0.0+ versions
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($majorChanges, '1.0.0', false));
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($majorChanges, '1.0.0', true));
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($majorChanges, '2.5.10', false));
        $this->assertEquals(ApiChange::SEVERITY_MAJOR, $this->analyzer->analyzeSeverity($majorChanges, '2.5.10', true));
        
        // Version recommendations should be the same
        $this->assertEquals('2.0.0', $this->analyzer->getRecommendedVersion('1.0.0', $majorChanges, false));
        $this->assertEquals('2.0.0', $this->analyzer->getRecommendedVersion('1.0.0', $majorChanges, true));
        $this->assertEquals('3.0.0', $this->analyzer->getRecommendedVersion('2.5.10', $majorChanges, false));
        $this->assertEquals('3.0.0', $this->analyzer->getRecommendedVersion('2.5.10', $majorChanges, true));
    }

    public function testShouldBumpMethodsWithPreReleaseLogic(): void
    {
        $majorChanges = [
            new ApiChange(ApiChange::TYPE_REMOVED, ApiChange::SEVERITY_MAJOR, new MethodElement('test', 'Test'))
        ];
        
        // Pre-1.0.0 behavior: major changes should bump minor instead
        $this->assertFalse($this->analyzer->shouldBumpMajor($majorChanges, '0.1.0', false));
        $this->assertTrue($this->analyzer->shouldBumpMinor($majorChanges, '0.1.0', false));
        
        // With strict mode: should bump major
        $this->assertTrue($this->analyzer->shouldBumpMajor($majorChanges, '0.1.0', true));
        $this->assertFalse($this->analyzer->shouldBumpMinor($majorChanges, '0.1.0', true));
        
        // Stable versions: should always bump major
        $this->assertTrue($this->analyzer->shouldBumpMajor($majorChanges, '1.0.0', false));
        $this->assertTrue($this->analyzer->shouldBumpMajor($majorChanges, '1.0.0', true));
    }
}