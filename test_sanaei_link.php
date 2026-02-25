<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Reseller\Models\VpnServer;
use Illuminate\Support\Facades\Http;

$subUrl = "https://v.savak.space:2096/sub/lmp5I4dbumsGyzv8";
echo "Testing URL: $subUrl\n";

try {
    $response = Http::withOptions(['verify' => false, 'timeout' => 10])->get($subUrl);
    
    if ($response->successful()) {
        $body = trim($response->body());
        echo "Raw Body Start: " . substr($body, 0, 50) . "\n";
        
        $decoded = base64_decode($body); // Non-strict
        
        if ($decoded && preg_match('/(vless|vmess|trojan|ss):\/\//i', $decoded)) {
            echo "SUCCESS: Decoded valid config list.\n";
            $body = $decoded;
        } else {
            echo "Not base64 or failed decode check.\n";
        }

        if (preg_match('/(vless|vmess|trojan|ss):\/\/[^\s"<]+/i', $body, $m)) {
            echo "FINAL RESULT: Found config link:\n" . $m[0] . "\n";
        } else {
            echo "FINAL RESULT: No config link found.\n";
        }

    } else {
        echo "Request failed: " . $response->status() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
