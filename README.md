# OA Assistant

WordPress plugin providing a secure REST API for AI-driven Elementor page management.

**Version:** 1.1.0
**Author:** Olivier de Armenteras
**Requires:** WordPress 5.8+, PHP 7.4+

---

## Installation

1. Upload the `oa-assistant` folder to `/wp-content/plugins/`
   — or install via **Plugins → Add New → Upload Plugin** using the `.zip` file
2. Activate the plugin under **Plugins → Installed Plugins**
3. Go to **Settings → OA Assistant** to copy or regenerate your API key
4. Use the API key as the `X-OA-Key` header in all requests

---

## Authentication

Every endpoint requires the header:

```
X-OA-Key: <your-api-key>
```

The key is stored in WordPress options and can be managed at **Settings → OA Assistant**.

---

## Endpoints

### GET `/wp-json/oa/v1/status`

Returns plugin and environment info.

**Response:**
```json
{
  "ok": true,
  "plugin": "OA Assistant",
  "version": "1.1.0",
  "wordpress": "6.5.0",
  "site_url": "https://example.com",
  "elementor_active": true,
  "elementor_version": "3.21.0"
}
```

---

### GET `/wp-json/oa/v1/pages`

Returns all pages with Elementor status.

**Response:**
```json
[
  {
    "id": 197,
    "title": "Werkwijze",
    "slug": "werkwijze",
    "status": "publish",
    "link": "https://example.com/werkwijze/",
    "modified": "2024-01-15 12:34:56",
    "elementor": true,
    "elementor_mode": "builder"
  }
]
```

---

### GET `/wp-json/oa/v1/progress`

Returns current progress data from `wp-content/uploads/oa-dashboard/progress.json`.

**Response (example):**
```json
{
  "active": true,
  "percentage": 45,
  "current_task": "Building Werkwijze page - section 3/6",
  "steps": [
    { "label": "Hero section", "status": "completed" },
    { "label": "About section", "status": "completed" },
    { "label": "Services section", "status": "in_progress" },
    { "label": "Testimonials", "status": "pending" }
  ]
}
```

**Response (when no progress file exists):**
```json
{
  "active": false,
  "percentage": 0,
  "steps": []
}
```

---

### POST `/wp-json/oa/v1/progress`

Writes progress data to `wp-content/uploads/oa-dashboard/progress.json`.

**Body:**
```json
{
  "active": true,
  "percentage": 60,
  "current_task": "Building Contact page",
  "steps": [
    { "label": "Hero section", "status": "completed" }
  ]
}
```

**Response:**
```json
{ "ok": true }
```

---

### POST `/wp-json/oa/v1/elementor-write`

Writes Elementor JSON data to a page's post meta and clears caches.

**Body:**
```json
{
  "page_id": 197,
  "elementor_data": "[{\"id\":\"abc123\",\"elType\":\"section\",...}]"
}
```

**Response:**
```json
{
  "ok": true,
  "page_id": 197
}
```

**What it does:**
- Sets `_elementor_data` post meta (the full page JSON)
- Sets `_elementor_edit_mode` to `builder`
- Sets `_elementor_template_type` to `wp-page`
- Clears Elementor CSS cache
- Purges LiteSpeed, W3 Total Cache, WP Super Cache, WP Rocket (whichever is active)

---

### POST `/wp-json/oa/v1/elementor-flush`

Regenerates Elementor CSS files and purges all caches for a page.

**Body:**
```json
{
  "page_id": 197
}
```

**Response:**
```json
{ "ok": true }
```

**What it does:**
- Triggers `elementor/core/files/clear_cache` action
- Calls `Elementor\Plugin::$instance->files_manager->clear_cache()`
- Purges LiteSpeed cache (post + global)
- Purges W3 Total Cache / WP Rocket (whichever is active)

---

## Example: cURL

```bash
# Status check
curl -H "X-OA-Key: your-key-here" https://example.com/wp-json/oa/v1/status

# Get all pages
curl -H "X-OA-Key: your-key-here" https://example.com/wp-json/oa/v1/pages

# Write Elementor data
curl -X POST \
  -H "X-OA-Key: your-key-here" \
  -H "Content-Type: application/json" \
  -d '{"page_id": 197, "elementor_data": "[...]"}' \
  https://example.com/wp-json/oa/v1/elementor-write

# Set progress
curl -X POST \
  -H "X-OA-Key: your-key-here" \
  -H "Content-Type: application/json" \
  -d '{"active": true, "percentage": 50, "current_task": "Working..."}' \
  https://example.com/wp-json/oa/v1/progress
```

---

## Progress JSON Schema

The progress file at `uploads/oa-dashboard/progress.json` follows this structure:

| Field | Type | Description |
|-------|------|-------------|
| `active` | boolean | Whether Claude is currently working |
| `percentage` | integer | 0–100 completion percentage |
| `current_task` | string | Current task description |
| `steps` | array | List of step objects |
| `steps[].label` | string | Step description |
| `steps[].status` | string | `completed`, `in_progress`, `current`, or `pending` |

---

## License

MIT
