<?php

use App\Models\Panel;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get legacy settings
        $settings = Setting::pluck('value', 'key');
        $panelType = $settings->get('panel_type', 'marzban');

        // Determine credentials based on panel type
        $username = null;
        $password = null;
        $url = null;
        $extra = [];

        if ($panelType === 'marzban') {
            $url = $settings->get('marzban_host');
            $username = $settings->get('marzban_sudo_username');
            $password = $settings->get('marzban_sudo_password');
            $extra['node_hostname'] = $settings->get('marzban_node_hostname');
        } elseif ($panelType === 'marzneshin') {
            $url = $settings->get('marzneshin_host');
            $username = $settings->get('marzneshin_sudo_username');
            $password = $settings->get('marzneshin_sudo_password');
            $extra['node_hostname'] = $settings->get('marzneshin_node_hostname');
        } elseif ($panelType === 'xui') {
            $url = $settings->get('xui_host');
            $username = $settings->get('xui_user');
            $password = $settings->get('xui_pass');
            $extra['default_inbound_id'] = $settings->get('xui_default_inbound_id');
            $extra['link_type'] = $settings->get('xui_link_type', 'single');
            $extra['subscription_url_base'] = $settings->get('xui_subscription_url_base');
        }

        // Only create default panel if we have valid settings
        if ($url && $username && $password) {
            $defaultPanel = Panel::create([
                'name' => 'Default Panel (Migrated)',
                'url' => $url,
                'panel_type' => $panelType,
                'username' => $username,
                'password' => $password,
                'extra' => $extra,
                'is_active' => true,
            ]);

            // Associate all existing plans without a panel to this default panel
            Plan::whereNull('panel_id')->update(['panel_id' => $defaultPanel->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the migrated default panel
        $defaultPanel = Panel::where('name', 'Default Panel (Migrated)')->first();
        
        if ($defaultPanel) {
            // Dissociate plans from this panel
            Plan::where('panel_id', $defaultPanel->id)->update(['panel_id' => null]);
            
            // Delete the default panel
            $defaultPanel->delete();
        }
    }
};
