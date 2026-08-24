<?php

declare(strict_types=1);

namespace App\Services\Core\Widgets;

/**
 * Base for the classes that supply dashboard widget data.
 *
 * Each provider owns one domain's widgets. DashboardService resolves a widget's
 * data source to a method on whichever provider declares it, so adding a widget
 * means adding a method here rather than touching the dashboard itself.
 *
 * Every widget method takes the widget's config array and returns the data the
 * front end renders.
 */
abstract class WidgetProvider
{
    protected int $organizationId;

    protected ?int $branchId = null;

    /** Scope every query this provider runs to one organization, and optionally one branch. */
    public function setContext(int $organizationId, ?int $branchId = null): static
    {
        $this->organizationId = $organizationId;
        $this->branchId       = $branchId;

        return $this;
    }
}
