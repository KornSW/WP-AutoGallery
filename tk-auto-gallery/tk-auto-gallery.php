<?php
/**
 * Plugin Name: TK Auto Gallery
 * Update URI: https://raw.githubusercontent.com/KornSW/WP-AutoGallery/master/doc/tk-auto-gallery.update.json
 * Plugin URI: https://github.com/KornSW/WP-AutoGallery
 * Description: Lightweight folder-based gallery with row-first justified layout, inline lightbox and video support.
 * Version: 1.2.2
 * Author: TK
 */

if (!defined('ABSPATH')) {
    exit;
}


/*************** SELF-UPDATE ***************/
define( 'KSWTKAUTOGALLERF54A_SELF_UPDATE_DIAGNOSTICS', false );
require_once __DIR__ . '/self-update.php';
kswtkautogallerf54a_bootstrap( __FILE__ );
/*******************************************/

final class TkAutoGalleryPlugin {

    private const PluginVersion = '1.2.1';

    /**
     * Constructor.
     */
    public function __construct() {

        add_shortcode(
            'tk_auto_gallery',
            array($this, 'RenderShortcode')
        );
    }

    /**
     * Renders the gallery shortcode.
     *
     * @param array<string, mixed> $Attributes
     *
     * @return string
     */
    public function RenderShortcode(array $Attributes): string {

        $Attributes = shortcode_atts(
            array(
                'gallery' => '',
                'filter' => '*',
                'gap' => '10',
                'thumb' => '1',
                'row_height' => '230',
                'mobile_row_height' => '150',
                'max_zoom' => '1.10'
            ),
            $Attributes,
            'tk_auto_gallery'
        );

        $GalleryName = trim((string)$Attributes['gallery']);
        $GalleryName = str_replace('\\', '/', $GalleryName);
        $GalleryName = trim($GalleryName, '/');

        if ($GalleryName === '' || str_contains($GalleryName, '..')) {
            return '<div class="tkag-error">Invalid gallery name.</div>';
        }

        $GalleryRootPath = WP_CONTENT_DIR . '/tk-auto-gallery/' . $GalleryName;

        if (!is_dir($GalleryRootPath)) {
            return '<div class="tkag-error">Gallery folder not found: ' . esc_html($GalleryRootPath) . '</div>';
        }

        $FilterPatterns = $this->BuildFilterPatterns((string)$Attributes['filter']);
        $MediaFiles = $this->FindMediaFiles($GalleryRootPath, $FilterPatterns);

        if (count($MediaFiles) === 0) {
            return '<div class="tkag-empty">No matching media files found.</div>';
        }

        $Gap = max(0, intval((string)$Attributes['gap']));
        $RowHeight = max(80, intval((string)$Attributes['row_height']));
        $MobileRowHeight = max(80, intval((string)$Attributes['mobile_row_height']));
        $MaxZoom = min(1.25, max(1.0, floatval((string)$Attributes['max_zoom'])));

        $MediaItems = $this->BuildMediaItems(
            $GalleryName,
            $GalleryRootPath,
            $MediaFiles,
            ((string)$Attributes['thumb']) === '1'
        );

        $Rows = $this->BuildRows($MediaItems);
        $GalleryId = 'tkag_' . md5(uniqid('', true));

        $Html = '';

        $Html .= '<div ';
        $Html .= 'id="' . esc_attr($GalleryId) . '" ';
        $Html .= 'class="tkag-justified-gallery" ';
        $Html .= 'style="display:flex!important;flex-direction:column!important;gap:' . $Gap . 'px!important;width:100%!important;box-sizing:border-box!important;--tkag-mobile-row-height:' . $MobileRowHeight . 'px;--tkag-gap:' . $Gap . 'px;"';
        $Html .= '>';

        foreach ($Rows as $Row) {
            $Html .= $this->RenderRow($Row, $Gap, $RowHeight, $MaxZoom);
        }

        $Html .= '</div>';

        $Html .= $this->RenderInlineCss($GalleryId);
        $Html .= $this->RenderInlineLightboxScript();

        return $Html;
    }

