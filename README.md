# Woo Fulfillment Export

Plugin WordPress/WooCommerce để xuất order fulfillment theo template CSV hoặc XLSX, hỗ trợ mapping placeholder, filter order, variable product và WCPA/Product Addon meta.

## Chức năng

- Menu riêng: **Fulfillment Export** với các tab Orders, Templates, Mapping, API Connections, Settings.
- Upload template `.csv` hoặc `.xlsx` vào `wp-content/uploads/woo-fulfillment-export/templates/`.
- Chặn upload định dạng khác bằng kiểm tra extension và MIME.
- Tạo template thủ công CSV/XLSX trong admin, gồm header, mapping và default value.
- Mapping theo cột, đọc header dòng đầu của CSV và đọc sheet/header row của XLSX nếu `ZipArchive` khả dụng.
- Preview mapping bằng một order mẫu.
- Export theo order được chọn hoặc export trực tiếp theo status/filter.
- Export toolbar có icon, select template đẹp hơn và action nhanh cho đơn đã chọn.
- Bulk action cho order đã chọn: Mark Fulfillment hoặc Back to Processing.
- Filter theo status, ngày, Order ID/Number, customer keyword, product name/ID, SKU và product category.
- Dynamic API mapping qua API Connections, ví dụ `{api:vtn_tasksave_url:{product_sku}}`.
- Khi export xong, order tự chuyển sang trạng thái Fulfillment.
- Hai chế độ dòng: mỗi order item là một dòng hoặc mỗi order là một dòng.
- Dùng WooCommerce CRUD (`wc_get_orders`, `wc_get_order`, order item APIs), không query trực tiếp bảng order, phù hợp hơn với HPOS.
- Lấy SKU variation trước, fallback về parent SKU.
- Lấy category từ product cha khi item là variation.
- Đọc WCPA/Product Addon từ line item meta, không yêu cầu plugin addon đang active.
- Không cần Composer/PhpSpreadsheet. XLSX được xử lý bằng `ZipArchive` + XML.

## Yêu cầu

- WordPress.
- WooCommerce.
- PHP 7.4+.
- PHP extension `ZipArchive` để đọc/xuất XLSX. CSV vẫn hoạt động nếu thiếu `ZipArchive`.

## Cài đặt

1. Upload plugin vào WordPress Admin > Plugins.
2. Activate plugin.
3. Vào **Fulfillment Export > Templates** để upload CSV/XLSX hoặc tạo template thủ công.
4. Vào **Mapping** để cấu hình cột và preview bằng order mẫu.
5. Vào **Orders** để filter và export.

## Placeholder example

```txt
{order_number}
#{order_number}
{product_name} - {variation_attributes}
{billing_full_name} / {billing_phone}
{wcpa:engraving_text}
{api:vtn_tasksave_url:{product_sku}}
{order_meta:delivery_code}
{item_meta:custom_line_value}
```

## Placeholder chính

Order:

- `{order_id}`
- `{order_number}`
- `{order_status}`
- `{order_date}`
- `{payment_method}`
- `{shipping_method}`
- `{order_total}`
- `{order_subtotal}`
- `{order_discount}`
- `{order_shipping_total}`
- `{customer_note}`

Billing / shipping:

- `{billing_full_name}`
- `{billing_phone}`
- `{billing_email}`
- `{billing_full_address}`
- `{shipping_full_name}`
- `{shipping_phone}`
- `{shipping_full_address}`

Product / variation:

- `{product_id}`
- `{variation_id}`
- `{product_name}`
- `{product_sku}`
- `{product_categories}`
- `{product_image}` hoặc `{product_image_url}`
- `{product_image_id}`
- `{quantity}`
- `{line_subtotal}`
- `{line_total}`
- `{variation_attributes}`
- `{variation:size}`, `{variation:color}`, `{variation:pa_size}`

WCPA / Product Addon / meta:

- `{wcpa:field_name}`
- `{wcpa:field_label}`
- `{wcpa:Size}` để lấy value theo label hoặc name, không phân biệt hoa thường
- `{wcpa_all}` trong chế độ mỗi order là một dòng
- `{order_meta:meta_key}`
- `{item_meta:meta_key}`

Nếu placeholder không có dữ liệu, plugin trả về chuỗi rỗng.

## Filter Orders

- **SKU**: nhập một phần SKU để match simple product SKU, variation SKU, hoặc parent product SKU nếu variation không có SKU. Search không phân biệt hoa thường.
- **Order ID / Number**: nhập `263950`, `#263950`, hoặc một phần order number nếu shop dùng custom order number.
- Các filter Order ID/Number, SKU, status, date range, customer, product và category có thể kết hợp với nhau.


## Bulk status actions

