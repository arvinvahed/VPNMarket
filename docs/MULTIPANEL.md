# Multi-Panel Management System

## Overview

The VPNMarket application now supports managing multiple VPN panels (Marzban, Marzneshin, X-UI, etc.) instead of being limited to a single global panel configuration. This allows administrators to:

- Configure multiple panels with different types and credentials
- Assign specific panels to individual service plans
- Manage panel credentials securely with encryption
- Seamlessly migrate from legacy single-panel setup

## Features

### Panel Management

- **Multiple Panel Support**: Add and manage unlimited VPN panels
- **Panel Types**: Support for Marzban, Marzneshin, X-UI, V2Ray, and custom panels
- **Secure Credentials**: Passwords and API tokens are encrypted using Laravel's encryption
- **Panel-Specific Configuration**: Each panel can have custom settings via the `extra` JSON field
- **Active/Inactive Status**: Enable or disable panels without deleting them

### Plan-Panel Association

- **Flexible Assignment**: Each service plan can be associated with a specific panel
- **Dynamic Service Selection**: For Marzneshin panels, service IDs can be selected per plan
- **Automatic Migration**: Legacy settings are automatically converted to a default panel

## API Endpoints

All panel management endpoints require admin authentication.

### List All Panels
```
GET /api/admin/panels
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Default Panel",
      "url": "https://panel.example.com",
      "panel_type": "marzban",
      "username": "admin",
      "has_password": true,
      "has_api_token": false,
      "extra": {
        "node_hostname": "https://node.example.com"
      },
      "is_active": true,
      "created_at": "2025-10-18T20:00:00.000000Z",
      "updated_at": "2025-10-18T20:00:00.000000Z"
    }
  ]
}
```

### Get Single Panel
```
GET /api/admin/panels/{id}
```

### Create Panel
```
POST /api/admin/panels
```

**Request Body:**
```json
{
  "name": "My Panel",
  "url": "https://panel.example.com",
  "panel_type": "marzban",
  "username": "admin",
  "password": "secret123",
  "api_token": "optional_token",
  "extra": {
    "node_hostname": "https://node.example.com"
  },
  "is_active": true
}
```

**Validation Rules:**
- `name`: required, string, max 255 characters
- `url`: required, valid URL, max 255 characters
- `panel_type`: required, one of: marzban, marzneshin, xui, v2ray, other
- `username`: optional, string, max 255 characters
- `password`: optional, string (encrypted on save)
- `api_token`: optional, string (encrypted on save)
- `extra`: optional, JSON object
- `is_active`: optional, boolean (default: true)

### Update Panel
```
PUT /api/admin/panels/{id}
```

**Note:** Passwords and API tokens are only updated if provided in the request. Send empty string to keep existing values.

### Delete Panel
```
DELETE /api/admin/panels/{id}
```

**Note:** Cannot delete a panel that has associated plans. Reassign or delete plans first.

### Test Panel Connection (Optional)
```
POST /api/admin/panels/{id}/test-connection
```

## Database Schema

### Panels Table

