<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Attribute;

use Attribute;

/**
 * Mark a controller class or action so maintenance mode does not return 503.
 *
 * Requires subscriber priority lower than the router (default 31) so `_controller` is available.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ExcludeFromMaintenance
{
    public const ROUTE_DEFAULT = '_maintenance_exclude';
}