    /**
     * Builds wildcard filter patterns.
     *
     * @param string $Filter
     *
     * @return string[]
     */
    private function BuildFilterPatterns(string $Filter): array {

        $Filter = trim($Filter);

        if ($Filter === '') {
            return array('*');
        }

        $Parts = explode('|', $Filter);
        $Result = array();

        foreach ($Parts as $Part) {
            $Part = trim($Part);

            if ($Part !== '') {
                $Result[] = $Part;
            }
        }

        if (count($Result) === 0) {
            $Result[] = '*';
        }

        return $Result;
    }

    /**
     * Finds matching files.
     *
     * @param string $Directory
     * @param string[] $Patterns
     *
     * @return string[]
     */
    private function FindMediaFiles(string $Directory, array $Patterns): array {

        $Files = scandir($Directory);

        if (!is_array($Files)) {
            return array();
        }

        $Result = array();

        foreach ($Files as $FileName) {

            if ($FileName === '.' || $FileName === '..') {
                continue;
            }

            $AbsolutePath = $Directory . '/' . $FileName;

            if (!is_file($AbsolutePath)) {
                continue;
            }

            foreach ($Patterns as $Pattern) {

                if (fnmatch($Pattern, $FileName, FNM_CASEFOLD)) {
                    $Result[] = $FileName;
                    break;
                }
            }
        }

        sort($Result);

        return $Result;
    }

    /**
     * Builds media items.
     *
     * @param string $GalleryName
     * @param string $GalleryRootPath
     * @param string[] $MediaFiles
     * @param bool $UseThumbs
     *
     * @return array<int, array<string, mixed>>
     */
    private function BuildMediaItems(
        string $GalleryName,
        string $GalleryRootPath,
        array $MediaFiles,
        bool $UseThumbs
    ): array {

        $Result = array();

        foreach ($MediaFiles as $FileName) {

            $Extension = strtolower(pathinfo($FileName, PATHINFO_EXTENSION));
            $IsVideo = in_array($Extension, array('mp4', 'webm', 'ogg', 'mov'), true);

            $RelativeUrl = content_url(
                'tk-auto-gallery/' . $this->EncodeRelativeUrlPath($GalleryName . '/' . $FileName)
            );

            $AbsolutePath = $GalleryRootPath . '/' . $FileName;

            $Width = 1600;
            $Height = 900;

            if (!$IsVideo) {
                $ImageSize = @getimagesize($AbsolutePath);

                if (is_array($ImageSize)) {
                    $Width = max(1, intval($ImageSize[0]));
                    $Height = max(1, intval($ImageSize[1]));
                }
            }

            $PreviewUrl = $RelativeUrl;

            if (!$IsVideo && $UseThumbs) {
                $ThumbUrl = $this->GetThumbnailUrl($AbsolutePath, $GalleryName, $FileName);

                if ($ThumbUrl !== '') {
                    $PreviewUrl = $ThumbUrl;
                }
            }

            $Ratio = $Width / $Height;

            $Result[] = array(
                'url' => $RelativeUrl,
                'previewUrl' => $PreviewUrl,
                'width' => $Width,
                'height' => $Height,
                'ratio' => $Ratio,
                'isVideo' => $IsVideo
            );
        }

        return $Result;
    }

