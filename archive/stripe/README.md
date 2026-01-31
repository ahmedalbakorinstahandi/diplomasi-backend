# Stripe Code Archive

This directory contains archived Stripe-related code that has been replaced with Geidea payment integration.

## Archived Files

- `StripeService.php` - Original Stripe service class
- `StripeWebhookController.php` - Original Stripe webhook handler
- `SyncStripeSubscriptions.php` - Stripe subscription sync command

## Migration Date

Archived on: 2026-01-31

## Reason for Archive

Stripe integration has been completely replaced with Geidea payment gateway. This code is kept for:
1. Rollback capability (if needed)
2. Reference for historical context
3. Data migration purposes (existing Stripe subscriptions in DB)

## Important Notes

- **DO NOT** use this code in new development
- Stripe DB fields are kept in database for historical data (will be removed after 1+ week stable production)
- All new payment flows use Geidea only
- See `.cursor/plans/stripe_to_geidea_migration_41beb499.plan.md` for migration details

## Removal Strategy

Stripe DB fields will be removed only after:
- 1+ week of stable production with Geidea
- No payment regressions
- All existing Stripe subscriptions properly handled
- Monitoring confirms no issues
