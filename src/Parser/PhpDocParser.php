<?php

declare(strict_types=1);

namespace XLeune\ChangelogGenerator\Parser;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser as PhpStanPhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TypeParser;

class PhpDocParser
{
    private Lexer $lexer;
    private PhpStanPhpDocParser $parser;

    public function __construct()
    {
        $config = new ParserConfig(usedAttributes: []);
        $this->lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $this->parser = new PhpStanPhpDocParser($config, $typeParser, $constExprParser);
    }

    public function parse(?string $docComment): ?PhpDocNode
    {
        if ($docComment === null || trim($docComment) === '') {
            return null;
        }

        try {
            $tokens = $this->lexer->tokenize($docComment);
            $tokenIterator = new TokenIterator($tokens);
            return $this->parser->parse($tokenIterator);
        } catch (\Throwable $e) {
            error_log("Failed to parse PHPDoc: " . $e->getMessage());
            return null;
        }
    }

    public function extractReturnType(?string $docComment): ?string
    {
        $phpDocNode = $this->parse($docComment);
        if ($phpDocNode === null) {
            return null;
        }

        foreach ($phpDocNode->children as $child) {
            if ($child instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode) {
                return (string) $child->type;
            }
        }

        return null;
    }

    public function extractParameterTypes(?string $docComment): array
    {
        $phpDocNode = $this->parse($docComment);
        if ($phpDocNode === null) {
            return [];
        }

        $parameters = [];
        foreach ($phpDocNode->children as $child) {
            if ($child instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode) {
                $paramName = ltrim($child->parameterName, '$');
                $parameters[$paramName] = (string) $child->type;
            }
        }

        return $parameters;
    }
}
