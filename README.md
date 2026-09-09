# TK Auto Gallery

Responsive folder-based WordPress gallery with shortcode, image lightbox, HTML5 video playback, wildcard filters, and optional generated thumbnail cache.

## Installation

1. Copy the `tk-auto-gallery` folder into `wp-content/plugins/`.
2. Activate **TK Auto Gallery** in WordPress.
3. Create folders below `wp-content/tk-auto-gallery/`.
4. Add images/videos to a gallery folder.

## Shortcode

```text
[tk_auto_gallery gallery="summer-2026" filter="*.{jpg,jpeg,png,webp,mp4,webm}" columns="auto" gap="10" thumb="1"]
```

## Attributes

| Attribute | Default | Description |
| --- | --- | --- |
| `gallery` | empty | Folder below `wp-content/tk-auto-gallery/`. Nested folders are allowed. |
| `filter` | `*.{jpg,jpeg,png,gif,webp,mp4,webm,ogg,mov}` | Wildcard filename filter. Supports comma lists and one brace group. |
| `columns` | `auto` | `auto` or `1` to `8`. On phones the layout is forced to two columns. |
| `gap` | `10` | Tile gap in pixels. |
| `thumb` | `1` | Enables generated thumbnails for images. Use `0` to disable. |
| `thumb_width` | `640` | Generated thumbnail width. |
| `sort` | `name` | `name`, `date`, or `extension`. |
| `order` | `asc` | `asc` or `desc`. |

## Video posters

For `clip.mp4`, add `clip.jpg`, `clip.png`, or `clip.webp` in the same folder to use it as tile poster and video poster.

## Lightbox viewer

The plugin includes `assets/vendor/tk-lightbox`, a tiny MIT-licensed viewer embedded directly in the plugin folder.
