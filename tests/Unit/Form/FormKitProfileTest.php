<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\DependencyInjection\Configuration as FormKitConfiguration;
use Nowo\FormKitBundle\DependencyInjection\FormKitExtension;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\MaintenanceModeBundle\DependencyInjection\MaintenanceModeExtension;
use Nowo\MaintenanceModeBundle\Form\AbstractMaintenanceFormType;
use Nowo\MaintenanceModeBundle\Form\ClearScheduleType;
use Nowo\MaintenanceModeBundle\Form\DisableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\EnableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LoginMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LogoutMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\ScheduleMaintenanceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;

/**
 * REQ-TWIG-005 rule 11: the bundle forms are FormKit Types on the `maintenance_mode` profile, and the stock profile renders
 * them exactly as their explicit options.
 */
final class FormKitProfileTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractMaintenanceFormType>, array<string, mixed>}>
     */
    public static function formTypes(): iterable
    {
        yield 'clear schedule' => [ClearScheduleType::class, []];
        yield 'disable' => [DisableMaintenanceType::class, []];
        yield 'enable' => [EnableMaintenanceType::class, []];
        yield 'login' => [LoginMaintenanceType::class, []];
        yield 'logout' => [LogoutMaintenanceType::class, []];
        yield 'schedule' => [ScheduleMaintenanceType::class, []];
    }

    /**
     * @param class-string<AbstractMaintenanceFormType> $class
     * @param array<string, mixed> $options
     */
    #[DataProvider('formTypes')]
    public function testFormTypeUsesTheMaintenanceModeProfileAndRendersTheSameFields(string $class, array $options): void
    {
        $type = new $class();
        self::assertSame(AbstractMaintenanceFormType::FORM_KIT_PROFILE, FormKitConfig::nameFrom($type));

        $plain = $this->view(Forms::createFormFactoryBuilder()->getFormFactory(), $class, $options);

        $type->setFormOptionsMerger($this->merger(MaintenanceModeExtension::formKitProfile()));
        $kit = $this->view(Forms::createFormFactoryBuilder()->addType($type)->getFormFactory(), $class, $options);

        self::assertSame(array_keys($plain->children), array_keys($kit->children));
        foreach ($plain->children as $name => $child) {
            foreach (['label', 'attr', 'row_attr', 'help', 'required', 'translation_domain', 'value'] as $var) {
                self::assertSame($child->vars[$var] ?? null, $kit->children[$name]->vars[$var] ?? null, $class . ' ' . $name . '.' . $var);
            }
        }
    }

    public function testHostProfileClassesReachTheFields(): void
    {
        $type    = new EnableMaintenanceType();
        $profile = ['defaults' => ['attr' => ['class' => 'host-input'], 'row_attr' => [], 'help_attr' => []]] + MaintenanceModeExtension::formKitProfile();
        $type->setFormOptionsMerger($this->merger($profile));

        $view = $this->view(Forms::createFormFactoryBuilder()->addType($type)->getFormFactory(), EnableMaintenanceType::class, []);

        self::assertSame('host-input', $view->children['message']->vars['attr']['class'] ?? null);
        self::assertSame(3, $view->children['message']->vars['attr']['rows'] ?? null);
    }

    public function testPrependSkipsHostsWithoutFormKit(): void
    {
        $container = new ContainerBuilder();
        (new MaintenanceModeExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_form_kit'));
    }

    public function testPrependSeedsTheProfileAndKeepsTheImplicitDefaultProfile(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FormKitExtension());
        (new MaintenanceModeExtension())->prepend($container);

        $config = (new Processor())->processConfiguration(new FormKitConfiguration(), $container->getExtensionConfig('nowo_form_kit'));

        self::assertSame('default', $config['default_profile']);
        self::assertArrayHasKey('default', $config['profiles']);
        self::assertSame('NowoMaintenanceModeBundle', $config['profiles']['maintenance_mode']['translation_domain']);
        self::assertFalse($config['profiles']['maintenance_mode']['auto_help']);
        self::assertFalse($config['profiles']['maintenance_mode']['auto_placeholder']);
    }

    public function testPrependAddsTheProfileNextToTheHostConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FormKitExtension());
        $container->loadFromExtension('nowo_form_kit', ['default_profile' => 'app', 'profiles' => ['app' => ['alias' => 'app']]]);
        (new MaintenanceModeExtension())->prepend($container);

        $config = (new Processor())->processConfiguration(new FormKitConfiguration(), $container->getExtensionConfig('nowo_form_kit'));

        self::assertSame('app', $config['default_profile']);
        self::assertArrayNotHasKey('default', $config['profiles']);
        self::assertArrayHasKey('maintenance_mode', $config['profiles']);
    }

    public function testPrependKeepsAHostDefinedProfile(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FormKitExtension());
        $container->loadFromExtension('nowo_form_kit', ['profiles' => ['maintenance_mode' => ['alias' => 'maintenance_mode', 'translation_domain' => 'admin']]]);
        (new MaintenanceModeExtension())->prepend($container);

        self::assertCount(1, $container->getExtensionConfig('nowo_form_kit'));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function merger(array $profile): FormOptionsMerger
    {
        /** @var array{translation_domain: string, defaults: array{attr: array<string, mixed>, row_attr: array<string, mixed>, help_attr: array<string, mixed>}, field_types: array<string, array<string, mixed>>} $config */
        $config = $profile + ['defaults' => ['attr' => [], 'row_attr' => [], 'help_attr' => []], 'field_types' => []];

        return new FormOptionsMerger([AbstractMaintenanceFormType::FORM_KIT_PROFILE => $config], AbstractMaintenanceFormType::FORM_KIT_PROFILE, new ConstraintDefinitionFactory());
    }

    /**
     * @param class-string<AbstractMaintenanceFormType> $class
     * @param array<string, mixed> $options
     */
    private function view(FormFactoryInterface $factory, string $class, array $options): FormView
    {
        return $factory->createNamed('', $class, null, ['csrf_protection' => false] + $options)->createView();
    }
}