```sql
CREATE TABLE panels (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    panel_type ENUM('marzban', 'marzneshin', 'xui', 'v2ray', 'other') DEFAULT 'marzban',
    username VARCHAR(255) NULL,
    password TEXT NULL,  -- Encrypted
    api_token TEXT NULL, -- Encrypted
    extra JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Plans Table (Updated)

```sql
ALTER TABLE plans 
ADD COLUMN panel_id BIGINT NULL,
ADD FOREIGN KEY (panel_id) REFERENCES panels(id) ON DELETE SET NULL;
```

## Panel Extra Configuration

The `extra` JSON field stores panel-specific configuration:

### Marzban/Marzneshin
```json
{
  "node_hostname": "https://node.example.com"
}
```

### X-UI
```json
{
  "default_inbound_id": "1",
  "link_type": "subscription",
  "subscription_url_base": "https://sub.example.com"
}
```

## Migration from Legacy Settings

When upgrading, the system automatically:

1. Reads legacy panel settings from the `settings` table
2. Creates a "Default Panel (Migrated)" with those credentials
3. Associates all existing plans without a panel to this default panel
4. Preserves panel-specific configuration in the `extra` field

**Legacy Settings Migrated:**
- `panel_type` → `panel_type`
- `marzban_host` → `url`
- `marzban_sudo_username` → `username`
- `marzban_sudo_password` → `password` (encrypted)
- `marzban_node_hostname` → `extra.node_hostname`
- Similar for Marzneshin and X-UI panels

## Admin Panel Usage

### Managing Panels (Filament)

1. Navigate to **Panels** in the admin sidebar
2. Click **New Panel** to add a panel
3. Fill in the panel details:
   - Name: A descriptive name for the panel
   - URL: The panel's base URL
   - Panel Type: Select from available types
   - Username: Admin username for the panel
   - Password: Admin password (masked input)
   - API Token: Optional API token (masked input)
   - Extra Settings: Key-value pairs for additional configuration
   - Active: Toggle to enable/disable the panel

### Assigning Panels to Plans

1. Navigate to **Plans** in the admin sidebar
2. Edit an existing plan or create a new one
3. Select the desired panel from the **Panel** dropdown
4. For Marzneshin panels, the **Marzneshin Services** section will appear
5. Select the services this plan should have access to
6. Save the plan

## Security Considerations

### Credential Encryption

Passwords and API tokens are automatically encrypted using Laravel's `Crypt` facade before being stored in the database. They are:

- Encrypted when set via model attributes
- Decrypted when accessed via model attributes
- Hidden from JSON serialization
- Never exposed in API responses (only `has_password` and `has_api_token` flags are returned)

### Access Control

- All panel management endpoints require admin authentication
- Admin middleware checks the `is_admin` flag on the user model
- Non-admin users receive a 403 Forbidden response

### API Response Masking

API responses never include plaintext passwords or tokens. Instead:
```json
{
  "has_password": true,
  "has_api_token": false
}
```

## Order Processing

When an order is processed (either via wallet payment or admin approval):

1. The system retrieves the plan's associated panel
2. Panel credentials are decrypted and used to create/update the VPN user
3. For Marzneshin panels, plan-specific service IDs are included in the API request
4. The generated subscription link is stored with the order

## Testing

The implementation includes comprehensive tests:

### Model Tests
- Panel creation and persistence
- Password/API token encryption
- Credential hiding in JSON
- Panel-Plan relationship

### API Tests
- Admin authentication requirement
- CRUD operations
- Validation rules
- Panel deletion with associated plans

### Migration Tests
- Legacy settings conversion
- Plan association
- Rollback functionality

Run tests with:
```bash
php artisan test --filter=PanelTest
php artisan test --filter=PanelMigrationTest
```

## Troubleshooting

### Panel Not Available in Plan Dropdown

Ensure:
- Panel is marked as active
- Panel credentials are correctly configured
- Database migrations have been run

### Order Processing Fails

Check:
- Plan has an associated panel
- Panel credentials are correct
- Panel URL is accessible
- For X-UI: Inbound ID exists in `extra` configuration

### Migration Not Creating Default Panel

The migration only creates a default panel if:
- `panel_type` setting exists
- Corresponding credentials (`username`, `password`) exist
- URL setting exists for the panel type

## Future Enhancements

Potential improvements:
- Panel health monitoring
- Automatic credential rotation
- Panel usage statistics
- Load balancing across multiple panels
- Real-time panel connectivity testing
- Panel failover support

## Related Files

- `app/Models/Panel.php` - Panel model with encryption
- `app/Http/Controllers/PanelsController.php` - API controller
- `app/Filament/Resources/PanelResource.php` - Admin UI
- `database/migrations/*_create_panels_table.php` - Schema
- `database/migrations/*_migrate_legacy_panel_settings_to_default_panel.php` - Migration
- `tests/Feature/PanelTest.php` - API tests
- `tests/Feature/PanelMigrationTest.php` - Migration tests
