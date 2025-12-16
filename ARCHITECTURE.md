# Ajar Backend – Full Structure and Architecture

## Technology Stack
- PHP 8.2, Laravel 12 skeleton (new `bootstrap/app.php` middleware/exception config; no `Http\Kernel.php`).
- API auth with Laravel Sanctum; custom `PersonalAccessToken` model adds `device_type`, `device_token`, soft deletes, and notification integration.
- Data localization via `spatie/laravel-translatable` (locales `ar`, `en`, default `ar`), custom `LanguageService` and `LanguageTrait`.
- Image/File handling with `intervention/image`, storage in `storage/app/public` (webp conversion), custom `ImageService`/`FileService`.
- Phone parsing/validation with `giggsey/libphonenumber-for-php` and `propaganistas/laravel-phone` through `PhoneService`.
- Push notifications through `kreait/laravel-firebase`; WhatsApp/OTP delivery via `WhatsappMessageService`.
- Frontend tooling: Vite + TailwindCSS v4, minimal JS (Axios bootstrap).

## Folder & File Hierarchy
```
ajar-backend/
├─ .editorconfig
├─ .env.example
├─ .gitattributes
├─ .gitignore
├─ ARCHITECTURE.md
├─ CATEGORIES_SETUP_INSTRUCTIONS.md
├─ README.md
├─ artisan
├─ bootstrap/
│  ├─ app.php
│  ├─ cache/.gitignore
│  └─ providers.php
├─ composer.json
├─ composer.lock
├─ config/
│  ├─ app.php
│  ├─ auth.php
│  ├─ cache.php
│  ├─ database.php
│  ├─ filesystems.php
│  ├─ logging.php
│  ├─ mail.php
│  ├─ queue.php
│  ├─ services.php
│  ├─ session.php
│  └─ translatable.php
├─ database/
│  ├─ .gitignore
│  ├─ factories/
│  │  └─ UserFactory.php
│  ├─ migrations/
│  │  ├─ 0001_01_01_000000_create_users_table.php
│  │  ├─ 0001_01_01_000001_create_cache_table.php
│  │  ├─ 0001_01_01_000002_create_jobs_table.php
│  │  ├─ 2024_09_24_000001_create_governorates_table.php
│  │  ├─ 2024_09_24_000002_create_cities_table.php
│  │  ├─ 2024_09_24_000003_create_categories_table.php
│  │  ├─ 2024_09_24_000004_create_properties_table.php
│  │  ├─ 2024_09_24_000005_create_features_table.php
│  │  ├─ 2024_09_24_000006_create_listings_table.php
│  │  ├─ 2024_09_24_000007_create_listing_properties_table.php
│  │  ├─ 2024_09_24_000009_create_listing_features_table.php
│  │  ├─ 2024_09_24_000010_create_media_table.php
│  │  ├─ 2024_09_24_000011_create_favorites_table.php
│  │  ├─ 2024_09_24_000012_create_views_table.php
│  │  ├─ 2024_09_24_000013_create_listing_reviews_table.php
│  │  ├─ 2024_09_24_000014_create_sliders_table.php
│  │  ├─ 2024_09_24_000015_create_notifications_table.php
│  │  ├─ 2024_09_24_000016_create_settings_table.php
│  │  ├─ 2024_09_24_000017_create_personal_access_tokens_table.php
│  │  ├─ 2025_10_06_074649_add_title_and_description_columns_to_sliders_table.php
│  │  ├─ 2025_10_06_133009_make_icon_allowed_null_in_categories_table copy.php
│  │  ├─ 2025_10_06_162931_make_icon_nullable_in_properties_and_features_tables.php
│  │  ├─ 2025_10_06_170000_add_string_type_to_properties_enum.php
│  │  ├─ 2025_10_13_000001_modify_url_column_in_media_table.php
│  │  ├─ 2025_10_13_120000_alter_media_table_for_iframely.php
│  │  ├─ 2025_10_18_153144_add_sort_order_to_listings_table.php
│  │  ├─ 2025_10_18_153653_remove_users_phone_unique_from_users_table.php
│  │  ├─ 2025_11_07_163328_add_sort_order_to_users_table.php
│  │  ├─ 2025_11_18_180703_add_deleted_at_to_personal_access_tokens_table.php
│  │  └─ 2025_12_07_163233_add_count_column_to_views_table.php
│  └─ seeders/
│     ├─ AddAdminSeeder.php
│     ├─ CategoriesDetailsSeeder.php
│     ├─ CategoriesSeeder.php
│     ├─ DatabaseSeeder.php
│     ├─ DeleteAllCategoriesSeeder.php
│     ├─ GovernoratesSeeder.php
│     └─ ListingsSeeder.php
├─ json/
│  ├─ Ajar_Admin_Endpoints.postman_collection.json
│  └─ Ajar_Complete_API_Fixed.postman_collection.json
├─ lang/
│  ├─ ar/attributes.php
│  ├─ ar/auth.php
│  ├─ ar/enums.php
│  ├─ ar/messages.php
│  ├─ ar/names.php
│  ├─ ar/notifications.php
│  ├─ ar/pagination.php
│  ├─ ar/passwords.php
│  ├─ ar/validation.php
│  ├─ en/attributes.php
│  ├─ en/auth.php
│  ├─ en/enums.php
│  ├─ en/messages.php
│  ├─ en/names.php
│  ├─ en/notifications.php
│  ├─ en/pagination.php
│  ├─ en/passwords.php
│  └─ en/validation.php
├─ md/
│  ├─ admin_endpoints_curl.md
│  ├─ auth_endpoints_curl.md
│  ├─ import_to_postman.md
│  └─ user_endpoints_curl.md
├─ package.json
├─ phpunit.xml
├─ public/
│  ├─ .htaccess
│  ├─ favicon.ico
│  ├─ index.php
│  └─ robots.txt
├─ resources/
│  ├─ css/app.css
│  ├─ js/app.js
│  ├─ js/bootstrap.js
│  └─ views/welcome.blade.php
├─ routes/
│  ├─ api/api.php
│  ├─ api/v1/api_admin.php
│  ├─ api/v1/api_auth.php
│  ├─ api/v1/api_general.php
│  ├─ api/v1/api_user.php
│  ├─ console.php
│  └─ web.php
├─ server.sh
├─ storage/
│  ├─ app/.gitignore
│  ├─ app/private/.gitignore
│  ├─ app/public/.gitignore
│  ├─ firebase/ajar-b6b42-firebase-adminsdk-fbsvc-549b968f42.json
│  ├─ framework/.gitignore
│  ├─ framework/cache/.gitignore
│  ├─ framework/cache/data/.gitignore
│  ├─ framework/sessions/.gitignore
│  ├─ framework/testing/.gitignore
│  ├─ framework/views/.gitignore
│  └─ logs/.gitignore
├─ tests/
│  ├─ Feature/ExampleTest.php
│  ├─ TestCase.php
│  └─ Unit/ExampleTest.php
├─ vite.config.js
└─ app/
   ├─ Http/
   │  ├─ Controllers/
   │  │  ├─ AuthController.php
   │  │  ├─ CategoryController.php
   │  │  ├─ CityController.php
   │  │  ├─ Controller.php
   │  │  ├─ FeatureController.php
   │  │  ├─ GovernorateController.php
   │  │  ├─ HomeController.php
   │  │  ├─ ImageController.php
   │  │  ├─ ListingController.php
   │  │  ├─ ListingReviewController.php
   │  │  ├─ NotificationController.php
   │  │  ├─ PropertyController.php
   │  │  ├─ SettingController.php
   │  │  ├─ SliderController.php
   │  │  └─ UserController.php
   │  ├─ Middleware/
   │  │  ├─ AdminMiddleware.php
   │  │  └─ SetLocaleMiddleware.php
   │  ├─ Notifications/
   │  │  ├─ ListingNotification.php
   │  │  ├─ ListingReviewNotifications.php
   │  │  └─ UserNotification.php
   │  ├─ Permissions/
   │  │  ├─ CategoryPermission.php
   │  │  ├─ CityPermission.php
   │  │  ├─ FeaturePermission.php
   │  │  ├─ GovernoratePermission.php
   │  │  ├─ ListingPermission.php
   │  │  ├─ ListingReviewPermission.php
   │  │  ├─ NotificationPermission.php
   │  │  ├─ PropertyPermission.php
   │  │  ├─ SettingPermission.php
   │  │  ├─ SliderPermission.php
   │  │  └─ UserPermission.php
   │  ├─ Requests/
   │  │  ├─ BaseFormRequest.php
   │  │  ├─ Auth/
   │  │  │  ├─ CheckPhoneNumberRequest.php
   │  │  │  ├─ ForgotPasswordRequest.php
   │  │  │  ├─ LoginRequest.php
   │  │  │  ├─ RegisterRequest.php
   │  │  │  ├─ ResetPasswordRequest.php
   │  │  │  └─ VerifyCodeRequest.php
   │  │  ├─ Category/
   │  │  │  ├─ CreateCategoryRequest.php
   │  │  │  ├─ ReOrderCategoryRequest.php
   │  │  │  └─ UpdateCategoryRequest.php
   │  │  ├─ City/
   │  │  │  ├─ CreateCityRequest.php
   │  │  │  ├─ ReOrderCityRequest.php
   │  │  │  └─ UpdateCityRequest.php
   │  │  ├─ Feature/
   │  │  │  ├─ CreateFeatureRequest.php
   │  │  │  ├─ ReOrderFeatureRequest.php
   │  │  │  └─ UpdateFeatureRequest.php
   │  │  ├─ Governorate/
   │  │  │  ├─ CreateGovernorateRequest.php
   │  │  │  ├─ ReOrderGovernorateRequest.php
   │  │  │  └─ UpdateGovernorateRequest.php
   │  │  ├─ Listing/
   │  │  │  ├─ CreateListingRequest.php
   │  │  │  ├─ ReOrderListingRequest.php
   │  │  │  ├─ UpdateListingRequest.php
   │  │  │  └─ ViewListingRequest.php
   │  │  ├─ ListingReview/
   │  │  │  ├─ CreateListingReviewRequest.php
   │  │  │  └─ UpdateListingReviewRequest.php
   │  │  ├─ Notification/
   │  │  │  ├─ CreateNotificationRequest.php
   │  │  │  └─ UpdateNotificationRequest.php
   │  │  ├─ Property/
   │  │  │  ├─ CreatePropertyRequest.php
   │  │  │  ├─ ReOrderPropertyRequest.php
   │  │  │  └─ UpdatePropertyRequest.php
   │  │  ├─ Setting/
   │  │  │  ├─ CreateSettingRequest.php
   │  │  │  ├─ UpdateManySettingsRequest.php
   │  │  │  └─ UpdateSettingRequest.php
   │  │  ├─ Slider/
   │  │  │  ├─ CreateSliderRequest.php
   │  │  │  ├─ ReOrderSliderRequest.php
   │  │  │  └─ UpdateSliderRequest.php
   │  │  └─ User/
   │  │     ├─ CreateUserRequest.php
   │  │     ├─ UpdateProfileRequest.php
   │  │     └─ UpdateUserRequest.php
   │  ├─ Resources/
   │  │  ├─ CategoryResource.php
   │  │  ├─ CityResource.php
   │  │  ├─ FeatureResource.php
   │  │  ├─ GovernorateResource.php
   │  │  ├─ ListingPropertyResource.php
   │  │  ├─ ListingResource.php
   │  │  ├─ ListingReviewResource.php
   │  │  ├─ MediaResource.php
   │  │  ├─ NotificationResource.php
   │  │  ├─ PropertyResource.php
   │  │  ├─ SettingResource.php
   │  │  ├─ SliderResource.php
   │  │  ├─ UserResource.php
   │  │  └─ ViewResource.php
   │  └─ Services/
   │     ├─ AuthService.php
   │     ├─ CategoryService.php
   │     ├─ CityService.php
   │     ├─ FeatureService.php
   │     ├─ GovernorateService.php
   │     ├─ ListingReviewService.php
   │     ├─ ListingService.php
   │     ├─ NotificationService.php
   │     ├─ PropertyService.php
   │     ├─ SettingService.php
   │     ├─ SliderService.php
   │     └─ UserService.php
   ├─ Models/
   │  ├─ Category.php
   │  ├─ City.php
   │  ├─ Favorite.php
   │  ├─ Feature.php
   │  ├─ Governorate.php
   │  ├─ Listing.php
   │  ├─ ListingFeature.php
   │  ├─ ListingProperty.php
   │  ├─ ListingReview.php
   │  ├─ Media.php
   │  ├─ Notification.php
   │  ├─ PersonalAccessToken.php
   │  ├─ Property.php
   │  ├─ Setting.php
   │  ├─ Slider.php
   │  ├─ User.php
   │  └─ View.php
   ├─ Providers/
   │  └─ AppServiceProvider.php
   ├─ Services/
   │  ├─ AvatarService.php
   │  ├─ FileService.php
   │  ├─ FilterService.php
   │  ├─ FirebaseService.php
   │  ├─ IframelyService.php
   │  ├─ ImageService.php
   │  ├─ LanguageService.php
   │  ├─ MediaResolverService.php
   │  ├─ MessageService.php
   │  ├─ OrderHelper.php
   │  ├─ PhoneService.php
   │  ├─ ResponseService.php
   │  └─ WhatsappMessageService.php
   └─ Traits/
      └─ LanguageTrait.php
```

