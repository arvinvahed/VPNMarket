<?php

namespace Modules\Reseller\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Reseller\Models\ResellerRequest;
use Modules\Reseller\Models\ResellerPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramResellerController extends Controller
{
    /**
     * Submit reseller application via Telegram bot.
     */
    public function submitApplication(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:reseller_plans,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'telegram_username' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'payment_amount' => 'required|numeric|min:0',
            'payment_receipt_path' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $user = User::findOrFail($request->user_id);
            $plan = ResellerPlan::findOrFail($request->plan_id);

            // Check if user already has an active reseller request
            $existingRequest = ResellerRequest::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active reseller request.'
                ], 400);
            }

            // Create reseller request
            $resellerRequest = ResellerRequest::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'telegram_username' => $request->telegram_username,
                'description' => $request->description,
                'payment_amount' => $request->payment_amount,
                'payment_receipt_path' => $request->payment_receipt_path,
                'status' => 'pending',
            ]);

            DB::commit();

            Log::info('New reseller request submitted via Telegram', [
                'user_id' => $user->id,
                'request_id' => $resellerRequest->id,
                'plan' => $plan->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reseller application submitted successfully. We will review your request soon.',
                'request_id' => $resellerRequest->id,
                'status' => 'pending'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Failed to submit reseller application', [
                'error' => $e->getMessage(),
                'user_id' => $request->user_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application. Please try again.'
            ], 500);
        }
    }

    /**
     * Get reseller application status.
     */
    public function getApplicationStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        
        $latestRequest = ResellerRequest::where('user_id', $user->id)
            ->with('plan')
            ->latest()
            ->first();

        if (!$latestRequest) {
            return response()->json([
                'has_request' => false,
                'message' => 'No reseller application found.'
            ]);
        }

        return response()->json([
            'has_request' => true,
            'request' => [
                'id' => $latestRequest->id,
                'status' => $latestRequest->status,
                'plan_name' => $latestRequest->plan->name,
                'plan_type' => $latestRequest->plan->type,
                'submitted_at' => $latestRequest->created_at->toDateTimeString(),
                'rejection_reason' => $latestRequest->rejection_reason,
            ]
        ]);
    }

    /**
     * Get available reseller plans for Telegram bot.
     */
    public function getAvailablePlans(Request $request)
    {
        $plans = ResellerPlan::where('is_active', true)
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'type' => $plan->type,
                    'price' => (float) $plan->price,
                    'price_per_account' => (float) $plan->price_per_account,
                    'account_limit' => $plan->account_limit,
                    'description' => $plan->description,
                ];
            });

        return response()->json([
            'plans' => $plans,
            'message' => 'Choose a plan that suits your needs.'
        ]);
    }
}