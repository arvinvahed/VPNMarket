<?php

namespace Modules\Reseller\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;

class QrCodeService
{
    /**
     * Generate QR code for VPN account subscription URL.
     */
    public function generateVpnQrCode(string $subscriptionUrl, string $accountName = 'VPN Account'): string
    {
        $writer = new PngWriter();
        
        // Create QR code
        $qrCode = new QrCode(
            data: $subscriptionUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );
        
        // Create label
        $label = new Label(
            text: $accountName,
            font: new OpenSans(12),
            textColor: new Color(0, 0, 0),
            alignment: LabelAlignment::Center
        );
        
        $result = $writer->write($qrCode, label: $label);
        
        return $result->getString();
    }

    /**
     * Generate QR code for VPN account and return base64 encoded image.
     */
    public function generateVpnQrCodeBase64(string $subscriptionUrl, string $accountName = 'VPN Account'): string
    {
        $qrCodeData = $this->generateVpnQrCode($subscriptionUrl, $accountName);
        return 'data:image/png;base64,' . base64_encode($qrCodeData);
    }

    /**
     * Generate QR codes for multiple VPN configurations.
     */
    public function generateMultipleQrCodes(array $configs): array
    {
        $results = [];
        
        foreach ($configs as $config) {
            $name = $config['name'] ?? 'VPN Account';
            $url = $config['subscription_url'] ?? $config['url'] ?? '';
            
            if (empty($url)) {
                continue;
            }
            
            $results[] = [
                'name' => $name,
                'qr_code' => $this->generateVpnQrCodeBase64($url, $name),
                'url' => $url,
            ];
        }
        
        return $results;
    }
}