## Architectural Layers and Roles
### Routing & Middleware
- `bootstrap/app.php` wires routes and exception rendering. All API responses are forced to JSON; authentication, 404, and method errors are mapped to `MessageService::abort`.
- API entrypoint: `routes/api/api.php` mounts `v1` routes under `SetLocaleMiddleware` (sets locale from `Accept-Language`, caches user per bearer token, persists language on user record).
- Versioned route files:
  - `api_auth.php`: `/api/v1/auth/*` public login/register/otp, with Sanctum guard on reset/logout.
  - `api_admin.php`: `/api/v1/admin/*` admin-only (Sanctum + `AdminMiddleware`) managing users, categories, properties, features, governorates, cities, sliders (click/reorder), notifications, reviews, settings, listings.
  - `api_user.php`: `/api/v1/user/*` public listing/category/governorate/city/property/feature/slider/settings read endpoints plus listing views; Sanctum-required nested group for creating/updating/deleting listings, toggling favorites, creating/updating/deleting reviews, reading/marking notifications, and profile endpoints.
  - `api_general.php`: `/api/v1/general/*` upload image/file, fetch media metadata, fetch setting by id/key, check phone number.
- `web.php` only returns `welcome` view; `console.php` defines the example `inspire` command.
- `AdminMiddleware` enforces admin role via cached `User::auth()`; aborts with translated unauthorized message.

