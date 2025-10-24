# Copilot Instructions for oc-polaris

## Project Overview
- This project is a modular OctoberCMS application, extended with custom plugins and themes for the One Medicare Sdn Bhd.
- Core structure: `modules/` (core features), `plugins/omsb/*` (domain-specific plugins), `themes/` (contain css and site structure definition which is less important since most of the user will be accessing the backend side of OctoberCMS and that part look and feel is defined by the default OctoberCMS Backend theme), and `config/` (environment/configuration).
- Built on top of OctoberCMS latest version that utilized Laravel 12 with PHP 8.2 and above supported. All custom codebase (other than config) resides within `/plugins/omsb` folder and strictyly adhere to OctoberCMS conventions for plugins structure.

## Key Architectural Patterns
- **Modules**: Core features (e.g., `backend`, `cms`, `editor`, `media`, `system`) are in `modules/`. Each module is self-contained with its own controllers, models, assets, and tests.
- **Plugins**: Domain-specific logic is in `plugins/omsb/*`. Each plugin follows standardized structure:
  ```
  controllers/     # Lean controllers, request/response handling only (https://docs.octobercms.com/4.x/extend/system/controllers.html)
  services/        # Business logic classes (e.g., AdjustmentService)
  models/          # Eloquent models with validation traits
  behaviors/       # OctoberCMS repeatable controller behaviors or model behaviors (https://docs.octobercms.com/4.x/extend/system/behaviors.html)
  updates/         # Database migration files
  helpers/         # Utility/helper functions (e.g., StaffHelper, WorkflowHelper)
  classes/         # Jobs, events, listeners, abstractions, etc each within its own subfolder
  tests/           # PHPUnit tests extending PluginTestCase
  ```
- **Views**: Backend views use Twig templates located in `views/` within each plugin's controller (https://docs.octobercms.com/4.x/extend/system/views.html).
- **Themes**: Frontend themes (e.g., `thebakerdev-zenii`) use TailwindCSS and Laravel Mix for asset management. See each theme's `README.md` for build instructions.
- **Config**: All environment and service configuration is in `config/`.

## Developer Workflows
- **Install dependencies**: Use `composer install` for PHP and `npm install` in theme directories for JS/CSS.
- **Build assets**: For themes, run `npm run dev` or `npm run watch` in the theme directory (e.g., `themes/thebakerdev-zenii`).
- **Testing**:
  - Core and plugin tests: Run `phpunit` in the project root or in a plugin directory with a `phpunit.xml`.
  - Plugin tests use `PluginTestCase` and may require plugin registration/bootstrapping in `setUp()`.
  - To change the test DB engine, set `useConfigForTesting` in `config/database.php` or override with `config/testing/database.php`.
- **Database migrations**: Use `php artisan october:migrate` (core) and `php artisan plugin:refresh Vendor.Plugin` (will destroy db data so not to be run on production).

## Project-Specific Conventions
- **Service Layer**: Business logic is extracted into service classes (e.g., `AdjustmentService`, `PurchaseRequestService`) that handle complex operations and maintain separation of concerns.
- **Model Architecture**: Models use OctoberCMS models traits (https://docs.octobercms.com/4.x/extend/database/traits.html) and follow PHP 8.2 standards with return type declarations. Many models include morphTo relationships for flexible associations.
- **Plugin structure**: Always use OctoberCMS plugin conventions. TSI plugins follow modern PHP 8.0 standards with comprehensive PHPDoc blocks.
- **Testing**: Extend `PluginTestCase` for plugin tests. Register/boot plugins in `setUp()` if testing with dependencies.
- **Asset management**: Themes use TailwindCSS and Laravel Mix. Edit `tailwind.config.js` for theme customization.
- **Event-driven extensions**: Editor extensions are registered via the `editor.extension.register` event (see `modules/editor/README.md`).
- **Naming**: TSI plugins are namespaced under `Tsi\` and follow clear domain separation (Inventory, Procurement, Organization, etc.).

## Integration Points
- **Laravel Foundation**: OctoberCMS is built on Laravel 6.0; use Laravel features where appropriate, but follow OctoberCMS conventions for structure.
- **Vue Components**: Editor and some modules use Vue for client-side features (see `modules/editor/vuecomponents/`).
- **External assets**: Themes may use external icon sets, illustrations from [unDraw](https://undraw.co/), and Tailwind plugins.
- **Data Feeding**: The Feeder plugin provides activity tracking via morphTo relationships - models can have feeds that track user actions (e.g., `$user->first_name created this $model->title`).
- **Cross-plugin dependencies**: Many plugins reference each other (e.g., Workflow integrates with Organization for staff management, Inventory connects to Organization for site/unit structure).

## Domain-Specific Plugin Details
- **Organization**: Core plugin managing companies, sites, units, and staff with backend user extensions
- **Inventory**: Complex stock management with services for adjustments, transfers, and warehouse operations
- **Procurement**: Purchase request workflows with budget management and vendor relationships
- **Registrar**: Document numbering patterns and registration management
- **PettyCash**: Claims, registers, and reimbursement workflows
- **Workflow**: Approval processes that integrate across other plugins
- **Feeder**: Activity tracking and audit trails for model changes

## Common Patterns
- **Morphable relationships**: Many models use `morphTo` for flexible associations (e.g., `Feed` model can attach to any feedable model)
- **Backend user integration**: Extensive use of `BackendAuth::getUser()` for user context in business logic
- **Validation traits**: Models consistently use `\October\Rain\Database\Traits\Validation`

## References
- See `README.md` in root, themes, and plugins for more details.
- Plugin architecture overview in `plugins/omsb/README.md` with detailed documentation status
- For OctoberCMS plugin's development: https://docs.octobercms.com/4.x/extend/system/plugins.html
- For OctoberCMS conventions, see https://octobercms.com/help/guidelines/developer
- For OctoberCMS quality guidelines: https://octobercms.com/help/guidelines/quality
- For OctoberCMS API reference: https://docs.octobercms.com/4.x/element/form-fields.html
- For Laravel Mix/Tailwind, see theme `README.md` and `tailwind.config.js`.

---

**When in doubt, follow the structure and conventions of existing modules/plugins.**
