<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Leune\ChangelogGenerator\Generator\ChangelogGenerator;
use Leune\ChangelogGenerator\Model\ApiChange;
use Leune\ChangelogGenerator\Model\ClassElement;
use Leune\ChangelogGenerator\Model\MethodElement;
use Leune\ChangelogGenerator\Model\ConstantElement;
use Leune\ChangelogGenerator\Model\InterfaceElement;

class FullyQualifiedNamesTest extends TestCase
{
    private ChangelogGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ChangelogGenerator();
    }

    public function testAddedClassConstantFullyQualifiedName(): void
    {
        $class = new ClassElement('CampaignState', 'CCMBenchmark\ANSClient\Model');
        $constant = new ConstantElement('PLANNED', 'CCMBenchmark\ANSClient\Model', 'planned_value');
        $constant->setParentClass('CampaignState');
        $class->addConstant($constant);

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $constant
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        // Should show class name + constant name, not just constant name
        $this->assertStringContainsString('CCMBenchmark\ANSClient\Model\CampaignState::PLANNED', $changelog);
        $this->assertStringNotContainsString('CCMBenchmark\ANSClient\Model\PLANNED', $changelog);
    }

    public function testAddedClassMethodFullyQualifiedName(): void
    {
        $class = new ClassElement('UserService', 'App\Service');
        $method = new MethodElement('createUser', 'App\Service');
        $method->setParentClass('UserService');
        $class->addMethod($method);

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $method
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        // Should show class name + method name, not just method name
        $this->assertStringContainsString('App\Service\UserService::createUser', $changelog);
        $this->assertStringNotContainsString('App\Service\createUser', $changelog);
    }

    public function testRemovedClassConstantFullyQualifiedName(): void
    {
        $class = new ClassElement('Status', 'My\Namespace');
        $constant = new ConstantElement('DEPRECATED', 'My\Namespace', 'deprecated');
        $constant->setParentClass('Status');
        $class->addConstant($constant);

        $change = new ApiChange(
            ApiChange::TYPE_REMOVED,
            ApiChange::SEVERITY_MAJOR,
            $constant
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('My\Namespace\Status::DEPRECATED', $changelog);
        $this->assertStringNotContainsString('My\Namespace\DEPRECATED', $changelog);
    }

    public function testRemovedClassMethodFullyQualifiedName(): void
    {
        $class = new ClassElement('Calculator', 'Math\Utils');
        $method = new MethodElement('divide', 'Math\Utils');
        $method->setParentClass('Calculator');
        $class->addMethod($method);

        $change = new ApiChange(
            ApiChange::TYPE_REMOVED,
            ApiChange::SEVERITY_MAJOR,
            $method
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Math\Utils\Calculator::divide', $changelog);
        $this->assertStringNotContainsString('Math\Utils\divide', $changelog);
    }

    public function testModifiedClassMethodFullyQualifiedName(): void
    {
        $class = new ClassElement('EmailSender', 'Communication');
        $oldMethod = new MethodElement('send', 'Communication', [], 'void');
        $oldMethod->setParentClass('EmailSender');
        $newMethod = new MethodElement('send', 'Communication', [], 'bool');
        $newMethod->setParentClass('EmailSender');
        $class->addMethod($newMethod);

        $change = new ApiChange(
            ApiChange::TYPE_MODIFIED,
            ApiChange::SEVERITY_MAJOR,
            $newMethod,
            $oldMethod
        );

        $changelog = $this->generator->generate([$change], '2.0.0');

        $this->assertStringContainsString('Communication\EmailSender::send', $changelog);
        $this->assertStringNotContainsString('Communication\send', $changelog);
    }

    public function testInterfaceConstantFullyQualifiedName(): void
    {
        $interface = new InterfaceElement('PaymentInterface', 'Payment\Contract');
        $constant = new ConstantElement('MAX_AMOUNT', 'Payment\Contract', 10000);
        $constant->setParentClass('PaymentInterface');
        $interface->addConstant($constant);

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $constant
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringContainsString('Payment\Contract\PaymentInterface::MAX_AMOUNT', $changelog);
        $this->assertStringNotContainsString('Payment\Contract\MAX_AMOUNT', $changelog);
    }

    public function testInterfaceMethodFullyQualifiedName(): void
    {
        $interface = new InterfaceElement('LoggerInterface', 'Psr\Log');
        $method = new MethodElement('emergency', 'Psr\Log');
        $method->setParentClass('LoggerInterface');
        $interface->addMethod($method);

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $method
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        $this->assertStringContainsString('Psr\Log\LoggerInterface::emergency', $changelog);
        $this->assertStringNotContainsString('Psr\Log\emergency', $changelog);
    }

    public function testGlobalFunctionKeepsOriginalName(): void
    {
        $function = new \Leune\ChangelogGenerator\Model\FunctionElement('str_contains_polyfill', 'Polyfill\Php8');

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $function
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        // Global functions should keep their namespace\function format
        $this->assertStringContainsString('Polyfill\Php8\str_contains_polyfill', $changelog);
    }

    public function testGlobalConstantKeepsOriginalName(): void
    {
        $constant = new ConstantElement('PHP_VERSION_ID', 'Constants', 80400);

        $change = new ApiChange(
            ApiChange::TYPE_ADDED,
            ApiChange::SEVERITY_MINOR,
            $constant
        );

        $changelog = $this->generator->generate([$change], '1.1.0');

        // Global constants should keep their namespace\constant format
        $this->assertStringContainsString('Constants\PHP_VERSION_ID', $changelog);
    }

    public function testMultipleChangesWithCorrectNames(): void
    {
        $class = new ClassElement('HttpClient', 'Http');
        
        $method = new MethodElement('get', 'Http');
        $method->setParentClass('HttpClient');
        $constant = new ConstantElement('TIMEOUT', 'Http', 30);
        $constant->setParentClass('HttpClient');
        
        $class->addMethod($method);
        $class->addConstant($constant);

        $changes = [
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, $method),
            new ApiChange(ApiChange::TYPE_ADDED, ApiChange::SEVERITY_MINOR, $constant)
        ];

        $changelog = $this->generator->generate($changes, '1.1.0');

        $this->assertStringContainsString('Http\HttpClient::get', $changelog);
        $this->assertStringContainsString('Http\HttpClient::TIMEOUT', $changelog);
        
        // Verify incorrect names are not present
        $this->assertStringNotContainsString('Http\get', $changelog);
        $this->assertStringNotContainsString('Http\TIMEOUT', $changelog);
    }
}