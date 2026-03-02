# WD Market Multi-Warehouse

A WooCommerce plugin for multi-warehouse inventory management with per-warehouse stock tracking, proximity-based fulfillment, and catalog filtering.

## Features

- **Multiple Warehouses** — Manage warehouses with name, address, coordinates, and active status
- **Per-Warehouse Stock** — Track inventory quantities per warehouse with automatic WooCommerce sync
- **Proximity-Based Allocation** — Auto-select nearest warehouse to customer at checkout using Haversine distance
- **Split Fulfillment** — Automatically split orders across warehouses when single warehouse lacks full stock
- **Geocoding** — Auto-detect coordinates from address via OpenStreetMap Nominatim (free) or Google Maps
- **Catalog Filtering** — Filter shop products by warehouse availability
- **Optional Shipping Surcharge** — Add fees when fulfilling from non-closest warehouse
- **HPOS Compatible** — Supports WooCommerce High-Performance Order Storage

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

## Installation

1. Download or clone to `wp-content/plugins/wd-market-multi-warehouse/`
2. Activate in **WordPress Admin → Plugins**

## Architecture

- **PSR-4 autoloading** with SOLID principles
- **Dependency injection** via composition root
- **Repository pattern** for data access
- **Twig 3.x** templating

## Author

**Radoslav Zdravkovic** — [GitHub](https://github.com/RadoZ-dev)

## License

GPL-2.0-or-later
