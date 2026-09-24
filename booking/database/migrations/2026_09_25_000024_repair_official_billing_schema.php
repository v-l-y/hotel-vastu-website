<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('billing_document_sequences')) {
            Schema::create('billing_document_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('series', 2);
                $table->string('financial_year', 5);
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['series', 'financial_year']);
            });
        }

        $this->repairGuestBillingColumns('guests');
        $this->repairGuestBillingColumns('booking_verifications');
        $this->repairRestaurantBillingColumns();
        $this->repairInvoiceColumns();
        $this->repairInvoiceItemColumns();
    }

    private function repairGuestBillingColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $missing = array_values(array_filter(
            ['gstin', 'billing_address', 'billing_state', 'billing_state_code'],
            fn (string $column): bool => ! Schema::hasColumn($tableName, $column)
        ));

        if ($missing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($missing) {
            if (in_array('gstin', $missing, true)) {
                $table->string('gstin', 20)->nullable();
            }
            if (in_array('billing_address', $missing, true)) {
                $table->text('billing_address')->nullable();
            }
            if (in_array('billing_state', $missing, true)) {
                $table->string('billing_state', 100)->nullable();
            }
            if (in_array('billing_state_code', $missing, true)) {
                $table->string('billing_state_code', 2)->nullable();
            }
        });
    }

    private function repairRestaurantBillingColumns(): void
    {
        if (! Schema::hasTable('restaurant_orders')) {
            return;
        }

        $missing = array_values(array_filter(
            [
                'guest_email',
                'guest_gstin',
                'guest_billing_address',
                'guest_billing_state',
                'guest_billing_state_code',
            ],
            fn (string $column): bool => ! Schema::hasColumn('restaurant_orders', $column)
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('restaurant_orders', function (Blueprint $table) use ($missing) {
            if (in_array('guest_email', $missing, true)) {
                $table->string('guest_email', 190)->nullable();
            }
            if (in_array('guest_gstin', $missing, true)) {
                $table->string('guest_gstin', 20)->nullable();
            }
            if (in_array('guest_billing_address', $missing, true)) {
                $table->text('guest_billing_address')->nullable();
            }
            if (in_array('guest_billing_state', $missing, true)) {
                $table->string('guest_billing_state', 100)->nullable();
            }
            if (in_array('guest_billing_state_code', $missing, true)) {
                $table->string('guest_billing_state_code', 2)->nullable();
            }
        });
    }

    private function repairInvoiceColumns(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $restaurantOrderColumnMissing = ! Schema::hasColumn('invoices', 'restaurant_order_id');

        if ($restaurantOrderColumnMissing && Schema::hasColumn('invoices', 'folio_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('folio_id')->nullable()->change();
            });
        }

        $columns = [
            'public_token',
            'document_type',
            'restaurant_order_id',
            'is_gst_invoice',
            'supplier_name',
            'supplier_address',
            'supplier_gstin',
            'supplier_state',
            'supplier_state_code',
            'supplier_phone',
            'supplier_email',
            'recipient_name',
            'recipient_phone',
            'recipient_email',
            'recipient_gstin',
            'recipient_address',
            'recipient_state',
            'recipient_state_code',
            'place_of_supply',
            'place_of_supply_code',
            'reverse_charge',
            'cgst',
            'sgst',
            'igst',
            'last_sent_at',
        ];

        $missing = array_values(array_filter(
            $columns,
            fn (string $column): bool => ! Schema::hasColumn('invoices', $column)
        ));

        if ($missing !== []) {
            Schema::table('invoices', function (Blueprint $table) use ($missing) {
                if (in_array('public_token', $missing, true)) {
                    $table->uuid('public_token')->nullable()->unique();
                }
                if (in_array('document_type', $missing, true)) {
                    $table->string('document_type', 30)->default('hotel');
                }
                if (in_array('restaurant_order_id', $missing, true)) {
                    $table->foreignId('restaurant_order_id')
                        ->nullable()
                        ->constrained('restaurant_orders')
                        ->restrictOnDelete();
                    $table->unique('restaurant_order_id');
                }
                if (in_array('is_gst_invoice', $missing, true)) {
                    $table->boolean('is_gst_invoice')->default(false);
                }
                if (in_array('supplier_name', $missing, true)) {
                    $table->string('supplier_name', 160)->nullable();
                }
                if (in_array('supplier_address', $missing, true)) {
                    $table->text('supplier_address')->nullable();
                }
                if (in_array('supplier_gstin', $missing, true)) {
                    $table->string('supplier_gstin', 20)->nullable();
                }
                if (in_array('supplier_state', $missing, true)) {
                    $table->string('supplier_state', 100)->nullable();
                }
                if (in_array('supplier_state_code', $missing, true)) {
                    $table->string('supplier_state_code', 2)->nullable();
                }
                if (in_array('supplier_phone', $missing, true)) {
                    $table->string('supplier_phone', 30)->nullable();
                }
                if (in_array('supplier_email', $missing, true)) {
                    $table->string('supplier_email', 190)->nullable();
                }
                if (in_array('recipient_name', $missing, true)) {
                    $table->string('recipient_name', 200)->nullable();
                }
                if (in_array('recipient_phone', $missing, true)) {
                    $table->string('recipient_phone', 30)->nullable();
                }
                if (in_array('recipient_email', $missing, true)) {
                    $table->string('recipient_email', 190)->nullable();
                }
                if (in_array('recipient_gstin', $missing, true)) {
                    $table->string('recipient_gstin', 20)->nullable();
                }
                if (in_array('recipient_address', $missing, true)) {
                    $table->text('recipient_address')->nullable();
                }
                if (in_array('recipient_state', $missing, true)) {
                    $table->string('recipient_state', 100)->nullable();
                }
                if (in_array('recipient_state_code', $missing, true)) {
                    $table->string('recipient_state_code', 2)->nullable();
                }
                if (in_array('place_of_supply', $missing, true)) {
                    $table->string('place_of_supply', 100)->nullable();
                }
                if (in_array('place_of_supply_code', $missing, true)) {
                    $table->string('place_of_supply_code', 2)->nullable();
                }
                if (in_array('reverse_charge', $missing, true)) {
                    $table->boolean('reverse_charge')->default(false);
                }
                if (in_array('cgst', $missing, true)) {
                    $table->decimal('cgst', 12, 2)->default(0);
                }
                if (in_array('sgst', $missing, true)) {
                    $table->decimal('sgst', 12, 2)->default(0);
                }
                if (in_array('igst', $missing, true)) {
                    $table->decimal('igst', 12, 2)->default(0);
                }
                if (in_array('last_sent_at', $missing, true)) {
                    $table->timestamp('last_sent_at')->nullable();
                }
            });
        }

        if (Schema::hasColumn('invoices', 'public_token')) {
            DB::table('invoices')
                ->whereNull('public_token')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('invoices')
                            ->where('id', $row->id)
                            ->update(['public_token' => (string) Str::uuid()]);
                    }
                });
        }
    }

    private function repairInvoiceItemColumns(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        $columns = [
            'sac_code',
            'tax_rate_percent',
            'cgst_rate_percent',
            'cgst_amount',
            'sgst_rate_percent',
            'sgst_amount',
            'igst_rate_percent',
            'igst_amount',
        ];

        $missing = array_values(array_filter(
            $columns,
            fn (string $column): bool => ! Schema::hasColumn('invoice_items', $column)
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) use ($missing) {
            if (in_array('sac_code', $missing, true)) {
                $table->string('sac_code', 20)->nullable();
            }
            if (in_array('tax_rate_percent', $missing, true)) {
                $table->decimal('tax_rate_percent', 7, 4)->default(0);
            }
            if (in_array('cgst_rate_percent', $missing, true)) {
                $table->decimal('cgst_rate_percent', 7, 4)->default(0);
            }
            if (in_array('cgst_amount', $missing, true)) {
                $table->decimal('cgst_amount', 12, 2)->default(0);
            }
            if (in_array('sgst_rate_percent', $missing, true)) {
                $table->decimal('sgst_rate_percent', 7, 4)->default(0);
            }
            if (in_array('sgst_amount', $missing, true)) {
                $table->decimal('sgst_amount', 12, 2)->default(0);
            }
            if (in_array('igst_rate_percent', $missing, true)) {
                $table->decimal('igst_rate_percent', 7, 4)->default(0);
            }
            if (in_array('igst_amount', $missing, true)) {
                $table->decimal('igst_amount', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        // Repair-only migration. Do not remove billing columns during rollback.
    }
};