### Controllers
- Thin orchestrators calling services and permission classes, then respond via `ResponseService`. Standard method set: `index`, `show`, `create`, `update`, `delete`, plus domain-specific actions (`reorder`, `toggleFavorite`, `click`, `viewListing`, `markAsRead`, etc.).
- Controllers by domain: Auth, Category, City, Feature, Governorate, Home (home page composition), Image (uploads/remote fetch), Listing, ListingReview, Notification, Property, Setting, Slider, User.

### Validation Requests
- All requests extend `BaseFormRequest` (`stopOnFirstFailure=true`; `authorize` always true; attributes/messages pulled from `lang/*/validation.php`).
- Domain-specific folders mirror entities: `Create*/Update*/ReOrder*/View*` request classes encapsulate rules per action; Auth-specific requests validate phone/password/OTP.
- Reorder requests expect `sort_order`; validation relies on translation attributes for field names.

### Permission Classes
- One class per entity under `Http/Permissions`, exposing static helpers:
  - `filterIndex($query)` to scope public vs admin visibility (e.g., hide invisible categories for non-admin, only approved listings for guests).
  - `canShow/CanCreate/CanUpdate/CanDelete` to gate actions and adjust model state (e.g., `ListingPermission::canUpdate` sets user-owned listings to `in_review`).
- Abort paths use `MessageService::abort` with translated keys.

