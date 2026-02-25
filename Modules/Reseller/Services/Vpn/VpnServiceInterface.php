<?php

namespace Modules\Reseller\Services\Vpn;

use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Models\VpnProduct;

interface VpnServiceInterface
{
    /**
     * Create a new VPN account on the server.
     * 
     * @param VpnServer $server
     * @param VpnProduct $product
     * @param string $username
     * @param string|null $uuid
     * @return array Result with 'success' => bool, 'data' => array (including subscription link, uuid, etc), 'error' => string
     */
    public function createAccount(VpnServer $server, VpnProduct $product, string $username, ?string $uuid = null): array;

    /**
     * Delete an account from the server.
     * 
     * @param VpnServer $server
     * @param string $identifier (Username for Marzban, UUID for Sanaei)
     * @param VpnProduct|null $product (Required for Sanaei to get Inbound ID)
     */
    public function deleteAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): bool;

    /**
     * Get account details.
     * 
     * @param VpnServer $server
     * @param string $identifier
     * @param VpnProduct|null $product
     */
    public function getAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): ?array;

    /**
     * Renew/Extend an account.
     * 
     * @param VpnServer $server
     * @param string $identifier
     * @param VpnProduct $product
     * @param int $daysToAdd
     * @param int|null $trafficLimit (GB, null to keep existing or reset to product default)
     * @return bool
     */
    public function renewAccount(VpnServer $server, string $identifier, VpnProduct $product, int $daysToAdd, ?int $trafficLimit = null): bool;
}
