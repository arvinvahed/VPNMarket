# Promo/Coupon Codes Feature Guide

## Introduction

This feature provides site administrators with the ability to create and manage promotional/coupon codes. Customers can apply these codes during checkout to receive discounts on their purchases.

## Key Features

- **Discount Types**: Percentage-based or fixed amount
- **Usage Limits**: Total usage limit and per-user limit
- **Validity Period**: Start date and expiration date
- **Plan-Specific Application**: Ability to define codes for specific plans
- **Admin Panel Management**: Simple user interface for managing codes
- **Usage Tracking**: View the number of times each code has been used

## Admin Guide

### Creating a New Promo Code

1. Log in to the admin panel (`/admin`)
2. From the right sidebar menu, select **Marketing > Promo Codes**
3. Click the **Create** button
4. Fill in the form with the following information:

#### Basic Information
- **Code**: Unique code (automatically converted to uppercase)
- **Description**: Optional description for better identification

#### Discount Settings
- **Discount Type**:
  - Percentage: Discount based on percentage (e.g., 10%)
  - Fixed: Discount with a specific amount (e.g., 5000 Toman)
- **Discount Value**: The discount number (for percentage, between 0 and 100)
- **Currency**: For fixed discount (default: Toman)

#### Usage Limits
- **Max Total Uses**: Maximum number of times the code can be used (empty = unlimited)
- **Max Uses Per User**: How many times each user can use the code (empty = unlimited)

#### Validity Period
- **Start Date**: When the code becomes active (empty = immediate)
- **Expiration Date**: When the code expires (empty = never expires)

#### Application Scope
- **Applies To**:
  - All Plans: Code can be used for all plans
  - Specific Plan: Only for a specific plan

#### Status
- **Active**: Enable/disable the code

5. Click **Create**

### Managing Existing Codes

On the promo codes list page, you can:

- **View All Codes**: Table with all code information
- **Search**: Search by code or description
- **Filter**:
  - Active/Inactive
  - Expired or expiring soon
  - Deleted (soft delete)
- **Quick Toggle**: Enable/disable with action button
- **Edit**: Modify code details
- **Delete**: Soft delete (can be restored)
- **Force Delete**: Permanently remove from database

### Example Promo Codes

After running the seeder, the following codes are created in the database:

- **WELCOME10**: 10% welcome discount (max 100 uses, 1 per user)
- **SAVE20**: 20% special discount (max 50 uses, 2 per user, 30 days validity)
- **OFF5000**: 5000 Toman discount (max 20 uses, 15 days validity)

## Customer Guide

### Applying a Promo Code During Checkout

1. After selecting a plan and creating an order, you'll be redirected to the payment page
2. In the **Have a promo code?** section, enter your code
3. Click the **Apply** button
4. If the code is valid:
   - A success message is displayed
   - Original price shown with strikethrough
   - Discount amount displayed
   - Final price calculated and shown
5. You can pay using your preferred method

### Error Messages

If there's an issue, one of the following messages will be displayed:

- **Promo code is not valid**: Code doesn't exist
- **This promo code is inactive**: Code has been deactivated
- **This promo code is not active yet**: Start date hasn't been reached
- **This promo code has expired**: Expiration date has passed
- **This promo code has reached its usage limit**: All uses consumed
- **You have already used this promo code**: Per-user limit reached
- **This promo code cannot be used for this plan**: Code is for a different plan

## Technical Architecture

### Database Tables

#### promo_codes
- `id`: Unique identifier
- `code`: Promo code (unique, uppercase)
- `description`: Description
- `discount_type`: Discount type (percent/fixed)
- `discount_value`: Discount value
- `currency`: Currency unit (for fixed)
- `max_uses`: Maximum total uses
- `max_uses_per_user`: Maximum uses per user
- `uses_count`: Number of times used
- `start_at`: Start date
- `expires_at`: Expiration date
- `active`: Active/inactive status
- `applies_to`: Application type (all/plan/provider)
- `plan_id`: Plan ID (nullable)
- `provider_id`: Provider ID (nullable)
- `created_by_admin_id`: Admin creator ID
- `created_at`, `updated_at`, `deleted_at`

#### orders (new fields)
- `promo_code_id`: Applied promo code ID
- `discount_amount`: Discount amount
- `original_amount`: Original price before discount

### Classes and Services

#### PromoCode Model
Main model with helper methods:
- `isValid()`: Check code validity
- `canBeUsedByUser()`: Check user limit
- `appliesToPlan()`: Check plan applicability
- `calculateDiscount()`: Calculate discount

#### CouponService
Coupon management service with methods:
- `validateCode()`: Validate code
- `calculateDiscount()`: Calculate discount
- `applyToOrder()`: Apply to order
- `removeFromOrder()`: Remove from order
- `incrementUsage()`: Increment usage counter (atomic)

#### PromoCodeResource (Filament)
- Admin UI interface
- Forms with validation
- Table with filters and actions
- Full Persian localization

### API Routes

```php
// Authenticated routes
POST /order/{order}/apply-coupon    // Apply promo code
POST /order/{order}/remove-coupon   // Remove promo code
```

### Tests

Comprehensive tests with Pest:
- Create and validate codes
- Discount calculations (percentage and fixed)
- Apply and remove from order
- Usage limits
- Atomic counter increment
- Per-user limits
- Specific plans

## Setup and Installation

### Migration

```bash
php artisan migrate
```

This command creates the `promo_codes` table and new fields in the `orders` table.

### Seeder

To create example promo codes:

```bash
php artisan db:seed --class=PromoCodeSeeder
```

### Tests

To run the tests:

```bash
php artisan test --filter=PromoCodeTest
```

## Security Notes

- Codes are automatically converted to uppercase
- uses_count counter is incremented atomically using DB::increment to prevent race conditions
- Complete server-side validation
- Only authenticated users can apply codes
- Users can only apply codes to their own orders

## Future Enhancements

- Allow providers to create codes
- Analytics reports on code usage
- CSV export of codes list
- Single-use codes for specific users
- Notifications for codes nearing expiration

## Support

If you encounter issues or have questions:
1. Check Laravel logs first (`storage/logs/laravel.log`)
2. Ensure migrations have been run
3. Run tests to verify functionality
