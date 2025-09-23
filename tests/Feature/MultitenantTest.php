<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MultitenantTest extends TestCase
{

    use RefreshDatabase;
    
    /**
     * A basic feature test example.
     */

    // Properties to hold our test data
    protected $tenantA;
    protected $userA;
    protected $tenantB;
    protected $userB;
    protected $invoiceA1;
    protected $invoiceA2;
    protected $invoiceB1;

    public function setUp(): void{
        parent::setUp();
        // add tenant A and user A
        $this->tenantA = Tenant::factory()->create();
        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        // add tenant B and user B
        $this->tenantB = Tenant::factory()->create();
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        // added invoice A1 and A2  for tenant A and Invoice B1 for tenant B
        $this->invoiceA1 = Invoice::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->invoiceA2 = Invoice::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->invoiceB1 = Invoice::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    // test that user can see their own invoices
    public function test_a_user_can_only_see_their_own_invoices(): void
    {
        // 3. Execute Queries and Assertions
        $this->actingAs($this->userA);

        // Get all invoices for the authenticated user
        $invoices = Invoice::all();

        // Assert that the user can only see their two invoices
        // we get invoices of authenticated user A associated to tenant A 
        // which has two invoices A1 and A2 
        // so we xpect 2 records here 
        // check if the invoice contain A1
        // check if the invoice not contai B1 (tenant B)
        $this->assertCount(2, $invoices);
        $this->assertTrue($invoices->contains($this->invoiceA1));
        $this->assertFalse($invoices->contains($this->invoiceB1));
    }

}
