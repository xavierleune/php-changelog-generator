<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Model\ClassElement;

class ApiElementTest extends TestCase
{
    public function testIsInternalDetectsAnnotation(): void
    {
        $internalClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/**
             * This is an internal class
             * @internal
             * @since 1.0.0
             */'
        );

        $this->assertTrue($internalClass->isInternal());
    }

    public function testIsInternalWithoutAnnotation(): void
    {
        $publicClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/**
             * This is a public class
             * @since 1.0.0
             */'
        );

        $this->assertFalse($publicClass->isInternal());
    }

    public function testIsInternalWithNoDocComment(): void
    {
        $noDocClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            null
        );

        $this->assertFalse($noDocClass->isInternal());
    }

    public function testIsInternalWithEmptyDocComment(): void
    {
        $emptyDocClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            ''
        );

        $this->assertFalse($emptyDocClass->isInternal());
    }

    public function testIsInternalWithInternalInDescription(): void
    {
        // Should not match @internal when it's not a real annotation
        $falsePositiveClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/**
             * This class has internal logic but is not @internal
             * The word internal should not trigger the detection
             */'
        );

        $this->assertFalse($falsePositiveClass->isInternal());
    }

    public function testIsInternalWithInlineAnnotation(): void
    {
        $inlineInternalClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/** @internal This is inline */'
        );

        $this->assertTrue($inlineInternalClass->isInternal());
    }

    public function testIsInternalWithMultipleAnnotations(): void
    {
        $multiAnnotationClass = new ClassElement(
            'TestClass',
            'Test\\Namespace',
            [],
            [],
            false,
            false,
            null,
            [],
            '/**
             * @author John Doe
             * @internal
             * @deprecated Will be removed in v2.0
             * @since 1.0.0
             */'
        );

        $this->assertTrue($multiAnnotationClass->isInternal());
    }
}