### Domain Services (`app/Http/Services`)
- **AuthService**: login/register/OTP verify/forgot/reset/logout; parses E.164 phone via `PhoneService`; enforces role-specific login; issues Sanctum tokens; sends OTP through `WhatsappMessageService`.
- **CategoryService**: translatable create/update (`LanguageService::prepareTranslatableData`), hierarchical eager-loading (children/properties/features), visibility filtering via permissions, `OrderHelper` for sort order, cascade delete of children/properties/features, reorder handler.
- **CityService / GovernorateService**: CRUD with permission scoping, ordering, search/filter via `FilterService`.
- **FeatureService / PropertyService**: CRUD, sorting, filterable attributes; properties tie to categories.
- **ListingService**: Full listing lifecycle. Applies permission scoping, extensive filtering (`search`, numeric/date ranges, exact/in filters), favorites filter, insurance filter, dynamic feature/property filters, descendant category filtering, radius-based geo filter, map-mode viewport clustering (custom screen-space clustering with Web Mercator projections), optional pagination control, owner and media eager-loading, creation/update with media handling and status transitions, favorite toggling, view counting. Uses `OrderHelper`, `LanguageService`, `FilterService`, `ImageService` and permissions.
- **ListingReviewService**: Create/update/delete reviews with permission checks; filters by listing/user; averages managed in listing model/service.
- **NotificationService**: List/store/mark notifications, unread counts; integrates with Firebase to send and with DB storage.
- **SettingService**: CRUD of key/value settings (supports translatable fields), bulk update (`UpdateManySettingsRequest`), public settings exposure; guarded by permissions.
- **SliderService**: CRUD, click tracking, active sliders fetch, reorder support.
- **UserService**: Admin user CRUD, profile retrieval/update (password change revokes other tokens and triggers session-expiry notification), filtering via `FilterService` with name concatenation search.

### Shared Services (`app/Services`)
- **ResponseService**: Standard JSON envelope with `success`, `message` (translated), optional `data`, resource wrapping, pagination `meta` derived from paginator.
- **MessageService**: Central abort/success helpers returning JSON with `key`/`message`.
- **FilterService**: Reusable query filtering: search across fields/relations (including combined fields), numeric range filters (supports aggregate functions), date ranges, exact match, `in`/`not in`, configurable sorting, optional pagination toggle.
- **OrderHelper**: Assigns incremental `sort_order` on creation (including soft-deleted rows) and reorders with gapless adjustment inside a transaction.
- **LanguageService**: Locale detection (Accept-Language, default `en`), translatable rule builder for array-based locale input, translatable data preparation for models with `getTranslatableAttributes`.
- **LanguageTrait**: Returns translation arrays for every configured locale when accessing translatable attributes.
- **PhoneService**: Validates and parses phone numbers; returns country code, national number, formatted E.164; aborts with translated message on failure.
- **ImageService**: Stores images in `storage/app/public/{folder}` as webp (quality auto-adjusted to <=300KB), supports updating/deleting, bulk removal by IDs, ensures folders exist.
- **FileService**: Generic store/update/delete on `public` disk.
- **MediaResolverService**: Scrapes OpenGraph/Twitter meta to resolve image/video/embed HTML; normalizes URLs, handles Facebook-specific fallbacks; classifies media type.
- **IframelyService**: Calls Iframely oEmbed API using configured base URL/API key; aborts with detailed errors on failure.
- **FirebaseService**: Manages topic subscriptions, stores device tokens on personal access tokens, sends notifications to topics or token lists (multicast), logs failures, persists notifications via `NotificationService`.
- **WhatsappMessageService**: Sends OTP messages via external HTTP APIs (Metaphilia); alternate `send2` stub.
- **AvatarService**: Generates simple SVG data-URI avatars with caching.
- **Response/Message helpers** tie controllers to consistent API payloads.

### Models
- Common traits: `HasFactory`; heavy use of `SoftDeletes`; `HasApiTokens` on `User`/`PersonalAccessToken`; `HasTranslations` + `LanguageTrait` on text-heavy entities (e.g., `Category`, `Feature`, `Property`, `Listing` where applicable).
- **User**: Roles (`admin`, `employee`, `user`), `status`, `sort_order`, phone verification, wallet balance; cached accessor `User::auth()` reads user from cache keyed by bearer token (set in `SetLocaleMiddleware`); avatar accessor falls back to ui-avatars; relations to listings (owner_id), favorites, reviews, views, notifications, sliders.
- **Category**: Hierarchical (`parent`/`children` eager with properties/features), `properties_source` controls property inheritance; translatable `name/description`; `is_visible`; dynamic `properties` attribute based on source; scopes `parents` and `ordered`.
- **Listing**: (not shown fully here) owns media (polymorphic), features, listingProperties->property, owner, location fields; supports favorites/views/reviews; `sort_order` and statuses (`approved`, `in_review`, etc.).
- **Media**: Polymorphic `imageable`, `type` (image/video/link), JSON `iframely`, `sort_order` scope `ordered`.
- **PersonalAccessToken**: Custom table/model with device metadata and soft deletes; relation to `User`.
- Other domain models: `City`, `Governorate`, `Property`, `Feature`, `ListingFeature`, `ListingProperty`, `Favorite`, `ListingReview`, `Notification`, `Setting`, `Slider`, `View` with expected relations and casts; many carry `sort_order` integer casts and optional translatable fields.

### Resources (API Transformers)
- Per-entity resources mirror models: Category, City, Feature, Governorate, Listing, ListingProperty, ListingReview, Media, Notification, Property, Setting, Slider, User, View. Used by `ResponseService` to wrap single models or collections, ensuring consistent payload shapes.