    /**
     * Builds row-first groups while avoiding lonely portrait rows.
     *
     * @param array<int, array<string, mixed>> $Items
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function BuildRows(array $Items): array {

        $Rows = array();
        $CurrentRow = array();
        $CurrentRatioSum = 0.0;

        foreach ($Items as $Item) {

            $Ratio = (float)$Item['ratio'];

            if ($Ratio >= 2.45) {

                if (count($CurrentRow) > 0) {
                    $Rows[] = $CurrentRow;
                    $CurrentRow = array();
                    $CurrentRatioSum = 0.0;
                }

                $Rows[] = array($Item);
                continue;
            }

            $CurrentRow[] = $Item;
            $CurrentRatioSum += $Ratio;

            if (count($CurrentRow) >= 4) {
                $Rows[] = $CurrentRow;
                $CurrentRow = array();
                $CurrentRatioSum = 0.0;
                continue;
            }

            if (count($CurrentRow) >= 2 && $CurrentRatioSum >= 3.35) {
                $Rows[] = $CurrentRow;
                $CurrentRow = array();
                $CurrentRatioSum = 0.0;
            }
        }

        if (count($CurrentRow) > 0) {
            $Rows[] = $CurrentRow;
        }

        $Rows = $this->RepairWeakLastRows($Rows);

        return $Rows;
    }

    /**
     * Moves lonely portrait or weak final rows into previous rows.
     *
     * @param array<int, array<int, array<string, mixed>>> $Rows
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function RepairWeakLastRows(array $Rows): array {

        if (count($Rows) < 2) {
            return $Rows;
        }

        $LastIndex = count($Rows) - 1;
        $LastRow = $Rows[$LastIndex];

        if (count($LastRow) === 1) {
            $OnlyItem = $LastRow[0];
            $OnlyRatio = (float)$OnlyItem['ratio'];

            if ($OnlyRatio < 2.45) {
                unset($Rows[$LastIndex]);
                $PreviousIndex = count($Rows) - 1;
                $Rows[$PreviousIndex][] = $OnlyItem;
                $Rows = array_values($Rows);
            }
        }

        return $Rows;
    }

    /**
     * Renders one row.
     *
     * @param array<int, array<string, mixed>> $Row
     * @param int $Gap
     * @param int $BaseRowHeight
     * @param float $MaxZoom
     *
     * @return string
     */
    private function RenderRow(array $Row, int $Gap, int $BaseRowHeight, float $MaxZoom): string {

        $RatioSum = 0.0;

        foreach ($Row as $Item) {
            $RatioSum += (float)$Item['ratio'];
        }

        $IsSinglePano = count($Row) === 1 && $RatioSum >= 2.45;
        $RowHeight = $BaseRowHeight;

        if ($IsSinglePano) {
            $RowHeight = max(110, intval($BaseRowHeight * 0.72));
        }

        $Html = '';

        $Html .= '<div class="tkag-row" style="display:flex!important;flex-direction:row!important;gap:' . $Gap . 'px!important;width:100%!important;height:' . $RowHeight . 'px!important;box-sizing:border-box!important;overflow:hidden!important;">';

        foreach ($Row as $Item) {

            $Ratio = (float)$Item['ratio'];
            $FlexGrow = max(450, intval($Ratio * 1000));
            $Zoom = 1.0;

            if (!$IsSinglePano && count($Row) > 1) {
                $Zoom = min($MaxZoom, 1.04);
            }

            $Html .= $this->RenderMediaTile($Item, $FlexGrow, $Zoom);
        }

        $Html .= '</div>';

        return $Html;
    }

    /**
     * Renders one media tile.
     *
     * @param array<string, mixed> $Item
     * @param int $FlexGrow
     * @param float $Zoom
     *
     * @return string
     */
    private function RenderMediaTile(array $Item, int $FlexGrow, float $Zoom): string {

        $IsVideo = (bool)$Item['isVideo'];
        $Url = (string)$Item['url'];
        $PreviewUrl = (string)$Item['previewUrl'];
        $Width = intval($Item['width']);
        $Height = intval($Item['height']);

        $Html = '';

        $Html .= '<a href="' . esc_url($Url) . '" class="tkag-tile" data-type="' . ($IsVideo ? 'video' : 'image') . '" style="display:block!important;position:relative!important;flex:' . $FlexGrow . ' 1 0!important;height:100%!important;min-width:0!important;overflow:hidden!important;border-radius:8px!important;background:#111!important;text-decoration:none!important;line-height:0!important;box-sizing:border-box!important;">';

        if ($IsVideo) {
            $Html .= '<video muted playsinline preload="metadata" style="display:block!important;width:100%!important;height:100%!important;object-fit:cover!important;transform:scale(' . $Zoom . ')!important;transform-origin:center center!important;max-width:none!important;">';
            $Html .= '<source src="' . esc_url($Url) . '">';
            $Html .= '</video>';
            $Html .= '<div style="position:absolute!important;left:50%!important;top:50%!important;transform:translate(-50%,-50%)!important;padding:10px 14px!important;border-radius:999px!important;background:rgba(0,0,0,0.65)!important;color:#fff!important;font-size:22px!important;line-height:1!important;z-index:2!important;">▶</div>';
        } else {
            $Html .= '<img src="' . esc_url($PreviewUrl) . '" loading="lazy" decoding="async" width="' . $Width . '" height="' . $Height . '" alt="" style="display:block!important;width:100%!important;height:100%!important;object-fit:cover!important;transform:scale(' . $Zoom . ')!important;transform-origin:center center!important;max-width:none!important;" />';
        }

        $Html .= '</a>';

        return $Html;
    }

