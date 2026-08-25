<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Models\HR\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LeaveRequest::factory() builds a LeaveRequest.
 *
 * The model defines its factory inline through newFactory(), which overrides
 * the namespace convention — a standalone factory class named for this model
 * is never consulted, whatever it declares. Nothing called
 * LeaveRequest::factory(), so the override had nothing to disagree with.
 */
class LeaveRequestFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_builds_the_model_it_is_named_for(): void
    {
        $this->assertInstanceOf(LeaveRequest::class, LeaveRequest::factory()->make());
    }

    /**
     * The attributes have to be columns leave_requests has. A factory naming
     * fields the table lacks passes make() and fails create().
     */
    public function test_factory_persists(): void
    {
        $request = LeaveRequest::factory()->create();

        $this->assertDatabaseHas('leave_requests', ['id' => $request->id]);
        $this->assertNotNull($request->from_date);
        $this->assertNotNull($request->to_date);
    }

}
