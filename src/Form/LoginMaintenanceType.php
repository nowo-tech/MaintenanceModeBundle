<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Form;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Panel password gate login.
 */
final class LoginMaintenanceType extends AbstractMaintenanceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', PasswordType::class, [
            'required'     => true,
            'label'        => 'panel.login.password',
            'always_empty' => true,
            'attr'         => [
                'autocomplete' => 'current-password',
            ],
        ]);
    }

    protected function csrfTokenId(): string
    {
        return MaintenancePanelController::CSRF_LOGIN;
    }
}
