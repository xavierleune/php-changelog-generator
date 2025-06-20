<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Tests\Unit\Parser;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use XLeune\ChangelogGenerator\Model\ApiSnapshot;
use XLeune\ChangelogGenerator\Parser\ApiVisitor;
use XLeune\ChangelogGenerator\Parser\PhpDocParser;

class EdgeCasesTest extends TestCase
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

    public function testIntersectionTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        interface A {}
        interface B {}
        
        class IntersectionTest
        {
            public function intersectionMethod(A&B $param): A&B
            {
                return $param;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\IntersectionTest'];
        $methods = $class->getMethods();
        $method = $methods['intersectionMethod'];

        $this->assertEquals('A&B', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('A&B', $parameters[0]['type']);
    }

    public function testComplexUnionTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class ComplexUnionTest
        {
            public function complexMethod(string|int|null $value): array|false
            {
                return is_null($value) ? false : [$value];
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\ComplexUnionTest'];
        $methods = $class->getMethods();
        $method = $methods['complexMethod'];

        $this->assertEquals('array|false', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('string|int|null', $parameters[0]['type']);
    }

    public function testFullyQualifiedTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        use Another\Namespace\SomeClass;
        
        class QualifiedTest
        {
            public function qualifiedMethod(\DateTime $date, SomeClass $obj): \PDO
            {
                return new \PDO("sqlite::memory:");
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\QualifiedTest'];
        $methods = $class->getMethods();
        $method = $methods['qualifiedMethod'];

        $this->assertEquals('PDO', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('DateTime', $parameters[0]['type']);
        $this->assertEquals('SomeClass', $parameters[1]['type']);
    }

    public function testSelfAndParentTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class BaseClass {}
        
        class SelfTest extends BaseClass
        {
            public function selfMethod(): self
            {
                return $this;
            }
            
            public function parentMethod(): parent
            {
                return parent::class;
            }
            
            public function staticMethod(): static
            {
                return new static();
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\SelfTest'];
        $methods = $class->getMethods();

        $this->assertEquals('self', $methods['selfMethod']->getReturnType());
        $this->assertEquals('parent', $methods['parentMethod']->getReturnType());
        $this->assertEquals('static', $methods['staticMethod']->getReturnType());
    }

    public function testMixedTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class MixedTest
        {
            public function mixedMethod(mixed $value): mixed
            {
                return $value;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\MixedTest'];
        $methods = $class->getMethods();
        $method = $methods['mixedMethod'];

        $this->assertEquals('mixed', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('mixed', $parameters[0]['type']);
    }

    public function testCallableAndClosureTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class CallableTest
        {
            public function callableMethod(callable $callback): \Closure
            {
                return function() use ($callback) {
                    return $callback();
                };
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\CallableTest'];
        $methods = $class->getMethods();
        $method = $methods['callableMethod'];

        $this->assertEquals('Closure', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('callable', $parameters[0]['type']);
    }

    public function testArrayShapes(): void
    {
        $code = '<?php
        namespace Test;
        
        class ArrayTest
        {
            public function arrayMethod(array $data): array
            {
                return $data;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\ArrayTest'];
        $methods = $class->getMethods();
        $method = $methods['arrayMethod'];

        $this->assertEquals('array', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('array', $parameters[0]['type']);
    }

    public function testNestedNullableTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class NestedNullableTest
        {
            public function nestedMethod(?array $data = null): ?string
            {
                return $data ? implode(",", $data) : null;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\NestedNullableTest'];
        $methods = $class->getMethods();
        $method = $methods['nestedMethod'];

        $this->assertEquals('?string', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('?array', $parameters[0]['type']);
        $this->assertEquals('null', $parameters[0]['defaultValue']);
    }

    public function skipTestGenericsInPhpDoc(): void
    {
        $code = '<?php
        namespace Test;
        
        class GenericsTest
        {
            /**
             * @param array<string> $items
             * @return array<int, string>
             */
            public function genericsMethod($items)
            {
                return array_values($items);
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\GenericsTest'];
        $methods = $class->getMethods();
        $method = $methods['genericsMethod'];

        $this->assertEquals('array<int, string>', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('array<string>', $parameters[0]['type']);
    }

    public function testConflictingPhpDocAndNativeTypes(): void
    {
        $code = '<?php
        namespace Test;
        
        class ConflictTest
        {
            /**
             * @param int $value This conflicts with native string type
             * @return bool This conflicts with native string type
             */
            public function conflictMethod(string $value): string
            {
                return $value;
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\ConflictTest'];
        $methods = $class->getMethods();
        $method = $methods['conflictMethod'];

        // Native types should take precedence over conflicting PHPDoc
        $this->assertEquals('string', $method->getReturnType());

        $parameters = $method->getParameters();
        $this->assertEquals('string', $parameters[0]['type']);
    }

    public function testConstantExpressions(): void
    {
        $code = '<?php
        namespace Test;
        
        class ConstantTest
        {
            public const STRING_CONST = "string value";
            public const INT_CONST = 42;
            public const FLOAT_CONST = 3.14;
            public const BOOL_CONST = true;
            public const NULL_CONST = null;
            public const ARRAY_CONST = ["a", "b", "c"];
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $class = $classes['Test\ConstantTest'];
        $constants = $class->getConstants();

        $this->assertEquals('string value', $constants['STRING_CONST']->getValue());
        $this->assertEquals(42, $constants['INT_CONST']->getValue());
        $this->assertEquals(3.14, $constants['FLOAT_CONST']->getValue());
        $this->assertEquals('true', $constants['BOOL_CONST']->getValue());
        $this->assertEquals('null', $constants['NULL_CONST']->getValue());
        $this->assertEquals('array', $constants['ARRAY_CONST']->getValue());
    }

    public function testAnonymousClasses(): void
    {
        $code = '<?php
        namespace Test;
        
        class AnonymousTest
        {
            public function createAnonymous(): object
            {
                return new class {
                    public function method(): string
                    {
                        return "anonymous";
                    }
                };
            }
        }';

        $this->parseCode($code);

        $classes = $this->snapshot->getClasses();
        $this->assertCount(1, $classes); // Only the named class should be captured
        $this->assertArrayHasKey('Test\AnonymousTest', $classes);
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