    /**
     * Encodes URL path while preserving slashes.
     *
     * @param string $RelativePath
     *
     * @return string
     */
    private function EncodeRelativeUrlPath(string $RelativePath): string {

        $RelativePath = str_replace('\\', '/', $RelativePath);
        $Segments = explode('/', $RelativePath);
        $EncodedSegments = array();

        foreach ($Segments as $Segment) {
            if ($Segment === '') {
                continue;
            }

            $EncodedSegments[] = rawurlencode($Segment);
        }

        return implode('/', $EncodedSegments);
    }

    /**
     * Returns cached thumbnail URL.
     *
     * @param string $AbsolutePath
     * @param string $GalleryName
     * @param string $FileName
     *
     * @return string
     */
    private function GetThumbnailUrl(string $AbsolutePath, string $GalleryName, string $FileName): string {

        $CacheDirectory = WP_CONTENT_DIR . '/tk-auto-gallery-cache/' . $GalleryName;

        if (!is_dir($CacheDirectory)) {
            wp_mkdir_p($CacheDirectory);
        }

        $ThumbnailFileName = md5($GalleryName . '/' . $FileName) . '.jpg';
        $ThumbnailAbsolutePath = $CacheDirectory . '/' . $ThumbnailFileName;

        if (!file_exists($ThumbnailAbsolutePath)) {
            $Editor = wp_get_image_editor($AbsolutePath);

            if (is_wp_error($Editor)) {
                return '';
            }

            $Editor->resize(800, 800, false);
            $Saved = $Editor->save($ThumbnailAbsolutePath, 'image/jpeg');

            if (is_wp_error($Saved)) {
                return '';
            }
        }

        return content_url(
            'tk-auto-gallery-cache/' . $this->EncodeRelativeUrlPath($GalleryName . '/' . $ThumbnailFileName)
        );
    }

    /**
     * Renders scoped inline CSS.
     *
     * @param string $GalleryId
     *
     * @return string
     */
    private function RenderInlineCss(string $GalleryId): string {

        $Selector = '#' . esc_attr($GalleryId);

        $Css = '';

        $Css .= '<style>';
        $Css .= $Selector . ' *{box-sizing:border-box!important;}';
        $Css .= '@media(max-width:700px){';
        $Css .= $Selector . ' .tkag-row{height:var(--tkag-mobile-row-height)!important;}';
        $Css .= '}';
        $Css .= '@media(max-width:420px){';
        $Css .= $Selector . ' .tkag-row{height:auto!important;flex-wrap:wrap!important;overflow:visible!important;}';
        $Css .= $Selector . ' .tkag-tile{flex:1 1 calc(50% - var(--tkag-gap))!important;height:var(--tkag-mobile-row-height)!important;}';
        $Css .= '}';
        $Css .= '.tkag-lightbox{position:fixed!important;inset:0!important;background:rgba(0,0,0,.92)!important;z-index:999999!important;display:none!important;align-items:center!important;justify-content:center!important;padding:24px!important;}';
        $Css .= '.tkag-lightbox.tkag-open{display:flex!important;}';
        $Css .= '.tkag-lightbox img,.tkag-lightbox video{max-width:96vw!important;max-height:90vh!important;width:auto!important;height:auto!important;object-fit:contain!important;background:#000!important;}';
        $Css .= '.tkag-lightbox button{position:absolute!important;border:0!important;background:rgba(255,255,255,.14)!important;color:#fff!important;font-size:34px!important;line-height:1!important;border-radius:999px!important;width:48px!important;height:48px!important;cursor:pointer!important;}';
        $Css .= '.tkag-lightbox-close{right:18px!important;top:18px!important;}';
        $Css .= '.tkag-lightbox-prev{left:18px!important;top:50%!important;transform:translateY(-50%)!important;}';
        $Css .= '.tkag-lightbox-next{right:18px!important;top:50%!important;transform:translateY(-50%)!important;}';
        $Css .= '</style>';

        return $Css;
    }

