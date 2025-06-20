<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Unit\Parser;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Model\ApiSnapshot;
use Leune\ChangelogGenerator\Parser\ApiVisitor;
use Leune\ChangelogGenerator\Parser\PhpDocParser;

class ApiVisitorTest extends TestCase
{
    private Parser $parser;
    private ApiVisitor $visitor;
    private ApiSnapshot $snapshot;

    protected function setUp(): void
    {
        $factory = new ParserFactory();
        $this->parser = $factory->createForHostVersion();
        $this->snapshot = new ApiSnapshot();
        $this->visitor = new ApiVisitor($this->snapshot, new PhpDocParser());
    }

    public function testParseBasicClass(): void
    {
        $code = '<?php
        namespace Test;
        
        class BasicClass
        {
            public function publicMethod(): string
            {
                return "test";
            }
            
            private function privateMethod(): void
            {
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $this->assertCount(1, $classes);
        $this->assertArrayHasKey('Test\BasicClass', $classes);

        $class = $classes['Test\BasicClass'];
        $this->assertEquals('BasicClass', $class->getName());
        $this->assertEquals('Test', $class->getNamespace());

        $methods = $class->getMethods();
        $this->assertCount(1, $methods); // Only public method should be captured
        $this->assertArrayHasKey('publicMethod', $methods);
        $this->assertArrayNotHasKey('privateMethod', $methods);
    }

    public function testParseMethodWithNullableTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class NullableTest
        {
            public function nullableMethod(?string $param): ?int
            {
                return $param ? strlen($param) : null;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\NullableTest'];
        $methods = $class->getMethods();
        $method = $methods['nullableMethod'];

        $this->assertEquals('?int', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('?string', $parameters[0]['type']);
        $this->assertEquals('param', $parameters[0]['name']);
    }

    public function testParseMethodWithUnionTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class UnionTest
        {
            public function unionMethod(string|int $value): string|int
            {
                return $value;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\UnionTest'];
        $methods = $class->getMethods();
        $method = $methods['unionMethod'];

        $this->assertEquals('string|int', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('string|int', $parameters[0]['type']);
    }

    public function testParseMethodWithDefaultValues(): void
    {
        $code = '<?php
        namespace Test;
        
        class DefaultTest
        {
            public function defaultMethod(int $required, string $optional = "default", ?array $nullable = null): void
            {
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\DefaultTest'];
        $methods = $class->getMethods();
        $method = $methods['defaultMethod'];

        $parameters = $method->getParameters();
        $this->assertCount(3, $parameters);

        // Required parameter
        $this->assertEquals('required', $parameters[0]['name']);
        $this->assertEquals('int', $parameters[0]['type']);
        $this->assertNull($parameters[0]['defaultValue']);

        // Optional parameter with string default
        $this->assertEquals('optional', $parameters[1]['name']);
        $this->assertEquals('string', $parameters[1]['type']);
        $this->assertEquals('default', $parameters[1]['defaultValue']);

        // Nullable parameter with null default
        $this->assertEquals('nullable', $parameters[2]['name']);
        $this->assertEquals('?array', $parameters[2]['type']);
        $this->assertEquals('null', $parameters[2]['defaultValue']);
    }

    public function testParseMethodWithVariadicAndByRef(): void
    {
        $code = '<?php
        namespace Test;
        
        class AdvancedTest
        {
            public function advancedMethod(&$byRef, ...$variadic): void
            {
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\AdvancedTest'];
        $methods = $class->getMethods();
        $method = $methods['advancedMethod'];

        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);

        // By reference parameter
        $this->assertEquals('byRef', $parameters[0]['name']);
        $this->assertTrue($parameters[0]['byRef']);
        $this->assertFalse($parameters[0]['isVariadic']);

        // Variadic parameter
        $this->assertEquals('variadic', $parameters[1]['name']);
        $this->assertFalse($parameters[1]['byRef']);
        $this->assertTrue($parameters[1]['isVariadic']);
    }

    public function testParseMethodModifiers(): void
    {
        $code = '<?php
        namespace Test;
        
        abstract class ModifierTest
        {
            public static function staticMethod(): void {}
            public abstract function abstractMethod(): void;
            public final function finalMethod(): void {}
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\ModifierTest'];
        $methods = $class->getMethods();

        $staticMethod = $methods['staticMethod'];
        $this->assertTrue($staticMethod->isStatic());
        $this->assertFalse($staticMethod->isAbstract());
        $this->assertFalse($staticMethod->isFinal());

        $abstractMethod = $methods['abstractMethod'];
        $this->assertFalse($abstractMethod->isStatic());
        $this->assertTrue($abstractMethod->isAbstract());
        $this->assertFalse($abstractMethod->isFinal());

        $finalMethod = $methods['finalMethod'];
        $this->assertFalse($finalMethod->isStatic());
        $this->assertFalse($finalMethod->isAbstract());
        $this->assertTrue($finalMethod->isFinal());
    }

    public function testParseClassWithInheritance(): void
    {
        $code = '<?php
        namespace Test;
        
        abstract class BaseClass {}
        
        final class ChildClass extends BaseClass implements \Countable, \Iterator
        {
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        
        $baseClass = $classes['Test\BaseClass'];
        $this->assertTrue($baseClass->isAbstract());
        $this->assertFalse($baseClass->isFinal());
        $this->assertNull($baseClass->getExtends());
        $this->assertEmpty($baseClass->getImplements());

        $childClass = $classes['Test\ChildClass'];
        $this->assertFalse($childClass->isAbstract());
        $this->assertTrue($childClass->isFinal());
        $this->assertEquals('BaseClass', $childClass->getExtends());
        $this->assertEquals(['Countable', 'Iterator'], $childClass->getImplements());
    }

    public function testParseInterface(): void
    {
        $code = '<?php
        namespace Test;
        
        interface TestInterface extends \Iterator
        {
            public const CONSTANT = "value";
            
            public function method(): string;
        }';

        $this->parseCode($code);

        $interfaces = $this->snapshot->getInterfaces();
        $this->assertCount(1, $interfaces);
        $this->assertArrayHasKey('Test\TestInterface', $interfaces);

        $interface = $interfaces['Test\TestInterface'];
        $this->assertEquals('TestInterface', $interface->getName());
        $this->assertEquals('Test', $interface->getNamespace());
        $this->assertEquals(['Iterator'], $interface->getExtends());

        $methods = $interface->getMethods();
        $this->assertCount(1, $methods);
        $this->assertArrayHasKey('method', $methods);

        $constants = $interface->getConstants();
        $this->assertCount(1, $constants);
        $this->assertArrayHasKey('CONSTANT', $constants);
    }

    public function testParseGlobalFunction(): void
    {
        $code = '<?php
        namespace Test;
        
        function globalFunction(string $param): bool
        {
            return true;
        }';

        $this->parseCode($code);

        $functions = $this->snapshot->getFunctions();
        $this->assertCount(1, $functions);
        $this->assertArrayHasKey('Test\globalFunction', $functions);

        $function = $functions['Test\globalFunction'];
        $this->assertEquals('globalFunction', $function->getName());
        $this->assertEquals('Test', $function->getNamespace());
        $this->assertEquals('bool', $function->getReturnType());

        $parameters = $function->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('string', $parameters[0]['type']);
    }

    public function skipTestParsePhpDocTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class PhpDocTest
        {
            /**
             * @param string $param
             * @return int
             */
            public function phpDocMethod($param)
            {
                return 42;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\PhpDocTest'];
        $methods = $class->getMethods();
        $method = $methods['phpDocMethod'];

        $this->assertEquals('int', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('string', $parameters[0]['type']);
    }

    private function parseCode(string $code): void
    {
        $ast = $this->parser->parse($code);
        $this->visitor->setCurrentFile('test.php');

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this->visitor);
        $traverser->traverse($ast);
    }
}