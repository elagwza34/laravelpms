# Product Module — Phase 2

Backend product module for the multi-tenant PMS. Built on the Phase 1 tenancy,
authentication and permission architecture; nothing in that foundation was
replaced.

---

## 1. Product types

Exactly two. **Service**, **bundle** and **composite** are not part of the model
and no architecture exists for them.

| Type       | Meaning                                                              |
|------------|----------------------------------------------------------------------|
| `simple`   | One stock-keeping unit with its own SKU, barcode and pricing.         |
| `variable` | One inventory item offered in several option combinations.            |

`App\Enums\ProductType` — `simple`, `variable`.

---

## 2. Tables

| Table                      | Purpose                                             | Key constraints |
|----------------------------|-----------------------------------------------------|-----------------|
| `products`                 | The commercial + inventory entity                   | `UNIQUE(company_id, sku)`, `UNIQUE(company_id, barcode)`, `UNIQUE(id, company_id)` |
| `categories`               | Nested product categories                           | `UNIQUE(company_id, slug)`, `parent_id` self-FK `nullOnDelete` |
| `product_category`         | Product ↔ Category (many-to-many)                   | `UNIQUE(product_id, category_id)` |
| `brands`                   | Optional brand, max one per product                 | `UNIQUE(company_id, slug)` |
| `units`                    | Reusable company-level units                        | `UNIQUE(company_id, name)` |
| `product_units`            | Units a product is sold in, price + conversion      | `UNIQUE(product_id, unit_id)` |
| `attributes`               | Company-level attributes                            | `UNIQUE(company_id, slug)`, `UNIQUE(id, company_id)` |
| `attribute_values`         | Values of an attribute                              | `UNIQUE(attribute_id, value)`, composite FK `(attribute_id, company_id)` |
| `product_variants`         | Option combinations of a variable product           | `UNIQUE(product_id, combination_key)`, composite FK `(product_id, company_id)` |
| `variant_attribute_values` | Which values make up a variant                      | `UNIQUE(product_variant_id, attribute_value_id)` |
| `suppliers`                | Suppliers                                           | `UNIQUE(company_id, slug)` |
| `product_suppliers`        | Per-supplier purchase unit + price                 | `UNIQUE(product_id, supplier_id)` |

### Composite foreign keys

`attribute_values` and `product_variants` each carry a **composite FK** tying
their `company_id` to their parent's, which makes cross-company rows impossible
even via a hand-written query:

```sql
FOREIGN KEY (attribute_id, company_id) REFERENCES attributes(id, company_id)
FOREIGN KEY (product_id,  company_id) REFERENCES products(id,  company_id)
```

---

## 3. Product structure

```
Product
├── brand_id        → 0..1 Brand
├── categories      → many (product_category)
├── supplierTerms   → many ProductSupplier (per-supplier unit + price)
├── units           → many ProductUnit (base unit, conversions, prices)
├── variants        → many ProductVariant (VARIABLE only, no commercial data)
└── image           → single storage path (no gallery, no binary in DB)
```

Fields: `name`, `sku`, `barcode`, `product_type`, `short_description`,
`description`, `image`, `brand_id`, `minimum_stock`, `tax_type`, `tax_value`,
`status`, timestamps, `deleted_at`.

**Absent by design:** `maximum_stock`, `cost` (cost lives on
`product_suppliers`), image gallery.

---

## 4. SKU & barcode

- **Exactly one** SKU per product, unique **within the tenant**.
- **Exactly one** barcode per product, unique **within the tenant**.
- Both nullable — a product may exist before it is barcoded.
- No variant-level SKU or barcode.

Uniqueness is per company, so two companies may legitimately share a code.

---

## 5. Variable products

A variable product is still **one inventory item**.

```
T-Shirt (variable)
├── attributes: Color [Black, White], Size [S, M]
├── units:      Piece (base, factor 1, price 1500)
└── variants:   Black/S, Black/M, White/S, White/M
```

**`product_variants` has NO** `sku`, `barcode`, `selling_price`, `cost`,
`tax_type`, `tax_value` or `stock`. All of that belongs to the product.

`combination_key` is built from the **sorted** attribute value ids
(`"12-45-91"`), so `[12,45]` and `[45,12]` are the same combination and collide
instead of creating a duplicate. `UNIQUE(product_id, combination_key)` is the
real guard against a concurrent race.

Supply `attribute_value_ids` to generate the full matrix, or `variants`
explicitly for a subset.

---

## 6. Units & conversion

A unit is company master data. The **conversion rate belongs to the
product/unit pairing**: "1 Carton = 12 Pieces" is a statement about one product,
not about Cartons in general.

```json
"units": [
  { "unit_id": 1, "conversion_factor": 1,  "selling_price": 1500,  "is_base": true },
  { "unit_id": 2, "conversion_factor": 12, "selling_price": 16500, "is_base": false },
  { "unit_id": 3, "conversion_factor": 24, "selling_price": 31000, "is_base": false }
]
```

- Exactly one unit must have `is_base = true`.
- The base unit's `conversion_factor` must be exactly `1`.
- Every factor must be `> 0`; the same unit cannot be listed twice.
- `selling_price` is set **per unit by the company**, never derived.
- Inventory is denominated in the base unit: selling 2 Boxes of factor 12 moves
  24 base units (`ProductUnit::toBaseQuantity()`).

---

## 7. Supplier pricing

