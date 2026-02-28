# WD Market Multi-Warehouse

A WooCommerce plugin that adds multi-warehouse inventory management, enabling per-warehouse stock tracking, proximity-based warehouse selection at checkout, and catalog filtering by warehouse availability.

## Features

- **Multiple Warehouses** — Define warehouses with name, address, and active/inactive status.
- **Per-Warehouse Inventory** — Track stock quantities per warehouse instead of a single global number.
- **Smart Warehouse Selection** — Automatically allocate inventory from the closest warehouse to the customer's shipping address at checkout, with fallback to the next closest.
- **Oversell Prevention** — Stock is validated and reduced per warehouse on order completion.
- **Catalog Filtering** — Customers can filter products by warehouse availability on the shop page.
- **Optional Shipping Surcharge** — Apply additional shipping costs when orders are fulfilled from a non-closest warehouse, with a customer-facing notice.

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.0+
- Composer

## Installation

1. Clone or download this repository into `wp-content/plugins/`:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/RadoZ-dev/wd-market-multi-warehouse.git
   ```
2. Install PHP dependencies:
   ```bash
   cd wd-market-multi-warehouse
   composer install
   ```
3. Install front-end dependencies and build assets:
   ```bash
   npm install
   npm run build
   ```
4. Activate the plugin in **WordPress Admin → Plugins**.

## Development

```bash
composer install        # Install PHP dependencies
npm install             # Install Node dependencies
npm run start           # Watch mode — auto-recompile JS/SCSS
npm run build           # Production build
```

## Tech Stack

- **PHP** with PSR-4 autoloading and SOLID architecture
- **Twig 3.x** for templating
- **@wordpress/scripts** for JS/SCSS compilation

## Author

**Radoslav Zdravkovic** — [zdravkovicradoslav@gmail.com](mailto:zdravkovicradoslav@gmail.com)

## License

Proprietary. All rights reserved.
