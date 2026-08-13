<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Form;

use Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shared CSRF / translation defaults for panel forms.
 *
 * Empty block prefix keeps request field names flat (`message`, `_token`, …)
 * so host Twig overrides that still post raw HTML remain compatible.
 *
 * @extends AbstractType<array<string, mixed>|null>
 */
abstract class AbstractMaintenanceFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => NowoMaintenanceModeBundle::TRANSLATION_DOMAIN,
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'csrf_token_id'      => $this->csrfTokenId(),
            'method'             => 'POST',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }

    abstract protected function csrfTokenId(): string;
}