There is **no default supplier and no cost column on the product**. The same
product costs a different amount from each supplier, in a different unit:

```json
"supplier_terms": [
  { "supplier_id": 1, "purchase_unit_id": 3, "purchase_price": 30000 },
  { "supplier_id": 2, "purchase_unit_id": 2, "purchase_price": 16000 },
  { "supplier_id": 3, "purchase_unit_id": 1, "purchase_price":  1500 }
]
```

- `purchase_unit_id` must be one of **this product's** units.
- The same supplier cannot be attached twice.

---

## 8. Categories & brands

**Categories** nest via `parent_id`. A parent must belong to the same company,
and a category can never become its own ancestor (cycle protection).

**Brands** are optional: a product may have none or exactly one.

Both are company-scoped, reusable master data.

---

## 9. Attributes

**Company-level only — there are no global attributes.** Company A owns
Color/Size/Fabric; Company B owns Storage/RAM/Capacity; neither can reference
the other.

```json
POST /api/v1/{company}/attributes
{ "name": "Color", "values": ["Black", "White", "Red"] }
```

Values may be created inline or one at a time under
`/{company}/attributes/{attribute}/values`.

---

## 10. Tax

Product-level configuration only — not a taxation engine.

| `tax_type`   | `tax_value` | Meaning          |
|--------------|-------------|------------------|
| `percentage` | `14`        | 14% of the price |
| `fixed`      | `20`        | 20 per unit      |

A product with no tax leaves **both** columns null (no third "none" value is
invented). Setting one without the other is rejected.

---

## 11. Status & deletion

| Status     | Behaviour                                                     |
|------------|---------------------------------------------------------------|
| `active`   | Usable in new operations.                                     |
| `inactive` | Kept and visible in history; not selectable for new work.      |

Deletion is a **soft delete**. Sales, purchases and stock movements in later
phases must stay referentially intact, so products are archived, never dropped.

---

## 12. Tenancy rules

Every tenant-owned table carries `company_id` and uses the existing
`BelongsToCompany` trait + `CompanyScope` global scope.

**The `{company}` slug is a lookup hint, never the security boundary.**

```
auth:sanctum → tenant (ResolveTenant) → permission → subscription.active
                    ↑
        proves an ACTIVE MEMBERSHIP for {company}
```

Isolation is enforced in four independent layers:

1. **Read scoping** — `CompanyScope` appends `company_id = ?` to every query.
2. **Write stamping** — `company_id` is set from server-side context and is
   absent from every `$fillable`, so a payload cannot choose it.
3. **Validation** — `tenantExists()` constrains every foreign id with
   `company_id = <active tenant>`, so a foreign brand/category/unit/supplier/
   attribute value is "not found" rather than accepted.
4. **Database** — composite foreign keys make cross-company variant and
   attribute-value rows impossible.

A client-supplied `company_id` is **rejected with 422**, not silently ignored,
so an attempted spoof is visible.

### Route-model binding is not used for products

Laravel's `SubstituteBindings` runs in the global API stack, *before*
`ResolveTenant`. A route-bound model would resolve with no tenant active and the
scope would apply no filter. Products are resolved inside the controller
(`ProductController::findProduct()`), after every middleware.

---

## 13. Permissions

Added to the existing `PermissionCatalog`:

```
products.view  products.create  products.update  products.delete
categories.*   brands.*        units.*          suppliers.*
attributes.*
```

Declared per route (`permission:products.create`). A role name grants nothing on
its own — authorisation always resolves through permissions.

---

## 14. API endpoints

All tenant-scoped, requiring an authenticated **active membership**.

### Products
```
GET    /api/v1/{company}/products                       products.view
POST   /api/v1/{company}/products                       products.create
GET    /api/v1/{company}/products/{product}             products.view
PUT    /api/v1/{company}/products/{product}             products.update
PATCH  /api/v1/{company}/products/{product}             products.update
DELETE /api/v1/{company}/products/{product}             products.delete
POST   /api/v1/{company}/products/{product}/activate    products.update
POST   /api/v1/{company}/products/{product}/deactivate  products.update
```

Index query parameters: `search` (name/SKU/barcode), `status`, `product_type`,
`brand_id`, `category_id`, `per_page`, `page`.

### Master data
```
GET|POST         /api/v1/{company}/brands
GET|PUT|DELETE   /api/v1/{company}/brands/{brand}
…categories, /units, /suppliers      (same shape)

GET|POST         /api/v1/{company}/attributes
GET|PUT|DELETE   /api/v1/{company}/attributes/{attribute}
GET|POST         /api/v1/{company}/attributes/{attribute}/values
GET|PUT|DELETE   /api/v1/{company}/attributes/{attribute}/values/{value}
```

### Response format

```json
{ "success": true, "data": { ... } }
```

Paginated responses add `meta` (`current_page`, `last_page`, `per_page`,
`total`, `from`, `to`). Errors keep the Phase 1 envelope:
`{ "message", "error_code", "errors" }`.

---

## 15. Not implemented (by design)

- ❌ Stock movements, warehouses, transfers, adjustments
- ❌ Purchase / sales invoices, POS
- ❌ Inventory ledger
- ❌ WooCommerce synchronisation
- ❌ Reports
- ❌ Bulk import/export, barcode generation, product history

`minimum_stock` is stored as configuration only; the low-stock rule lives on
`Product::isLowStock()` for the inventory phase to use.