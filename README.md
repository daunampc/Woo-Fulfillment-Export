# Woo Fulfillment Export

Plugin WordPress/WooCommerce để xuất order fulfillment theo template CSV hoặc XLSX, hỗ trợ mapping placeholder, filter order, variable product và WCPA/Product Addon meta.

## Chức năng

- Menu riêng: **Fulfillment Export** với các tab Orders, Templates, Mapping, Settings.
- Upload template `.csv` hoặc `.xlsx` vào `wp-content/uploads/woo-fulfillment-export/templates/`.
- Chặn upload định dạng khác bằng kiểm tra extension và MIME.
- Tạo template thủ công CSV/XLSX trong admin, gồm header, mapping và default value.
- Mapping theo cột, đọc header dòng đầu của CSV và đọc sheet/header row của XLSX nếu `ZipArchive` khả dụng.
- Preview mapping bằng một order mẫu.
- Export theo order được chọn hoặc export trực tiếp theo status/filter.
- Filter theo status, ngày, customer keyword, product name/ID, SKU và product category.
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

## Ghi chú kỹ thuật

- Product/SKU/category/customer filters được xử lý an toàn bằng cách query order theo status/date trước bằng `wc_get_orders()`, sau đó lọc trong PHP.
- Vào **Settings** để chỉnh `Export batch limit` và `Filter scan limit` cho shop lớn.
- Template XLSX upload được giữ layout gốc và ghi dữ liệu vào sheet/start row đã chọn. Template XLSX thủ công tạo workbook đơn giản gồm header và data rows.
- Legacy template cũ trong `wp-content/uploads/wfe-templates/` vẫn được đọc/xóa nếu tồn tại; upload mới dùng thư mục chuẩn mới.

## TODO / giới hạn hiện tại

- Chưa có background queue/export async. Với shop rất lớn, nên dùng batch nền thay vì export trong một request admin.
- XLSX inspector đọc sheet/header phổ biến nhưng chưa xử lý đầy đủ mọi workbook phức tạp có relationship tùy biến hoặc công thức đặc biệt.
- Chưa có export log hoặc đánh dấu order đã export.
# Woo-Fulfillment-Export
