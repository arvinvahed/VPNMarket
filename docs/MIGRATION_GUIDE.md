# Migration Guide: Legacy V2Ray Settings to Multipanel System

## Overview

This guide helps you migrate from the legacy single V2Ray panel configuration to the new multipanel management system.

## What Changed?

### Before (Legacy System)
- Single panel configuration in Settings → V2Ray tab
- All plans used the same global panel settings
- Panel type, credentials stored in `settings` table
- Manual configuration required for each panel type

### After (New Multipanel System)
- Multiple panels can be configured and managed
- Each plan can be assigned to a specific panel
- Panel credentials encrypted and secured
- Flexible configuration via API and Filament admin

## Migration Process

The migration is **automatic** when you run the migrations. Here's what happens:

### Step 1: Automatic Migration

When you run `php artisan migrate`, the system will:

1. Read your current panel settings from the `settings` table
2. Create a new "Default Panel (Migrated)" with your existing credentials
3. Associate all existing plans to this default panel
4. Preserve all panel-specific configuration

### Step 2: Verification

After migration, verify the default panel was created:

```bash
# Check database
php artisan tinker
>>> \App\Models\Panel::where('name', 'Default Panel (Migrated)')->first();
```

Or via Filament admin:
1. Login to admin panel
2. Navigate to **Panels** section
3. You should see "Default Panel (Migrated)"

### Step 3: (Optional) Rename Default Panel

The migrated panel has a generic name. You can rename it:

1. Go to **Panels** in admin
2. Edit "Default Panel (Migrated)"
3. Change name to something meaningful (e.g., "Production Marzban Panel")
4. Save changes

## Post-Migration Tasks

### For Marzban Panels

Your legacy settings:
```
panel_type: marzban
marzban_host: https://panel.example.com
marzban_sudo_username: admin
marzban_sudo_password: secret123
marzban_node_hostname: https://node.example.com
```

Will be migrated to:
```json
{
  "name": "Default Panel (Migrated)",
  "url": "https://panel.example.com",
  "panel_type": "marzban",
  "username": "admin",
  "password": "secret123",  // Encrypted
  "extra": {
    "node_hostname": "https://node.example.com"
  }
}
```

### For Marzneshin Panels

Your legacy settings:
```
panel_type: marzneshin
marzneshin_host: https://panel.example.com
marzneshin_sudo_username: admin
marzneshin_sudo_password: secret123
marzneshin_node_hostname: https://node.example.com
```

Will be migrated to:
```json
{
  "name": "Default Panel (Migrated)",
  "url": "https://panel.example.com",
  "panel_type": "marzneshin",
  "username": "admin",
  "password": "secret123",  // Encrypted
  "extra": {
    "node_hostname": "https://node.example.com"
  }
}
```

### For X-UI Panels

Your legacy settings:
```
panel_type: xui
xui_host: https://panel.example.com
xui_user: admin
xui_pass: secret123
xui_default_inbound_id: 1
xui_link_type: subscription
xui_subscription_url_base: https://sub.example.com
```

Will be migrated to:
```json
{
  "name": "Default Panel (Migrated)",
  "url": "https://panel.example.com",
  "panel_type": "xui",
  "username": "admin",
  "password": "secret123",  // Encrypted
  "extra": {
    "default_inbound_id": "1",
    "link_type": "subscription",
    "subscription_url_base": "https://sub.example.com"
  }
}
```

## Adding Additional Panels

After migration, you can add more panels:

### Via Filament Admin

1. Navigate to **Panels**
2. Click **New Panel**
3. Fill in the details:
   - **Name**: Descriptive name (e.g., "Backup Panel")
   - **URL**: Panel base URL
   - **Panel Type**: Select panel type
   - **Username**: Admin username
   - **Password**: Admin password
   - **Extra Settings**: Add key-value pairs as needed
   - **Active**: Toggle to enable
4. Click **Create**

### Via API

```bash
curl -X POST https://your-site.com/api/admin/panels \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Backup Panel",
    "url": "https://backup-panel.example.com",
    "panel_type": "marzban",
    "username": "admin",
    "password": "secret456",
    "extra": {
      "node_hostname": "https://backup-node.example.com"
    },
    "is_active": true
  }'
```

## Assigning Panels to Plans

After adding panels, assign them to plans:

1. Navigate to **Plans**
2. Edit a plan
3. Select the desired panel from the **Panel** dropdown
4. For Marzneshin panels, select services if needed
5. Save

## Troubleshooting

### Migration Didn't Create Default Panel

**Possible causes:**
- Missing credentials in legacy settings
- Invalid panel type in settings

**Solution:**
1. Check your `settings` table has:
   - `panel_type` key
   - Corresponding username/password keys
   - URL key for that panel type
2. Manually create a panel via Filament admin

### Plans Don't Have Panel Assigned

**Solution:**
1. Edit each plan in Filament admin
2. Select a panel from the dropdown
3. Save

### Orders Fail with "No panel associated"

**Solution:**
Ensure all active plans have a panel assigned:
```bash
php artisan tinker
>>> \App\Models\Plan::whereNull('panel_id')->update(['panel_id' => 1]);
```

## Rollback (Emergency Only)

If you need to rollback the migration:

```bash
php artisan migrate:rollback --step=1
```

**Warning:** This will:
- Delete the default migrated panel
- Set all plans' `panel_id` to NULL
- You'll need to reconfigure panels manually

## Legacy Settings Cleanup

After successful migration and verification, you can optionally remove legacy settings:

```bash
php artisan tinker
>>> \App\Models\Setting::whereIn('key', [
...   'panel_type',
...   'marzban_host', 'marzban_sudo_username', 'marzban_sudo_password', 'marzban_node_hostname',
...   'marzneshin_host', 'marzneshin_sudo_username', 'marzneshin_sudo_password', 'marzneshin_node_hostname',
...   'xui_host', 'xui_user', 'xui_pass', 'xui_default_inbound_id', 'xui_link_type', 'xui_subscription_url_base'
... ])->delete();
```

**Note:** Only do this after confirming everything works with the new system.

## Benefits of New System

1. **Multiple Panels**: Run multiple panels for redundancy or load balancing
2. **Plan Flexibility**: Different plans can use different panels
3. **Better Security**: Encrypted credentials, masked in API responses
4. **Easier Management**: Centralized panel management in one place
5. **Scalability**: Add/remove panels without code changes

## Support

For issues or questions:
1. Check the logs: `storage/logs/laravel.log`
2. Run tests: `php artisan test --filter=Panel`
3. Review documentation: `docs/MULTIPANEL.md`
4. Check panel connectivity via admin panel

## Deployment Checklist

- [ ] Backup database
- [ ] Run migrations: `php artisan migrate`
- [ ] Verify default panel created
- [ ] Test order creation
- [ ] Verify panel credentials work
- [ ] Assign panels to all active plans
- [ ] Test with real orders (in staging first)
- [ ] Monitor logs for errors
- [ ] Update any custom scripts using old settings
- [ ] Document your panel configurations

## Timeline

Recommended migration timeline:

1. **Day 1**: Run migrations in staging, test thoroughly
2. **Day 2-3**: Test all order flows, verify configurations
3. **Day 4**: Run migrations in production (during low-traffic window)
4. **Day 5-7**: Monitor closely, be ready to rollback if needed
5. **Week 2+**: Add additional panels, optimize configurations

## Conclusion

The migration is designed to be seamless and automatic. Your existing configuration will be preserved, and you can continue using your current panel exactly as before, while gaining the flexibility to add more panels in the future.