### Notifications
- `Http/Notifications` wrappers trigger Firebase notifications (e.g., `UserNotification::expireSessions`), listing/review notifications for push and storage.

### Internationalization
- Languages: `lang/ar` and `lang/en` sets for validation/auth/messages/enums/names/notifications/pagination/passwords/attributes. `translatable.php` defines locales (`ar`, `en`), default `ar`, fallback `ar`, always-load translations.
- Requests/messages rely on translation keys; controllers/services pass translation keys to `ResponseService`/`MessageService` for runtime localization.

### Authentication & Authorization
- Sanctum guards protect admin and authenticated user routes; tokens created per user in `AuthService` and stored in `personal_access_tokens` with device metadata.
- `SetLocaleMiddleware` caches authenticated users per token for fast `User::auth()` access and persists language preference based on `Accept-Language` header.
- Role enforcement through `AdminMiddleware` and per-entity Permission classes; public visibility enforced in `filterIndex`/`canShow` patterns.

### Media & Uploads
- Image/file uploads restricted to folders: `sliders`, `listings`, `users`, `properties`, `features`.
- Images converted to webp with adaptive quality; URLs returned as `storage/{path}` assets. File uploads accept a wide extension allowlist.
- Remote media resolution via `MediaResolverService` and Iframely for embedding.

### Filtering, Sorting, Ordering
- Query filtering centralized in `FilterService` with support for:
  - Text search across multiple fields/relations (including concatenated name fields).
  - Numeric/date range filters, aggregate filters (AVG/SUM/COUNT/MIN/MAX) with having.
  - Exact-match and `in`/`not in` filters.
  - Sorting constrained to allowed fields with safe defaults; optional pagination toggle for map mode.
- `OrderHelper` standardizes `sort_order` assignment and reordering across entities; `ReOrder*Request` classes surface user-controlled ordering endpoints.

### Exception & Response Conventions
- `bootstrap/app.php` registers API-friendly exception rendering for auth/404/method errors via `MessageService`.
- API responses always include `success` flag; translated `message` keyed; optional `data`, `meta` (paginator), and `key` (message key).
- Validation stops at first failure; attributes/messages localized.

## Data & Migrations
- Migrations cover core domain tables with `sort_order`, soft deletes, translation-friendly fields, and status columns; additional migrations adjust icons nullability, property enums, media URL storage/iframely data, listing/user sort order, phone uniqueness removal, token soft deletes, views count.
- Seeders: `AddAdminSeeder`, category tree (`CategoriesSeeder`, `CategoriesDetailsSeeder`), locations (`GovernoratesSeeder`), sample listings (`ListingsSeeder`), cleanup (`DeleteAllCategoriesSeeder`), orchestrated via `DatabaseSeeder`. `CATEGORIES_SETUP_INSTRUCTIONS.md` documents seeded hierarchy and data rules.

## Frontend & Assets
- Vite config (`vite.config.js`) loads `resources/css/app.css` (Tailwind v4 @source hints) and `resources/js/app.js` (Axios bootstrap). No SPA present; only default `welcome.blade.php` view.

## Documentation & Tooling
- Postman collections under `json/` and cURL snippets under `md/` for admin/auth/user endpoints.
- `server.sh` contains local serve command and deployment SSH/migration instructions.
- Tests: placeholder `Feature/ExampleTest.php`, `Unit/ExampleTest.php`, base `TestCase.php`.

## Environment & Config Notes
- Third-party config keys in `config/services.php`: Iframely (`IFRAMELY_BASE_URL`, `IFRAMELY_API_KEY`, optional `IFRAMELY_ORIGIN`, timeout), mail/SNS defaults. Firebase service account stored at `storage/firebase/ajar-b6b42-firebase-adminsdk-fbsvc-549b968f42.json` (path expected by Kreait).
- Storage symlink expected (`php artisan storage:link`); `server.sh` also notes permissions for storage and deployment steps.

## Architectural Conventions (Replication Checklist)
- Keep controllers minimal; enforce access via Permission classes, validation via FormRequest subclasses, and return all API payloads through `ResponseService`/`MessageService`.
- Encapsulate business rules in `app/Http/Services/*`; share cross-cutting concerns in `app/Services/*` (filters, ordering, language, media, messaging, Firebase, phone/OTP).
- Use `spatie/laravel-translatable` for translatable fields; expose translations through `LanguageTrait` accessors so API returns per-locale maps.
- Apply `sort_order` to any ordered entity and expose `/reorder` endpoints using `OrderHelper`.
- Use `FilterService` for any list endpoint to keep filtering/sorting behavior consistent.
- Enforce locale per request via `SetLocaleMiddleware`; keep `Accept-Language` limited to `ar`/`en`.
- Manage authentication exclusively with Sanctum tokens; store device metadata on tokens for notification fan-out; cache authenticated user per token for request lifecycle.
- Restrict uploads to whitelisted folders; store media in `storage/app/public`; convert images to webp; allow remote media embedding through Iframely/MediaResolver when needed.
- Keep public vs admin visibility rules inside Permission classes (`filterIndex`/`canShow`) rather than controllers.
- Maintain Postman collections and cURL docs under `json/` and `md/` for API consumers; keep seeds in sync with documented category/property/feature hierarchy.

