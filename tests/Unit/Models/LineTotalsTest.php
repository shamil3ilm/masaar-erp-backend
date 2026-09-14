<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Purchase\BillLine;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\ConsignmentOrderLine;
use App\Models\Sales\InvoiceLine;
use App\Models\Sales\QuotationLine;
use App\Models\Sales\SalesOrderLine;
use App\Services\Tax\TaxDeterminationService;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The amounts a document line stores when it is saved.
 *
 * Every figure is bcmath truncation at four decimals, with a percentage
 * divided by 100 at six. A line discount comes off before tax. The cases pick
 * inputs where truncation and half-up rounding give different results.
 */
class LineTotalsTest extends TestCase
{
    /** @return array<string, array{class-string<Model>}> */
    public static function lineModels(): array
    {
        return [
            'invoice line' => [InvoiceLine::class],
            'bill line' => [BillLine::class],
            'quotation line' => [QuotationLine::class],
            'sales order line' => [SalesOrderLine::class],
            'purchase order line' => [PurchaseOrderLine::class],
        ];
    }

    /**
     * Lines whose table carries the India GST split.
     *
     * @return array<string, array{class-string<Model>}>
     */
    public static function gstLineModels(): array
    {
        return [
            'invoice line' => [InvoiceLine::class],
            'bill line' => [BillLine::class],
            'purchase order line' => [PurchaseOrderLine::class],
        ];
    }

    #[DataProvider('lineModels')]
    public function test_a_percentage_discount_comes_off_before_tax_and_truncates(string $class): void
    {
        $line = $this->calculated($class, [
            'quantity' => '7',
            'unit_price' => '1.2345',
            'discount_type' => 'percentage',
            'discount_value' => '3.3333',
            'tax_rate' => '5',
        ]);

        $this->assertSame('0.2880', $line->discount_amount);
        $this->assertSame('8.3535', $line->subtotal);
        $this->assertSame('0.4176', $line->tax_amount);
        $this->assertSame('8.7711', $line->total);
    }

    #[DataProvider('lineModels')]
    public function test_a_fixed_discount_is_taken_as_given(string $class): void
    {
        $line = $this->calculated($class, [
            'quantity' => '2',
            'unit_price' => '100',
            'discount_type' => 'fixed',
            'discount_value' => '10',
            'tax_rate' => '15',
        ]);

        $this->assertSame('10.0000', $line->discount_amount);
        $this->assertSame('190.0000', $line->subtotal);
        $this->assertSame('28.5000', $line->tax_amount);
        $this->assertSame('218.5000', $line->total);
    }

    #[DataProvider('lineModels')]
    public function test_a_discount_amount_without_a_discount_type_is_cleared(string $class): void
    {
        $line = $this->calculated($class, [
            'quantity' => '1',
            'unit_price' => '10',
            'discount_amount' => '5',
            'tax_rate' => '0',
        ]);

        $this->assertSame('0.0000', $line->discount_amount);
        $this->assertSame('10.0000', $line->subtotal);
        $this->assertSame('0.0000', $line->tax_amount);
        $this->assertSame('10.0000', $line->total);
    }

    #[DataProvider('gstLineModels')]
    public function test_intra_state_gst_taxes_each_half_at_its_own_rate(string $class): void
    {
        $line = $this->calculated($class, [
            'quantity' => '1',
            'unit_price' => '10.003',
            'tax_rate' => '0',
            'cgst_rate' => '2.5',
            'sgst_rate' => '2.5',
        ]);

        $this->assertSame('10.0030', $line->subtotal);
        $this->assertSame('0.2500', $line->cgst_amount);
        $this->assertSame('0.2500', $line->sgst_amount);
        $this->assertSame('0.0000', $line->igst_amount);
        $this->assertSame('0.5000', $line->tax_amount);
        $this->assertSame('10.5030', $line->total);
    }

    #[DataProvider('gstLineModels')]
    public function test_inter_state_gst_replaces_the_tax_rate(string $class): void
    {
        $line = $this->calculated($class, [
            'quantity' => '1',
            'unit_price' => '10.003',
            'tax_rate' => '5',
            'igst_rate' => '18',
        ]);

        $this->assertSame('1.8005', $line->igst_amount);
        $this->assertSame('0.0000', $line->cgst_amount);
        $this->assertSame('0.0000', $line->sgst_amount);
        $this->assertSame('1.8005', $line->tax_amount);
        $this->assertSame('11.8035', $line->total);
    }

    public function test_a_consignment_line_total_includes_truncated_tax(): void
    {
        $line = new ConsignmentOrderLine(['quantity' => '3', 'unit_price' => '19.99', 'tax_rate' => '15']);

        $line->calculateTotal();

        $this->assertSame('68.9655', $line->line_total);
    }

    public function test_tax_determination_truncates_the_simulated_tax(): void
    {
        $result = app(TaxDeterminationService::class)->calculateTax(99.99, ['tax_rate' => 15]);

        $this->assertSame(14.9985, $result['tax_amount']);
        $this->assertSame(114.9885, $result['gross_amount']);
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, string>  $attributes
     */
    private function calculated(string $class, array $attributes): Model
    {
        $line = new $class($attributes);
        $line->calculateTotals();

        return $line;
    }
}