Trong trang **Orders**, chọn nhiều order bằng checkbox rồi dùng nhóm **Bulk status**:

- **Mark Fulfillment**: chuyển các order đã chọn sang Fulfillment.
- **Back to Processing**: chuyển các order đã chọn về Processing.

Nhóm action này dùng chung nonce/quyền `manage_woocommerce` và không ảnh hưởng đến export AJAX.

## API Connections

Vào **Fulfillment Export > API Connections** để tạo connection cho dynamic mapping.

Connection mẫu VTN TaskSave:

```txt
Connection name: VTN TaskSave
Connection key: vtn_tasksave_url
Base URL: https://api.vtnpoddesign.com/api/tasks/tasksave
HTTP method: GET
Default params:
page=1
limit=32
Dynamic query param: query
Response path: data.0.URL
Timeout: 15
Cache TTL: 3600
```

Header có thể thêm trong form, ví dụ:

```txt
Authorization: Bearer xxx
X-API-Key: xxx
Accept: application/json
```

Sau khi lưu, header value được mask trong UI; để trống khi edit sẽ giữ secret cũ.

## API Dynamic Mapping

Placeholder API:

```txt
{api:vtn_tasksave_url:a}
{api:vtn_tasksave_url:{product_sku}}
{api:vtn_tasksave_url:{order_number}}
{api:vtn_tasksave_url:{wcpa:Size}}
{api:vtn_tasksave_url:{product_name}}
```

Plugin resolve placeholder lồng bên trong query trước, gọi API bằng WordPress HTTP API, parse JSON, rồi lấy giá trị theo response path `data.0.URL`. Nếu API lỗi hoặc không có dữ liệu, cell export rỗng và lỗi được ghi vào `error_log()`.

Cache API dùng transient theo `connection key + query`, đồng thời có memory cache trong cùng request export để tránh gọi trùng nhiều lần.

## Ghi chú kỹ thuật

- Product/SKU/category/customer/order-number filters được xử lý an toàn bằng cách query order theo status/date trước bằng `wc_get_orders()`, sau đó lọc trong PHP.
- Vào **Settings** để chỉnh `Export batch limit` và `Filter scan limit` cho shop lớn.
- Template XLSX upload được giữ layout gốc và ghi dữ liệu vào sheet/start row đã chọn. Template XLSX thủ công tạo workbook đơn giản gồm header và data rows.
- Legacy template cũ trong `wp-content/uploads/wfe-templates/` vẫn được đọc/xóa nếu tồn tại; upload mới dùng thư mục chuẩn mới.

## TODO / giới hạn hiện tại

- Chưa có background queue/export async. Với shop rất lớn, nên dùng batch nền thay vì export trong một request admin.
- XLSX inspector đọc sheet/header phổ biến nhưng chưa xử lý đầy đủ mọi workbook phức tạp có relationship tùy biến hoặc công thức đặc biệt.
- Chưa có export log hoặc đánh dấu order đã export.

## Version 1.2.0 changes

- Orders page now uses a wider, full-width admin layout.
- Orders pagination has been restyled to look closer to WooCommerce/Product list pagination.
- The order status filter is intentionally limited to **Processing** and **Fulfillment** for the fulfillment workflow.
- Added custom WooCommerce order status: **Fulfillment** (`wc-fulfillment`).
- Added a row action button on the Orders page:
  - Processing order: `Mark fulfillment`
  - Fulfillment order: `Back to processing`
- Added visual status badges so Processing and Fulfillment orders are easier to distinguish.
- Hardened the default status handling so bad or unsupported status filters do not break the Orders page.

## Version 1.3.0

### AJAX export

Orders are now exported with AJAX batches from the Orders page. This avoids long single requests on large exports. Configure the batch size in **Fulfillment Export > Settings > AJAX export chunk size**.

After an order is successfully processed during export, its status is automatically moved to **Fulfillment**. Existing Fulfillment orders are skipped and not changed again.

### Orders per page

The Orders page now supports a configurable limit. Use the **Orders per page** dropdown on the Orders page or set the default in **Settings > Orders per page**.

### GitHub updates

The plugin checks GitHub releases from the fixed repository `https://github.com/daunampc/Woo-Fulfillment-Export`.

1. Go to **Fulfillment Export > Settings**.
2. Keep the fallback branch as `main` unless your default branch is different.
3. Optionally set a GitHub token for private repositories or private release assets.
4. Create GitHub releases with version tags such as `v1.3.2`.

The updater reads the latest GitHub release and compares its tag against the installed plugin version.


## Version 1.3.1

- GitHub updater repository is now fixed to `https://github.com/daunampc/Woo-Fulfillment-Export`.
- Settings no longer require entering `owner/repo`; only branch fallback and optional token remain.