## How Enforcement Happens in Code (Concrete Patterns)
- **Controller → Permission → Service → Response**: Controllers call a permission static (e.g., `CategoryPermission::canUpdate($model)`) before handing data to a Service. Services return models/queries; controllers wrap them with `ResponseService::response([... 'resource' => CategoryResource::class ...])`, which materializes resources and builds the JSON envelope (`success`, optional `meta`, translated `message`).
- **Abort flow**: `MessageService::abort($status, $key)` wraps `abort(response()->json([...], $status))`; every denial and validation edge uses this path (permissions, phone parsing, Iframely failures) ensuring consistent payload shape and translated `key`.
- **Filtering flow**: Services build an Eloquent query, then pass it to `FilterService::applyFilters($query, $filters, $searchFields, ...)`. This function applies search, numeric/date ranges, exact/in filters, safe sorting, and optional pagination. Controllers never mutate queries directly.
- **Ordering flow**: Creation paths call `OrderHelper::assign($model)` to set `sort_order` based on `max + 1` (including soft-deleted rows). Reorder endpoints validate `sort_order` via `ReOrder*Request` and call `OrderHelper::reorder($model, $newOrder)` inside a DB transaction to shift neighbors.
- **Permissions in queries**: `filterIndex` is injected before filters (e.g., `ListingPermission::filterIndex($query)` restricts guests to `approved` listings, users to own-or-approved). `canShow` guards entity visibility and aborts 404/403 consistently.
- **Locale enforcement**: `SetLocaleMiddleware` reads `Accept-Language`, sets `app()->setLocale`, caches the authenticated user in `cache()->put('request_user_' . bearerToken, $user, 300)`, and updates the user’s `language` column if it changed—`User::auth()` then pulls from cache for the request lifecycle.
- **Sanctum tokens**: `AuthService` issues tokens; `logout` deletes the current token; `UserService` password changes purge other tokens and trigger `UserNotification::expireSessions` (Firebase push) using stored `device_token` on `personal_access_tokens`.
- **Media handling**: `ImageService::storeImage` forces webp, auto-reduces quality if >300KB, saves under `storage/app/public/{folder}`. `FileService` updates delete old files if present.
- **Response resource wrapping**: `ResponseService` checks `resource` + `data`; if data is collection/paginator, it returns `Resource::collection`, otherwise instantiates the resource. `meta` is derived from paginator via `meta()` helper.

## Non-Negotiable Architectural Rules (Must Not Be Violated)
- No business logic in controllers; only coordinate validation → permissions → service calls → `ResponseService`.
- No direct DB queries or model mutations inside controllers, requests, middleware, or resources—use services (and Permission classes for visibility).
- No bypassing Permission classes; all read/write entrypoints must use `filterIndex`/`canShow`/`canCreate`/`canUpdate`/`canDelete`.
- All API responses must go through `ResponseService` or `MessageService`; never return raw models or `response()->json` directly from controllers/services.
- Filtering and sorting must use `FilterService`; do not handcraft where/order chains in controllers.
- Ordering must use `OrderHelper`; do not set `sort_order` manually.
- Uploads must use `ImageService`/`FileService` with allowed folders; never write to `storage` directly.
- Locale must be taken from `SetLocaleMiddleware`; do not set `app()->setLocale` elsewhere.
- Translatable fields must be prepared through `LanguageService::prepareTranslatableData`; do not persist raw locale arrays manually.

## Request/Data Flows (Textual Diagrams)
- **Auth (login/register/OTP)**
  1) Route (`api/v1/auth/*`) → FormRequest validates phone/password/otp.  
  2) Controller calls `AuthService`.  
  3) `PhoneService::parsePhoneParts` normalizes E.164; abort 422 on invalid.  
  4) Login: role-guarded user lookup, password check, banned/not-verified checks → `createToken` → return user+token.  
  5) Register: ensure phone unique for role, generate OTP (4 digits, 5m), persist, send via `WhatsappMessageService::send`, return OTP expiry.  
  6) Verify OTP: accepts test bypass for `5555`, otherwise checks code+expiry, marks `phone_verified`, issues token.  
  7) Forgot/reset: generate OTP, send via WhatsApp, later reset password, delete old tokens, issue new token.  
  8) Responses uniformly via `ResponseService` keys.
- **Listing (public read + user create/update)**
  1) Route (`api/v1/user/listings` or admin) → FormRequest (Create/Update/ReOrder/View).  
  2) Controller injects `ListingPermission::filterIndex` for lists or `canShow` for single.  
  3) `ListingService::index` builds query with eager loads, applies `FilterService` (search, ranges, status, location radius, dynamic feature/property filters), map-mode clustering when `map_mode` params exist.  
  4) `ListingService::create/update`: calls `ListingPermission::canCreate/canUpdate` (users force status to `in_review`), uses `LanguageService` for translatables, `OrderHelper` for sort, handles media and status transitions, may toggle favorites/views.  
  5) Response via `ListingResource` (collection or single) + `meta` if paginated.
- **Admin CRUD template (Categories/Properties/Features/Locations/Settings/Sliders/Users)**
  1) Route under `/api/v1/admin/*` + `auth:sanctum` + `AdminMiddleware`.  
  2) FormRequest per action (Create/Update/ReOrder).  
  3) Permission class `canCreate/canUpdate/canDelete` enforces admin; `filterIndex` may expose only visible items to non-admin when reused by public endpoints.  
  4) Service performs query, applies `FilterService`, uses `OrderHelper` for ordering actions, `LanguageService` for translatables, cascading deletes where applicable (e.g., categories delete children/properties/features).  
  5) Responses wrapped with the matching Resource via `ResponseService`.

