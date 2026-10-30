<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shared CSRF / translation / form id defaults for panel forms: a FormKit Type (REQ-TWIG-005 rule 11) on the
 * `maintenance_mode` profile (`nowo_form_kit.profiles.maintenance_mode`, prepended by the bundle extension; hosts
 * override it there).
 *
 * Empty block prefix keeps request field names flat (`message`, `_token`, …)
 * so host Twig overrides that still post raw HTML remain compatible.
 *
 * @extends AbstractType<array<string, mixed>|null>
 */
#[FormKitConfig(self::FORM_KIT_PROFILE)]
abstract class AbstractMaintenanceFormType extends AbstractType
{
    use FormOptionsTrait;

    /** FormKit profile of the panel forms (key in `nowo_form_kit.profiles`). */
    public const FORM_KIT_PROFILE = 'maintenance_mode';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => NowoMaintenanceModeBundle::TRANSLATION_DOMAIN,
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'csrf_token_id'      => $this->csrfTokenId(),
            'method'             => 'POST',
            // Unique <form id> per panel form: the UiKit 2.2 settings scaffold binds a section's submit button
            // (printed after </form> next to SettingsSection::actions) through form="<id>"; flat-named forms would
            // otherwise all share the scaffold's fallback id.
            'attr' => ['id' => str_replace('_', '-', $this->csrfTokenId()) . '-form'],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }

    abstract protected function csrfTokenId(): string;

    /**
     * Adds a field with the options of the `maintenance_mode` FormKit profile merged in. Without FormKit's option
     * merger (FormKitBundle not registered in the kernel, plain form factories in tests) the explicit options are used
     * as is.
     *
     * @param FormBuilderInterface<mixed> $builder
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed> $options
     */
    protected function addField(FormBuilderInterface $builder, string $name, string $type, array $options = []): void
    {
        if (!isset($this->formOptionsMerger)) {
            $builder->add($name, $type, $options);

            return;
        }

        $this->addWithDefaults($builder, $name, $type, $options);
    }
}
