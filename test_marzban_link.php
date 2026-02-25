<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\MarzbanService;

// Mock dependencies if needed, but here we just test logic
// We can't easily mock entire Laravel app in a standalone script without bootstrapping
// So I'll just copy the relevant logic into a test class here

class TestMarzbanService
{
    protected string $nodeHostname;

    public function __construct(string $nodeHostname)
    {
        // Clean node hostname
        $nodeHostname = trim($nodeHostname);
        // Remove leading/trailing slashes to handle inputs like "/https://..."
        $nodeHostname = trim($nodeHostname, '/');
        
        // Ensure scheme exists for clickable links if not present
        if (!preg_match("~^(?:f|ht)tps?://~i", $nodeHostname)) {
            $nodeHostname = "https://" . $nodeHostname;
        }

        $this->nodeHostname = $nodeHostname;
        echo "Constructed nodeHostname: " . $this->nodeHostname . "\n";
    }

    public function generateSubscriptionLink(array $userApiResponse): string
    {
        $subscriptionUrl = $userApiResponse['subscription_url'];
        
        // If Marzban returns a full URL, use it directly
        if (preg_match("~^(?:f|ht)tps?://~i", $subscriptionUrl)) {
            return $subscriptionUrl;
        }
        
        // Ensure one slash between host and path
        if (!str_starts_with($subscriptionUrl, '/')) {
            $subscriptionUrl = '/' . $subscriptionUrl;
        }

        return $this->nodeHostname . $subscriptionUrl;
    }
}

// Test Cases
$testCases = [
    ['node' => 'https://example.com', 'sub' => '/sub/123', 'expected' => 'https://example.com/sub/123'],
    ['node' => 'example.com', 'sub' => '/sub/123', 'expected' => 'https://example.com/sub/123'],
    ['node' => '/https://example.com', 'sub' => '/sub/123', 'expected' => 'https://example.com/sub/123'], // The problematic case?
    ['node' => 'https://example.com/', 'sub' => 'sub/123', 'expected' => 'https://example.com/sub/123'],
    ['node' => 'https://example.com', 'sub' => 'https://other.com/sub/123', 'expected' => 'https://other.com/sub/123'],
    ['node' => '', 'sub' => '/sub/123', 'expected' => 'https:///sub/123'], // Empty node -> https://
];

foreach ($testCases as $case) {
    echo "Testing node='{$case['node']}', sub='{$case['sub']}'\n";
    $service = new TestMarzbanService($case['node']);
    $result = $service->generateSubscriptionLink(['subscription_url' => $case['sub']]);
    echo "Result:   $result\n";
    echo "Expected: {$case['expected']}\n";
    echo ($result === $case['expected'] ? "PASS" : "FAIL") . "\n";
    echo "----------------------------------------\n";
}