## Generic vs Project-Specific
- **Reusable architecture (keep when cloning)**
  - Layering: Controllers → Permissions → Services → Resources; FormRequests for validation; `ResponseService`/`MessageService`; `FilterService`; `OrderHelper`; `LanguageService` + `LanguageTrait`; Sanctum auth with cached `User::auth`; `SetLocaleMiddleware`; media/file services; permission classes pattern; translatable config structure; routing split by version and domain files.
  - Conventions: stop-on-first-failure validation, resource-wrapped responses, translated messages, enforced JSON errors in `bootstrap/app.php`.
- **Ajar-specific pieces (swap when cloning)**
  - Domain models/entities (Category, Listing, Feature, Property, Governorate, City, Slider, Setting, Notification, View, Favorite, ListingReview).  
  - Domain-specific filters (insurance, map-mode clustering, feature/property dynamic filters), status names (`approved`, `in_review`, `banneded` typo retained), properties inheritance logic, seeder data and hierarchy (`CATEGORIES_SETUP_INSTRUCTIONS.md`).  
  - WhatsApp OTP provider endpoints, Iframely keys/origin, Firebase topics naming (`user-{id}`, `role-{role}`, `all-users` with env suffix), folder names for uploads.

## Code Quality & Conventions
- **Naming**: Controllers use CRUD verbs; services use domain verbs; permissions use `filterIndex/canShow/canCreate/canUpdate/canDelete`; resources match model names; requests use `Create/Update/ReOrder/View` prefixes.
- **Return patterns**: Controllers never return models directly; always call `ResponseService::response`. Services return models/queries/arrays, leaving JSON shape to the controller layer.
- **Error handling**: Use `MessageService::abort` for any failure path; use translated keys; status codes set at abort call sites. Validation stops at first failure (`BaseFormRequest::$stopOnFirstFailure=true`).
- **Filtering/sorting**: Only through `FilterService`; sorting fields are whitelisted and defaulted to safe fallbacks; pagination opt-out only when explicitly requested (map mode).
- **Ordering**: Always via `OrderHelper`; includes soft-deleted rows to keep monotonic order.
- **Translatables**: Always prepared via `LanguageService::prepareTranslatableData`; `LanguageTrait` ensures getters return locale maps.
- **Uploads**: `ImageService` forces webp with adaptive quality; allowed folders fixed; `FileService` deletes old file on update.
- **Response envelope**: `success` flag always present; `message` translated when provided; `key` echoes translation key; `meta` only when paginator is present.

## Edge Cases & Implicit Logic (Documented)
- Token/user caching: `SetLocaleMiddleware` caches the authenticated user per bearer token for 5 minutes on every request; `User::auth()` returns cached user, so permission checks rely on this cache.
- Locale persistence: Middleware updates the user’s `language` column when header differs, creating side-effect writes on each request.
- Auth OTP bypass: `AuthService::verifyOtp` allows OTP `5555` as a testing shortcut.
- Listing status transitions: `ListingPermission::canUpdate` sets user-owned listings to `in_review`; guests cannot see non-`approved`; users can see their own regardless of status.
- Favorites filter: When `is_favorite=1` and unauthenticated, `ListingService::index` forces empty results (`whereRaw 1=0`).
- Insurance filter: Explicitly distinguishes zero/null insurance vs positive insurance amounts.
- Map mode clustering: When `map_mode` parameters supplied, pagination is bypassed, geo bounding applied, then screen-space clustering computed; cluster radius adapts to zoom and `radius_px`/`merge_factor`.
- Media limits: `ImageService` reduces quality iteratively until under 300KB (down to quality 10) to avoid oversize uploads.
- Device tokens: `FirebaseService::subscribeToAllTopic` stores `device_token` on the latest personal access token; notification fan-out relies on that field being populated.
- Soft deletes in ordering: `OrderHelper::assign/reorder` consider soft-deleted rows to avoid duplicate `sort_order`.
- Error surface: `bootstrap/app.php` forces JSON errors for auth/404/method errors on `api/*`, mapping to translated keys via `MessageService`.

## Code Anchors (File → Class → Method)
- Locale/cache: `app/Http/Middleware/SetLocaleMiddleware.php → SetLocaleMiddleware::handle()`
- Auth: `app/Http/Services/AuthService.php → login() | register() | verifyOtp() | forgotPassword() | resetPassword() | logout()`
- Token accessor: `app/Models/User.php → User::auth()`
- Permissions: `app/Http/Permissions/*Permission.php → filterIndex() | canShow() | canCreate() | canUpdate() | canDelete()`
- Listings: `app/Http/Services/ListingService.php → index() | create() | update() | viewListing()`
- Filtering: `app/Services/FilterService.php → applyFilters()` (and helpers `applySearch/applyNumericFilters/...`)
- Ordering: `app/Services/OrderHelper.php → assign() | reorder()`
- Responses: `app/Services/ResponseService.php → response() | meta()`
- Abort helper: `app/Services/MessageService.php → abort() | success()`
- Locale utilities: `app/Services/LanguageService.php → getLocale() | translatableFieldRules() | prepareTranslatableData()`
- Translatable getter: `app/Traits/LanguageTrait.php → getAllTranslations()`
- Media storage: `app/Services/ImageService.php → storeImage()`; files: `app/Services/FileService.php → storeFile() | updateFile() | deleteFile()`
- Embeds: `app/Services/IframelyService.php → fetch()`; scraping: `app/Services/MediaResolverService.php → resolve()`
- Firebase push: `app/Services/FirebaseService.php → subscribeToAllTopic() | sendToTokensAndStorage() | sendToTopic()`
- Permissions for listings: `app/Http/Permissions/ListingPermission.php → filterIndex() | canShow() | canCreate() | canUpdate() | canDelete()`