    /**
     * Renders inline lightbox JavaScript once per page.
     *
     * @return string
     */
    private function RenderInlineLightboxScript(): string {

        static $WasRendered = false;

        if ($WasRendered) {
            return '';
        }

        $WasRendered = true;

        return <<<'HTML'
<script>
(function () {
    "use strict";

    if (window.TkAutoGalleryLightboxInitialized === true) {
        return;
    }

    window.TkAutoGalleryLightboxInitialized = true;

    var currentItems = [];
    var currentIndex = 0;
    var lightbox = null;
    var content = null;

    function createLightbox() {
        if (lightbox !== null) {
            return;
        }

        lightbox = document.createElement("div");
        lightbox.className = "tkag-lightbox";

        var closeButton = document.createElement("button");
        closeButton.className = "tkag-lightbox-close";
        closeButton.type = "button";
        closeButton.innerHTML = "×";

        var previousButton = document.createElement("button");
        previousButton.className = "tkag-lightbox-prev";
        previousButton.type = "button";
        previousButton.innerHTML = "‹";

        var nextButton = document.createElement("button");
        nextButton.className = "tkag-lightbox-next";
        nextButton.type = "button";
        nextButton.innerHTML = "›";

        content = document.createElement("div");
        content.className = "tkag-lightbox-content";

        lightbox.appendChild(closeButton);
        lightbox.appendChild(previousButton);
        lightbox.appendChild(content);
        lightbox.appendChild(nextButton);

        document.body.appendChild(lightbox);

        closeButton.addEventListener("click", function () {
            close();
        });

        previousButton.addEventListener("click", function () {
            show(currentIndex - 1);
        });

        nextButton.addEventListener("click", function () {
            show(currentIndex + 1);
        });

        lightbox.addEventListener("click", function (event) {
            if (event.target === lightbox) {
                close();
            }
        });

        document.addEventListener("keydown", function (event) {
            if (!lightbox.classList.contains("tkag-open")) {
                return;
            }

            if (event.key === "Escape") {
                close();
            }

            if (event.key === "ArrowLeft") {
                show(currentIndex - 1);
            }

            if (event.key === "ArrowRight") {
                show(currentIndex + 1);
            }
        });
    }

    function open(items, index) {
        createLightbox();

        currentItems = items;
        currentIndex = index;

        lightbox.classList.add("tkag-open");
        document.body.style.overflow = "hidden";

        show(currentIndex);
    }

    function close() {
        if (lightbox === null) {
            return;
        }

        lightbox.classList.remove("tkag-open");
        document.body.style.overflow = "";

        while (content.firstChild) {
            content.removeChild(content.firstChild);
        }
    }

    function show(index) {
        if (currentItems.length === 0) {
            return;
        }

        if (index < 0) {
            index = currentItems.length - 1;
        }

        if (index >= currentItems.length) {
            index = 0;
        }

        currentIndex = index;

        while (content.firstChild) {
            content.removeChild(content.firstChild);
        }

        var item = currentItems[currentIndex];
        var element = null;

        if (item.type === "video") {
            element = document.createElement("video");
            element.controls = true;
            element.autoplay = true;
            element.playsInline = true;

            var source = document.createElement("source");
            source.src = item.href;
            element.appendChild(source);
        } else {
            element = document.createElement("img");
            element.src = item.href;
            element.alt = "";
        }

        content.appendChild(element);
    }

    document.addEventListener("click", function (event) {
        var link = event.target.closest(".tkag-tile");

        if (link === null) {
            return;
        }

        var gallery = link.closest(".tkag-justified-gallery");

        if (gallery === null) {
            return;
        }

        event.preventDefault();

        var links = Array.prototype.slice.call(gallery.querySelectorAll(".tkag-tile"));
        var items = [];
        var index = 0;

        for (var i = 0; i < links.length; i++) {
            items.push({
                href: links[i].getAttribute("href"),
                type: links[i].getAttribute("data-type")
            });

            if (links[i] === link) {
                index = i;
            }
        }

        open(items, index);
    });
})();
</script>
HTML;
    }
}

new TkAutoGalleryPlugin();