<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A model whose table requires a uuid has to set one.
 *
 * The column is not nullable and has no default, so a model that does not use
 * HasUuid cannot be created at all — every create() is a not-null violation.
 * LeavePolicy was in this state, which is why LeavePolicyService could not
 * create a policy.
 *
 * A caller passing its own uuid hides this, so the check is on the model
 * rather than on any one call.
 */
class UuidTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These three are the second model on a table that already has one, and
     * are listed in tests/Fixtures/duplicate-models.txt. Giving a model that
     * may be about to be deleted a trait is not a fix; resolving the
     * duplicate is.
     */
    private const PENDING_A_DUPLICATE_DECISION = [
        'App\Models\Calendar\CalendarTaskComment',
        'App\Models\Core\OrganizationSubscription',
        'App\Models\Core\SubscriptionPlan',
    ];

    public function test_a_required_uuid_is_always_set(): void
    {
        $missing = [];

        foreach ($this->models() as $class) {
            if (in_array($class, self::PENDING_A_DUPLICATE_DECISION, true)) {
                continue;
            }

            $table = (new $class)->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            $column = collect(Schema::getColumns($table))->firstWhere('name', 'uuid');

            if ($column === null || ($column['nullable'] ?? true) || ($column['default'] ?? null) !== null) {
                continue;
            }

            $traits = array_map(
                static fn (string $t) => substr($t, strrpos($t, chr(92)) + 1),
                array_values(class_uses_recursive($class))
            );

            if (in_array('HasUuid', $traits, true)) {
                continue;
            }

            // Six models set it in their own boot hook instead of using the
            // trait. That works, so it passes, but it is the trait rewritten
            // six times.
            $source = (string) file_get_contents((new \ReflectionClass($class))->getFileName());

            if (preg_match('/->uuid\s*=/', $source) === 1) {
                continue;
            }

            $missing[] = $class.' ('.$table.')';
        }

        sort($missing);

        $this->assertSame([], $missing, sprintf(
            "These models cannot be created: their table requires a uuid and they never set one.\n%s",
            implode("\n", $missing)
        ));
    }

    /** @return list<class-string<Model>> */
    private function models(): array
    {
        $out = [];
        $sep = chr(92);

        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            $src = (string) file_get_contents($file->getRealPath());

            if (! preg_match('/^namespace\s+([^;]+);/m', $src, $ns)) {
                continue;
            }

            if (! preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $src, $cl)) {
                continue;
            }

            $class = trim($ns[1]).$sep.$cl[1];

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if (! $reflection->isAbstract() && $reflection->isSubclassOf(Model::class)) {
                $out[] = $class;
            }
        }

        return $out;
    }
}