## API Response Contract (Exact Shapes)
- Success (via `ResponseService::response`):
  - Fields: `success` (bool), optional `key` (string), optional `message` (translated), optional `data` (resource-wrapped or raw), optional `meta` (only when paginator passed with `meta` flag).
  - `success` is computed from HTTP status (`status >=200 && <300`).
  - `meta`: `{ "current_page": int, "last_page": int, "per_page": int, "total": int }`; only added when the caller passes `meta` truthy **and** `data` is a paginator.
  - Example collection: `{ "success": true, "data": [ ... ], "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 87 } }`.
  - Example single with message: `{ "success": true, "key": "messages.category.created", "message": "Category created", "data": { ... } }`.
- Abort errors (via `MessageService::abort`):
  - Shape: `{ "success": false, "message": "<translated>", "key": "<translation_key>" }` with status passed to `abort`.
- Validation errors (Laravel default, 422):
  - `{ "message": "The given data was invalid.", "errors": { "field": ["error message"] } }` (`BaseFormRequest` stops on first failure but shape remains standard).
- Unauthenticated (`auth:sanctum` handler in `bootstrap/app.php`):
  - `{ "success": false, "message": "You are not logged in", "key": "You are not logged in" }`, status 401.
- Unauthorized (permission):
  - Example `{ "success": false, "message": "<translated>", "key": "messages.permission.error" }`, status 403.
- Not found:
  - Routes: key `"Route not found"`, status 404; entities use domain keys (e.g., `messages.category.not_found`), status 404.
- Method not allowed:
  - `{ "success": false, "message": "Invalid request method", "key": "Invalid request method" }`, status 405.
- `meta` appears only when a paginator is provided and `meta` flag is true; absent otherwise.

## Environment Variables Contract
- Required:
  - `APP_KEY` (framework), `APP_URL` (URL generation).
  - Database: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (`config/database.php`).
  - Iframely: `IFRAMELY_API_KEY` (mandatory), `IFRAMELY_BASE_URL` (default `https://iframe.ly/api/iframely`), `IFRAMELY_TIMEOUT` (default 12), optional `IFRAMELY_ORIGIN` (all in `config/services.php` → `IframelyService`).
- Optional/project-specific:
  - `APP_ENV_TYPE` (default `production`), used in `FirebaseService::subscribeToAllTopic` to suffix topics in non-prod.
  - Mail/SNS: `POSTMARK_TOKEN`, `RESEND_KEY`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` (default `us-east-1`) in `config/services.php`.
  - Sanctum cookie domains: `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` (needed if cookie auth is used; bearer tokens are primary here).
  - Firebase service account: expected at `storage/firebase/ajar-b6b42-firebase-adminsdk-fbsvc-549b968f42.json`; adjust Kreait config/env if relocating (not explicitly env-wired in code).
  - WhatsApp/OTP: **hardcoded (needs env refactor)** in `app/Services/WhatsappMessageService.php → send()` (`api_key`, `sender`) and `send2()` stub.
- Locale defaults:
  - `config/translatable.php`: `default_locale=ar`, `fallback_locale=ar`; `LanguageService::getLocale` defaults to `en` when header missing (see Locale Consistency).

## Database Conventions & Guarantees
- Soft deletes: used on many tables (users, listings, categories, media, tokens, etc.); ordering includes soft-deleted rows to avoid duplicate `sort_order`.
- `sort_order`: assigned as `max+1` on create (including soft-deleted) and rebalanced in transactions on reorder.
- Key naming: foreign keys `{name}_id`; owner linkage uses `owner_id` for listings.
- Uniqueness: phone uniqueness on users removed intentionally (`2025_10_18_153653_remove_users_phone_unique_from_users_table`); uniqueness enforced in services, not DB.
- Translations: JSON columns for translatable fields via spatie; accessors return locale maps.
- Status fields: Listings (`status`, `availability_status`, `type`), Users (`status`, `role`), others as applicable; no DB-level enums—code-enforced.
- Timestamps: standard `created_at/updated_at`; `deleted_at` for soft deletes.
- Phone uniqueness: DB uniqueness on phone removed (`2025_10_18_153653_remove_users_phone_unique_from_users_table`); service-level check in `AuthService::register()` (`User::where('phone', $phoneNumber)->where('role','user')`).

## Security Controls
- Rate limiting / throttling: **Not implemented** for OTP/login/register; add throttle middleware for production.
- Role/permission: `AdminMiddleware` + per-entity Permission classes.
- Token revocation: password change deletes other tokens (`UserService::updateProfile`); logout deletes current token (`AuthService::logout`); tokens soft-deletable.
- Device binding: `FirebaseService::subscribeToAllTopic` writes `device_token` to latest PAT; no IP/device fingerprinting.
- Upload validation: `ImageController::uploadImage` enforces mime and 8MB max; `uploadFile` uses explicit allowlist.
- Recommended throttle approach: apply Laravel `throttle` middleware on auth/OTP routes (e.g., `throttle:5,1` for OTP).

## Locale Consistency
- Translation defaults: `config/translatable.php` default/fallback locale = `ar`; locales = [`ar`, `en`].
- Request processing locale: `LanguageService::getLocale()` returns header `Accept-Language` when in [`ar`, `en`], else `en`; if header absent, `en`.
- Net effect (by design): Returned strings follow runtime locale (`en` if no header), but missing translations fall back to `ar`. This divergence is intentional; change only if aligning runtime default with storage default is required.
