<?php

namespace App\Http\Controllers;

use App\Models\DispatchBatch;
use App\Models\Order;
use App\Services\DispatchPlannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchBatchController extends Controller
{
    public function __construct(private DispatchPlannerService $planner) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'orders' => 'required|array',
            'orders.*.order_id' => 'required|string',
            'orders.*.placed_at' => 'required|date',
            'orders.*.destination_address' => 'required|string',
            'orders.*.destination_country' => 'required|in:IN,DE',
            'orders.*.total_value' => 'required|numeric',
            'orders.*.total_value_currency' => 'required|string|size:3',
            'orders.*.weight_grams' => 'required|integer',
            'orders.*.payment_mode' => 'required|in:prepaid,cod',
        ]);

        DB::beginTransaction();
        try {
            $batch = DispatchBatch::create();

            foreach ($data['orders'] as $orderData) {
                $batch->orders()->create($orderData);
            }

            try {
                $this->planner->processBatch($batch);
            } catch (\Exception $e) {
                // Log the error but don't fail the batch creation
                \Illuminate\Support\Facades\Log::error('Batch processing error: ' . $e->getMessage());
                $batch->update(['status' => 'failed']);
            }

            DB::commit();

            return response()->json(['batch_id' => $batch->id], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Batch creation error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to process batch: ' . $e->getMessage()], 500);
        }
    }

    public function show(DispatchBatch $batch)
    {
        $runs = $batch->dispatchRuns->map(function ($run) {
            return [
                'run_id' => $run->id,
                'city' => $run->city,
                'country' => $run->country,
                'dispatch_date' => $run->dispatch_date->toDateString(),
                'orders' => $run->orders->map(function ($order) {
                    return [
                        'order_id' => $order->order_id,
                        'placed_at' => $order->placed_at,
                        'destination_address' => $order->destination_address,
                        'destination_country' => $order->destination_country,
                        'total_value' => $order->total_value,
                        'total_value_currency' => $order->total_value_currency,
                        'weight_grams' => $order->weight_grams,
                        'payment_mode' => $order->payment_mode,
                        'invoiced_value_local' => $order->invoiced_value_local,
                        'invoiced_value_currency' => $order->invoiced_value_currency,
                    ];
                }),
                'weather_summary' => $run->weather_summary,
                'total_invoiced_value_local' => $run->total_invoiced_value_local,
                'total_invoiced_value_currency' => $run->total_invoiced_value_currency,
            ];
        });

        $deferredOrders = $batch->orders->where('is_deferred', true)->map(function ($order) {
            return [
                'order_id' => $order->order_id,
                'reason' => $order->deferred_reason,
            ];
        });

        $failedOrders = $batch->orders->where('status', 'failed')->map(function ($order) {
            return [
                'order_id' => $order->order_id,
                'error' => $order->error_message,
            ];
        });

        return response()->json([
            'batch_id' => $batch->id,
            'processed_at' => $batch->processed_at,
            'runs' => $runs,
            'deferred_orders' => $deferredOrders,
            'failed_orders' => $failedOrders,
        ]);
    }

    public function recompute(DispatchBatch $batch)
    {
        // Delete existing runs and reset orders
        $batch->dispatchRuns()->delete();
        $batch->orders()->update([
            'geocoded_city' => null,
            'geocoded_lat' => null,
            'geocoded_lon' => null,
            'planned_dispatch_date' => null,
            'is_deferred' => false,
            'deferred_reason' => null,
            'invoiced_value_local' => null,
            'invoiced_value_currency' => null,
            'status' => 'pending',
            'error_message' => null,
        ]);

        $this->planner->processBatch($batch);

        return response()->json(['message' => 'Recomputed']);
    }
}
