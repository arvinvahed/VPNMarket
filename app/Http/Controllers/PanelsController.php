<?php

namespace App\Http\Controllers;

use App\Models\Panel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PanelsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $panels = Panel::all()->map(function ($panel) {
            return [
                'id' => $panel->id,
                'name' => $panel->name,
                'url' => $panel->url,
                'panel_type' => $panel->panel_type,
                'username' => $panel->username,
                'has_password' => !empty($panel->password),
                'has_api_token' => !empty($panel->api_token),
                'extra' => $panel->extra,
                'is_active' => $panel->is_active,
                'created_at' => $panel->created_at,
                'updated_at' => $panel->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $panels,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:255',
            'panel_type' => ['required', Rule::in(['marzban', 'marzneshin', 'xui', 'v2ray', 'other'])],
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'api_token' => 'nullable|string',
            'extra' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $panel = Panel::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Panel created successfully',
            'data' => [
                'id' => $panel->id,
                'name' => $panel->name,
                'url' => $panel->url,
                'panel_type' => $panel->panel_type,
                'username' => $panel->username,
                'has_password' => !empty($panel->password),
                'has_api_token' => !empty($panel->api_token),
                'extra' => $panel->extra,
                'is_active' => $panel->is_active,
                'created_at' => $panel->created_at,
                'updated_at' => $panel->updated_at,
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Panel $panel)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $panel->id,
                'name' => $panel->name,
                'url' => $panel->url,
                'panel_type' => $panel->panel_type,
                'username' => $panel->username,
                'has_password' => !empty($panel->password),
                'has_api_token' => !empty($panel->api_token),
                'extra' => $panel->extra,
                'is_active' => $panel->is_active,
                'created_at' => $panel->created_at,
                'updated_at' => $panel->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Panel $panel)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|required|url|max:255',
            'panel_type' => ['sometimes', 'required', Rule::in(['marzban', 'marzneshin', 'xui', 'v2ray', 'other'])],
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'api_token' => 'nullable|string',
            'extra' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
        // Only update password if provided
        if (!isset($data['password']) || $data['password'] === '') {
            unset($data['password']);
        }
        
        // Only update api_token if provided
        if (!isset($data['api_token']) || $data['api_token'] === '') {
            unset($data['api_token']);
        }

        $panel->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Panel updated successfully',
            'data' => [
                'id' => $panel->id,
                'name' => $panel->name,
                'url' => $panel->url,
                'panel_type' => $panel->panel_type,
                'username' => $panel->username,
                'has_password' => !empty($panel->password),
                'has_api_token' => !empty($panel->api_token),
                'extra' => $panel->extra,
                'is_active' => $panel->is_active,
                'created_at' => $panel->created_at,
                'updated_at' => $panel->updated_at,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Panel $panel)
    {
        // Check if panel has associated plans
        if ($panel->plans()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete panel with associated plans. Please reassign or delete the plans first.',
            ], 422);
        }

        $panel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Panel deleted successfully',
        ]);
    }

    /**
     * Test panel connectivity (optional)
     */
    public function testConnection(Panel $panel)
    {
        // This is a placeholder for connectivity testing
        // Actual implementation would depend on panel type
        return response()->json([
            'success' => true,
            'message' => 'Connectivity test endpoint - implementation pending',
            'panel_id' => $panel->id,
        ]);
    }
}
