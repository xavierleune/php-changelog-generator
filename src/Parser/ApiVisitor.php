<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Parser;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use XLeune\ChangelogGenerator\Model\ApiSnapshot;
use XLeune\ChangelogGenerator\Model\ClassElement;
use XLeune\ChangelogGenerator\Model\ConstantElement;
use XLeune\ChangelogGenerator\Model\FunctionElement;
use XLeune\ChangelogGenerator\Model\InterfaceElement;
use XLeune\ChangelogGenerator\Model\MethodElement;

class ApiVisitor extends NodeVisitorAbstract
{
    private string $currentNamespace = '';
    private string $currentFile = '';
    private ?ClassElement $currentClass = null;
    private ?InterfaceElement $currentInterface = null;

    public function __construct(
        private ApiSnapshot $snapshot,
        private PhpDocParser $phpDocParser
    ) {
    }

    public function setCurrentFile(string $file): void
    {
        $this->currentFile = $file;
        $this->currentNamespace = '';
        $this->currentClass = null;
        $this->currentInterface = null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : '';
        } elseif ($node instanceof Node\Stmt\Class_) {
            $this->handleClass($node);
        } elseif ($node instanceof Node\Stmt\Interface_) {
            $this->handleInterface($node);
        } elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->handleMethod($node);
        } elseif ($node instanceof Node\Stmt\Function_) {
            $this->handleFunction($node);
        } elseif ($node instanceof Node\Stmt\ClassConst) {
            $this->handleClassConstant($node);
        } elseif ($node instanceof Node\Stmt\Const_) {
            $this->handleGlobalConstants($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClass = null;
        } elseif ($node instanceof Node\Stmt\Interface_) {
            $this->currentInterface = null;
        }

        return null;
    }

    private function handleClass(Node\Stmt\Class_ $node): void
    {
        $name = $node->name->toString();
        $extends = $node->extends ? $node->extends->toString() : null;
        $implements = array_map(fn($impl) => $impl->toString(), $node->implements);

        $this->currentClass = new ClassElement(
            $name,
            $this->currentNamespace,
            [],
            [],
            $node->isAbstract(),
            $node->isFinal(),
            $extends,
            $implements,
            $node->getDocComment()?->getText()
        );

        $this->snapshot->addClass($this->currentClass);
    }

    private function handleInterface(Node\Stmt\Interface_ $node): void
    {
        $name = $node->name->toString();
        $extends = array_map(fn($ext) => $ext->toString(), $node->extends);

        $this->currentInterface = new InterfaceElement(
            $name,
            $this->currentNamespace,
            [],
            [],
            $extends,
            $node->getDocComment()?->getText()
        );

        $this->snapshot->addInterface($this->currentInterface);
    }

    private function handleMethod(Node\Stmt\ClassMethod $node): void
    {
        if (!$node->isPublic()) {
            return;
        }

        if ($this->currentClass === null && $this->currentInterface === null) {
            return;
        }

        $name = $node->name->toString();
        $parameters = $this->extractParameters($node->params, $node->getDocComment()?->getText());
        $returnType = $this->extractReturnType($node, $node->getDocComment()?->getText());

        $method = new MethodElement(
            $name,
            $this->currentNamespace,
            $parameters,
            $returnType,
            $node->isStatic(),
            $node->isAbstract(),
            $node->isFinal(),
            $this->getVisibility($node),
            $node->getDocComment()?->getText()
        );

        if ($this->currentClass !== null) {
            $this->currentClass->addMethod($method);
        } elseif ($this->currentInterface !== null) {
            $this->currentInterface->addMethod($method);
        }
    }

    private function handleFunction(Node\Stmt\Function_ $node): void
    {
        $name = $node->name->toString();
        $parameters = $this->extractParameters($node->params, $node->getDocComment()?->getText());
        $returnType = $this->extractReturnType($node, $node->getDocComment()?->getText());

        $function = new FunctionElement(
            $name,
            $this->currentNamespace,
            $parameters,
            $returnType,
            $node->getDocComment()?->getText()
        );

        $this->snapshot->addFunction($function);
    }

    private function handleClassConstant(Node\Stmt\ClassConst $node): void
    {
        if (!$node->isPublic()) {
            return;
        }

        if ($this->currentClass === null && $this->currentInterface === null) {
            return;
        }

        foreach ($node->consts as $const) {
            $name = $const->name->toString();
            $value = $this->extractConstantValue($const->value);
            $type = $node->type ? $this->nodeTypeToString($node->type) : null;

            $constant = new ConstantElement(
                $name,
                $this->currentNamespace,
                $value,
                $type,
                $node->getDocComment()?->getText()
            );

            if ($this->currentClass !== null) {
                $this->currentClass->addConstant($constant);
            } elseif ($this->currentInterface !== null) {
                $this->currentInterface->addConstant($constant);
            }
        }
    }

    private function handleGlobalConstants(Node\Stmt\Const_ $node): void
    {
        foreach ($node->consts as $const) {
            $name = $const->name->toString();
            $value = $this->extractConstantValue($const->value);

            $constant = new ConstantElement(
                $name,
                $this->currentNamespace,
                $value,
                null,
                $node->getDocComment()?->getText()
            );

            $this->snapshot->addConstant($constant);
        }
    }

    private function extractParameters(array $params, ?string $docComment): array
    {
        $parameters = [];
        $docParameterTypes = $this->phpDocParser->extractParameterTypes($docComment);

        foreach ($params as $param) {
            $name = $param->var->name;
            $type = null;
            $defaultValue = null;
            $isVariadic = $param->variadic;
            $byRef = $param->byRef;

            if ($param->type !== null) {
                $type = $this->nodeTypeToString($param->type);
            } elseif (isset($docParameterTypes[$name])) {
                $type = $docParameterTypes[$name];
            }

            if ($param->default !== null) {
                $defaultValue = $this->extractConstantValue($param->default);
            }

            $parameters[] = [
                'name' => $name,
                'type' => $type,
                'defaultValue' => $defaultValue,
                'isVariadic' => $isVariadic,
                'byRef' => $byRef,
            ];
        }

        return $parameters;
    }

    private function extractReturnType(Node\FunctionLike $node, ?string $docComment): ?string
    {
        if ($node->getReturnType() !== null) {
            return $this->nodeTypeToString($node->getReturnType());
        }

        return $this->phpDocParser->extractReturnType($docComment);
    }

    private function extractConstantValue(Node\Expr $expr): mixed
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        } elseif ($expr instanceof Node\Scalar\LNumber) {
            return $expr->value;
        } elseif ($expr instanceof Node\Scalar\DNumber) {
            return $expr->value;
        } elseif ($expr instanceof Node\Expr\ConstFetch) {
            return $expr->name->toString();
        } elseif ($expr instanceof Node\Expr\Array_) {
            return 'array';
        }

        return 'unknown';
    }

    private function getVisibility(Node\Stmt\ClassMethod $node): string
    {
        if ($node->isPrivate()) {
            return 'private';
        } elseif ($node->isProtected()) {
            return 'protected';
        }
        return 'public';
    }

    private function isImplicitlyPublic(Node\Stmt\Class_ $node): bool
    {
        return !$node->isPrivate() && !$node->isProtected();
    }

    private function nodeTypeToString(Node $typeNode): string
    {
        if ($typeNode instanceof Node\NullableType) {
            return '?' . $this->nodeTypeToString($typeNode->type);
        }

        if ($typeNode instanceof Node\UnionType) {
            $types = array_map([$this, 'nodeTypeToString'], $typeNode->types);
            return implode('|', $types);
        }

        if ($typeNode instanceof Node\IntersectionType) {
            $types = array_map([$this, 'nodeTypeToString'], $typeNode->types);
            return implode('&', $types);
        }

        if ($typeNode instanceof Node\Name) {
            return $typeNode->toString();
        }

        if ($typeNode instanceof Node\Identifier) {
            return $typeNode->toString();
        }

        if (method_exists($typeNode, 'toString')) {
            return $typeNode->toString();
        }

        return (string) $typeNode;
    }
}
