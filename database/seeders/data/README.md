# Catalogue export

The shop's catalogue, exported from the WooCommerce site at meva.rs on
2026-09-07 and imported by `Database\Seeders\Meva\CatalogueSeeder`.

| File | Contents |
| --- | --- |
| `products.json` | 73 products: prices, categories, set contents, descriptions, image paths |
| `categories.json` | 7 categories plus the old `Uncategorized` bucket |
| `redirects.csv` | 102 rows mapping old URLs to new paths, for the 301 map |
| `../assets/images/` | 108 photographs, one folder per product |

## What is deliberately not here

**Orders and customers.** The export also contains 5,480 orders and 3,964
customers with names, addresses, phone numbers and email addresses. None of it
is in this repository, and it should not be: seeding a development database with
real people's contact details spreads personal data with no upside. The original
export is outside the repository.

**Reviews.** All 12 exported reviews were unapproved spam carrying email
addresses, so nothing was worth importing.

**Marketing artwork.** `images/*/galerija/` in the original export holds
promotional graphics reused across products, not photographs of them. Only the
main shot and the studio photographs are carried over.

## Decisions the import makes

- **SKUs** are generated as `MEVA-{wp_id}`. The old shop had none, Lunar
  requires one, and deriving it from the WordPress id keeps a traceable link
  back to the original product and its order history.
- **Five products without a price** are imported as drafts rather than dropped
  or published. They were never finished in the old shop.
- **Sixteen products without a category** are parked in `Nesvrstano`. They need
  categorising, and this keeps them visible until someone does it.
- **Fourteen sets** keep their composition in `product_bundle_items`. Lunar has
  no native bundle type, and flattening them would lose what each set contains.
- **Stock starts at zero** everywhere. The old shop never tracked it, so there
  is no opening figure to carry over.

## Re-running

The seeders update rather than duplicate, so re-running after a fresh export is
safe. Images are only attached to a product that has none, so re-runs are fast.

```bash
php artisan db:seed --class="Database\Seeders\Meva\CatalogueSeeder"
```
