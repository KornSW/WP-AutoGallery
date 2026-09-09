# [AI-Skill] KornSW AutoGallery

## 1. Zweck dieses Dokuments

Dieses Dokument beschreibt Architektur, Anforderungen, Konventionen und
Implementierungsdetails des WordPress-Plugins **KornSW AutoGallery**.

Es richtet sich primär an AI-Coding-Agents, die das Plugin später verändern,
erweitern, debuggen oder refaktorieren.

Bei Änderungen am Plugin sind die hier dokumentierten Verhaltensweisen als
bestehende Requirements zu behandeln und dürfen nicht unbeabsichtigt entfernt
oder verändert werden.

---

# 2. Ziel des Plugins

Das Plugin stellt eine möglichst leichtgewichtige, automatisch aus einem
Dateisystemordner erzeugte Mediengalerie bereit.

Die Galerie wird ausschließlich über einen WordPress-Shortcode eingebunden.

Die Medien müssen NICHT in der WordPress-Mediathek registriert sein.

Stattdessen liest das Plugin Dateien direkt aus:

    wp-content/tk-auto-gallery/

Eine Galerie entspricht einem frei wählbaren Unterpfad dieses Verzeichnisses.

Beispiel:

    wp-content/tk-auto-gallery/2026/foo/

kann eingebunden werden mit:

    [tk_auto_gallery gallery="2026/foo"]

Das Plugin unterstützt:

- Bilder
- Videos
- Wildcard-Dateifilter
- Unterordner in `gallery`
- mehrere Galerien auf derselben WordPress-Seite
- responsive Darstellung
- Thumbnail-Cache
- Lightbox
- Navigation innerhalb der Lightbox
- Video-Wiedergabe innerhalb der Lightbox
- Desktop und Mobile

Zentrales Entwicklungsziel ist:

> Möglichst wenig JavaScript, CSS, WordPress-Overhead und externe
> Abhängigkeiten.

Das Plugin soll insbesondere keine umfangreichen Gallery-Frameworks,
Page-Builder-Abhängigkeiten oder WordPress-Mediathek-Abhängigkeiten benötigen.

---

# 3. Grundarchitektur

Die Medien liegen außerhalb des Plugin-Verzeichnisses.

Struktur:

    wp-content/
    |
    +-- tk-auto-gallery/
    |   |
    |   +-- 2026/
    |       |
    |       +-- foo/
    |           +-- image-001.jpg
    |           +-- image-002.jpg
    |           +-- image-003.jpg
    |           +-- movie-001.mp4
    |
    +-- tk-auto-gallery-cache/
    |   |
    |   +-- 2026/
    |       |
    |       +-- foo/
    |           +-- <hash>.jpg
    |
    +-- plugins/
        |
        +-- tk-auto-gallery/
            +-- tk-auto-gallery.php
            +-- assets/
            +-- ...

Originalmedien:

    WP_CONTENT_DIR . '/tk-auto-gallery/...'

Thumbnail-Cache:

    WP_CONTENT_DIR . '/tk-auto-gallery-cache/...'

Das Plugin darf die Originaldateien niemals verändern.

---

# 4. Shortcode

Shortcode:

    [tk_auto_gallery]

Wichtige Parameter:

    gallery
    filter
    gap
    thumb
    row_height
    mobile_row_height
    max_zoom

Beispiel:

    [tk_auto_gallery
        gallery="2026/foo"
        filter="*.jpg|*.jpeg|*.png|*.webp|*.mp4|*.webm"
        row_height="230"
        mobile_row_height="150"
        gap="10"
        thumb="1"
    ]

---

# 5. gallery

`gallery` bezeichnet einen relativen Pfad unterhalb von:

    wp-content/tk-auto-gallery/

Beispiel:

    gallery="2026/foo"

entspricht:

    wp-content/tk-auto-gallery/2026/foo/

## WICHTIGE INVARIANTE

Slashes innerhalb von `gallery` sind ausdrücklich erlaubt und semantisch
relevant.

Folgendes darf NICHT passieren:

    2026/foo

    ->

    2026%2Ffoo

Der Slash ist ein Pfadseparator und kein Bestandteil eines einzelnen
URL-Segments.

Deshalb darf NICHT verwendet werden:

    rawurlencode($GalleryName)

Stattdessen müssen Pfade segmentweise kodiert werden.

Sinngemäß:

    explode('/', $Path)

anschließend:

    rawurlencode($Segment)

und schließlich:

    implode('/', $Segments)

Die bestehende Methode:

    EncodeRelativeUrlPath()

implementiert genau dieses Verhalten.

Diese Semantik darf bei Refactorings nicht verloren gehen.

---

# 6. Sicherheit des Galeriepfades

Der Galeriepfad wird normalisiert:

- Backslashes werden zu `/`
- führende `/` werden entfernt
- abschließende `/` werden entfernt
- `..` ist verboten

Dadurch soll verhindert werden, dass der Shortcode aus dem vorgesehenen
Gallery-Root ausbricht.

Beispielsweise ist dies ungültig:

    gallery="../../uploads"

Die Galerie muss immer unter:

    WP_CONTENT_DIR/tk-auto-gallery/

bleiben.

---

# 7. Dateifilter

Der Shortcode unterstützt Wildcard-Filter.

Beispiel:

    filter="*.jpg|*.jpeg|*.png|*.webp|*.mp4|*.webm"

Mehrere Patterns werden durch:

    |

getrennt.

Intern erfolgt der Vergleich über `fnmatch()`.

Der Vergleich soll case-insensitive erfolgen.

Damit müssen beispielsweise beide Dateien gefunden werden:

    IMG_1234.jpg
    IMG_1234.JPG

## Nicht wieder einführen

Eine frühere Implementierung versuchte Brace-Syntax wie:

    *.{jpg,jpeg,png}

zu verarbeiten.

Dies führte zu Problemen bei der Zerlegung des Filters.

Die robuste kanonische Syntax des Plugins ist deshalb:

    *.jpg|*.jpeg|*.png

---

# 8. Unterstützte Medien

Aktuell werden Bilder und Videos unterschieden.

Video-Endungen:

    mp4
    webm
    ogg
    mov

Alle anderen durch den Filter zugelassenen Dateien werden grundsätzlich als
Bild behandelt.

Bei einer zukünftigen Erweiterung sollte eine explizite Liste unterstützter
Bildformate eingeführt werden.

---

# 9. Bilddimensionen

Für Bilder werden die Originaldimensionen über:

    getimagesize()

ermittelt.

Entscheidend für das Layout ist NICHT die absolute Auflösung.

Relevant ist ausschließlich das Seitenverhältnis:

    ratio = width / height

Beispiele:

Hochkant:

    1000 x 1500
    ratio = 0.667

Quadratisch:

    1000 x 1000
    ratio = 1.0

Querformat:

    1600 x 1000
    ratio = 1.6

Panorama:

    3000 x 1000
    ratio = 3.0

## Wichtige Invariante

Eine hohe Pixelauflösung darf niemals dazu führen, dass ein Bild im Layout
größer dargestellt wird.

Ein:

    6000 x 9000

Hochkantbild muss layouttechnisch identisch zu:

    600 x 900

behandelt werden.

Beide haben:

    ratio = 0.667

Sollte ein Hochkantbild wegen seiner absoluten Auflösung eine eigene oder
übermäßig große Zeile erhalten, handelt es sich um einen Bug.

---

# 10. Row-First-Layout

Das Gallery-Layout ist bewusst **row-first**.

Es handelt sich NICHT um:

- CSS Masonry
- Pinterest-Masonry
- quadratische Grid-Kacheln
- starre CSS-Grid-Spalten

Die Galerie soll eher dem Prinzip einer "Justified Gallery" entsprechen.

Ziel:

    +-----------------------------------------------+
    |     Bild       | Bild | Bild |     Bild      |
    +-----------------------------------------------+
    |       Bild          |       Bild             |
    +-----------------------------------------------+
    |                 Panorama                      |
    +-----------------------------------------------+

Die gesamte verfügbare Breite einer normalen Zeile soll möglichst vollständig
ausgefüllt werden.

---

# 11. Zeilenhöhe

Normale Desktop-Zeilen haben eine gemeinsame Zielhöhe.

Default:

    row_height="230"

Innerhalb einer Zeile besitzen grundsätzlich alle Kacheln dieselbe Höhe.

Die Breite ergibt sich aus dem Seitenverhältnis des jeweiligen Mediums.

Dadurch entsteht ein ruhiger, blockartiger Galerieeindruck.

---

# 12. Gewünschte Bildkombinationen

Die Row-Packing-Logik soll insbesondere folgende Kombinationen ermöglichen.

## Vier Hochkantbilder

    | Portrait | Portrait | Portrait | Portrait |

## Querformat + Hochkantbilder

Beispielsweise:

    |      Landscape      | Portrait | Portrait | Portrait |

sofern die resultierenden Proportionen sinnvoll sind.

## Zwei Querformate + Hochkantbilder

Beispielsweise:

    | Landscape | Landscape | Portrait | Portrait |

## Andere sinnvolle Kombinationen

Die konkrete Anzahl ist nicht hart auf diese Beispiele beschränkt.

Ziel ist immer:

1. möglichst geschlossene Zeile
2. visuell ähnliche Zeilenhöhe
3. möglichst geringe Beschneidung
4. keine extrem schmalen oder extrem breiten Kacheln
5. maximal ungefähr vier Medien pro normaler Desktop-Zeile

---

# 13. Panorama-Sonderfall

Ein starkes Panorama darf eine eigene Zeile erhalten.

Aktueller Schwellwert ungefähr:

    ratio >= 2.45

Beispiel:

    +----------------------------------------------+
    |                  Panorama                    |
    +----------------------------------------------+

Für diesen Sonderfall darf die Zeile niedriger als die normale `row_height`
sein.

Aktuell wird ungefähr:

    row_height * 0.72

verwendet.

Dies verhindert, dass ein Panorama unnötig riesig dargestellt wird.

---

# 14. Hochkantbilder dürfen nicht allein explodieren

Ein wichtiges Requirement ist:

> Ein einzelnes Hochkantbild soll nach Möglichkeit niemals eine komplette
> Desktop-Zeile belegen.

Das würde beispielsweise aus:

    Portrait

eine riesige, stark beschnittene Kachel machen.

Deshalb existiert eine Reparaturlogik für schwache letzte Zeilen.

Eine einzelne Restkachel, die kein Panorama ist, soll möglichst in die
vorherige Zeile integriert werden.

Bei zukünftigen Verbesserungen darf diese Heuristik intelligenter werden.

Sie darf aber nicht ersatzlos entfernt werden.

---

# 15. Justification / Breitenberechnung

Die Breiten innerhalb einer Zeile werden proportional zum Seitenverhältnis
verteilt.

Konzeptionell:

    tileWidth ∝ imageWidth / imageHeight

Aktuell wird dies über `flex-grow` approximiert.

Beispiel:

    FlexGrow = Ratio * 1000

Portrait:

    ratio 0.67
    flex-grow ~670

Landscape:

    ratio 1.5
    flex-grow ~1500

Dadurch erhält ein Querformat innerhalb derselben Zeilenhöhe entsprechend mehr
Breite.

Für Hochkantbilder existiert eine Mindestgewichtung, damit diese nicht zu
extrem schmal werden.

---

# 16. Cropping und Zoom

Die Bilder verwenden:

    object-fit: cover

Dadurch darf das Bild geringfügig beschnitten werden, um die Zeile vollständig
zu schließen.

Zusätzlich darf zur Feinjustierung leicht gezoomt werden.

Zielvorgabe:

    maximal etwa 10 %

Shortcode:

    max_zoom="1.10"

Ein Wert von:

    1.10

entspricht maximal ungefähr 10 % Zoom.

Der Zoom darf ausschließlich der optischen Feinjustierung dienen.

Er darf NICHT benutzt werden, um schlechte Row-Packing-Entscheidungen zu
kaschieren.

Insbesondere darf ein einzelnes Portrait nicht durch massiven Zoom künstlich
in eine Landscape-Kachel gezwungen werden.

---

# 17. Responsive Verhalten

Desktop und größere Tablets verwenden das Row-First-Layout.

Auf kleineren Displays wird die Zeilenhöhe reduziert.

Default:

    mobile_row_height="150"

Sehr kleine Displays dürfen stärker umbrechen.

Wichtig ist:

- keine horizontale Scrollbar
- keine abgeschnittene Galerie
- ausreichend große Touch-Flächen
- Bilder dürfen nicht absurd klein werden
- Lightbox muss vollständig mobil bedienbar bleiben

Das Desktop-Layout muss nicht mathematisch identisch auf ein Smartphone
übertragen werden.

Mobile Lesbarkeit hat Vorrang.

---

# 18. Thumbnail-System

Optional:

    thumb="1"

Dann versucht das Plugin, Vorschaubilder zu erzeugen.

Cache-Root:

    wp-content/tk-auto-gallery-cache/

Die Unterordnerstruktur der Galerie wird gespiegelt.

Beispiel:

Original:

    wp-content/tk-auto-gallery/2026/foo/IMG_1234.JPG

Cache:

    wp-content/tk-auto-gallery-cache/2026/foo/<hash>.jpg

Die Thumbnail-Dateinamen werden gehasht.

Dadurch müssen problematische Originaldateinamen nicht in den Cache übernommen
werden.

---

# 19. Thumbnail-Erzeugung

Die Thumbnail-Erzeugung erfolgt über die WordPress Image Editor API:

    wp_get_image_editor()

Damit kann WordPress abhängig vom Server beispielsweise:

- GD
- Imagick

verwenden.

Das Plugin darf keine harte Abhängigkeit von Imagick voraussetzen.

Das Thumbnail wird aktuell ungefähr auf maximal:

    800 x 800

verkleinert.

Dabei wird NICHT auf ein Quadrat gecroppt.

Das Seitenverhältnis bleibt erhalten.

---

# 20. Thumbnail-Fallback

Die Thumbnail-Erzeugung darf niemals dazu führen, dass das Bild komplett
verschwindet.

Falls:

    wp_get_image_editor()

fehlschlägt oder das Bildformat serverseitig nicht verarbeitet werden kann,
muss das Originalbild verwendet werden.

Prinzip:

    Thumbnail verfügbar
        -> Thumbnail anzeigen

    Thumbnail nicht verfügbar
        -> Original anzeigen

Dies ist eine wichtige Robustheitsanforderung.

---

# 21. Lightbox

Ein Klick auf eine Kachel öffnet das Originalmedium in einer Lightbox.

Die Lightbox unterstützt:

- Bilder
- Videos
- Schließen per X
- Schließen per Klick auf Hintergrund
- ESC
- vorheriges Medium
- nächstes Medium
- Cursor links/rechts
- zyklische Navigation

Nach dem letzten Medium folgt wieder das erste.

Vor dem ersten Medium folgt das letzte.

---

# 22. Lightbox und mehrere Galerien

Der Shortcode darf mehrfach auf derselben Seite vorkommen.

Beispiel:

    [tk_auto_gallery gallery="2026/foo"]

    [tk_auto_gallery gallery="2026/bar"]

Beide Galerien müssen unabhängig funktionieren.

Beim Öffnen einer Lightbox darf die Navigation ausschließlich die Medien der
angeklickten Galerie enthalten.

Die zweite Galerie darf nicht in die erste Lightbox-Navigation hineinlaufen.

Dazu wird beim Klick der nächste:

    .tkag-justified-gallery

Container bestimmt.

Anschließend werden ausschließlich dessen:

    .tkag-tile

Elemente als Lightbox-Items verwendet.

---

# 23. Lightbox-Singleton

Die Lightbox-Infrastruktur soll pro HTML-Seite nur einmal erzeugt werden.

Dazu wird serverseitig aktuell ein statisches Flag verwendet:

    static $WasRendered

und clientseitig zusätzlich:

    window.TkAutoGalleryLightboxInitialized

Damit kann der Shortcode beliebig oft auf einer Seite verwendet werden, ohne
das globale Lightbox-JavaScript mehrfach zu initialisieren.

Dieses Verhalten muss erhalten bleiben.

---

# 24. Video-Support

Videos werden in der Galerie als Kachel behandelt.

Die Kachel verwendet ein HTML5-`video`-Element mit:

    muted
    playsinline
    preload="metadata"

Darüber liegt ein Play-Symbol.

Beim Klick wird das Video nicht direkt innerhalb der Kachel abgespielt.

Stattdessen öffnet sich die Lightbox.

Dort wird ein neues:

    <video controls autoplay playsinline>

erzeugt.

Das Video verwendet die Originaldatei.

---

# 25. Performance

Das Plugin soll auch bei Galerien mit vielen Bildern möglichst wenig Overhead
erzeugen.

Deshalb:

- keine WordPress-Mediathek-Abfragen
- keine Attachment-Posts
- keine Datenbankeinträge pro Bild
- Lazy Loading für Bilder
- `decoding="async"`
- Thumbnail-Cache
- Lightbox-JavaScript nur einmal
- keine großen JS-Frameworks
- keine jQuery-Abhängigkeit

---

# 26. CSS-Isolation

In realen WordPress-Themes können globale Styles aggressiv sein.

Beispielsweise können Themes Regeln definieren für:

    img
    a
    video
    div

oder Gallery-Klassen überschreiben.

Deshalb verwendet das Plugin für layoutkritische Eigenschaften bewusst
Inline-Styles mit:

    !important

Dies ist eine bewusste Designentscheidung.

Bei diesem Plugin ist robuste Darstellung gegenüber Theme-CSS wichtiger als
eine vollständig "saubere" CSS-Kaskade.

Ein Refactoring darf die Inline-Styles nur entfernen, wenn sichergestellt ist,
dass Themes die Galerie nicht mehr zerstören können.

---

# 27. Eindeutige Galerie-ID

Jede Shortcode-Instanz erhält eine eigene ID.

Sinngemäß:

    tkag_<unique hash>

Dadurch kann instanzspezifisches CSS sauber gescoped werden.

Dies ist insbesondere bei mehreren Galerien auf derselben Seite wichtig.

---

# 28. Sortierung

Die Dateien werden aktuell nach Dateinamen sortiert.

Sinngemäß:

    sort($Result)

Damit können Dateinamen gleichzeitig die Reihenfolge steuern.

Beispiel:

    001-image.jpg
    002-image.jpg
    003-video.mp4

Die Reihenfolge darf nicht zufällig vom Filesystem abhängen.

---

# 29. Nicht rekursive Dateisuche

Derzeit liest eine Galerie ausschließlich Dateien unmittelbar aus dem
angegebenen Galerieordner.

Beispiel:

    gallery="2026/foo"

liest:

    2026/foo/*.jpg

aber NICHT automatisch:

    2026/foo/bar/*.jpg

Unterordner können jedoch explizit adressiert werden:

    gallery="2026/foo/bar"

Soll rekursive Suche später ergänzt werden, muss sie eine explizite Option
werden und darf das bestehende Verhalten nicht stillschweigend verändern.

---

# 30. Bekannte heuristische Bereiche

Das Row-Packing ist bewusst heuristisch.

Insbesondere folgende Werte sind keine fachlich unveränderlichen Konstanten:

    Panorama threshold ~2.45
    Row close threshold ~3.35
    max items per row ~4
    Panorama height factor ~0.72

Diese Werte dürfen optimiert werden.

Die zugrunde liegenden Requirements dagegen sind stabil:

- geschlossene Zeilen
- ähnliche Zeilenhöhen
- Portraits sinnvoll gruppieren
- Panoramen dürfen allein stehen
- keine riesigen einzelnen Portraits
- maximal geringe Beschneidung
- höchstens ungefähr 10 % Feinzoom

---

# 31. Verbesserungspotenzial des Row-Packers

Bei einer zukünftigen größeren Überarbeitung sollte nicht einfach die nächste
Datei greedy in eine Zeile gelegt werden.

Eine bessere Implementierung könnte für die nächsten beispielsweise 2–6 Bilder
mehrere mögliche Kombinationen bewerten.

Eine mögliche Kostenfunktion könnte berücksichtigen:

    Abweichung von Zielzeilenhöhe
    + ungenutzte Breite
    + Crop-Kosten
    + Zoom-Kosten
    + Portrait-Einzelzeilen-Strafe
    + zu schmale Kachel-Strafe
    + zu viele Kacheln pro Zeile

Dann kann die Kombination mit den niedrigsten Kosten gewählt werden.

Dies wäre gegenüber der aktuellen Greedy-Heuristik vorzuziehen, solange das
Plugin weiterhin leichtgewichtig bleibt.

---

# 32. Wichtige Regressionstests

Nach Änderungen müssen mindestens folgende Fälle geprüft werden.

## A. Einfacher Ordner

    gallery="test"

## B. Verschachtelter Galeriepfad

    gallery="2026/foo"

Die generierte URL muss enthalten:

    /tk-auto-gallery/2026/foo/

und NICHT:

    /tk-auto-gallery/2026%2Ffoo/

## C. Großgeschriebene Erweiterung

    IMG_1234.JPG

muss bei:

    filter="*.jpg"

gefunden werden.

## D. Mehrere Dateitypen

    filter="*.jpg|*.jpeg|*.png|*.webp|*.mp4|*.webm"

## E. Vier Portraits

Sollten sinnvoll gemeinsam eine Zeile bilden.

## F. Einzelnes Portrait am Ende

Darf nach Möglichkeit keine riesige eigene Zeile erzeugen.

## G. Panorama

Darf eine einzelne, niedrigere Zeile bilden.

## H. Mehrere Shortcodes

Zwei Galerien auf derselben Seite müssen unabhängig funktionieren.

## I. Lightbox

Prüfen:

    click
    previous
    next
    ESC
    backdrop
    video
    image

## J. Thumbnail-Fehler

Wenn Thumbnail-Erzeugung fehlschlägt, muss das Original weiterhin erscheinen.

## K. Mobile

Prüfen mindestens ungefähr:

    390 px
    430 px
    768 px
    Desktop

---

# 33. Entwicklungsprinzip

Bei zukünftigen Änderungen gilt:

> Die Einfachheit des Dateisystemmodells ist ein Kernfeature.

Aus:

    Ordner + Dateien + Shortcode

soll nicht schleichend ein komplexes Gallery-CMS werden.

Features, die zwingend Datenbanktabellen, Attachment-Importe oder umfangreiche
Frontend-Frameworks erfordern, sollten nur bei einem klaren fachlichen Bedarf
eingeführt werden.

---

# 34. Aktueller Shortcode als Referenz

Empfohlene Verwendung:

    [tk_auto_gallery
        gallery="2026/foo"
        filter="*.jpg|*.jpeg|*.png|*.webp|*.mp4|*.webm"
        row_height="230"
        mobile_row_height="150"
        gap="10"
        thumb="1"
        max_zoom="1.10"
    ]

Minimal:

    [tk_auto_gallery gallery="2026/foo"]

---

# 35. Prioritäten für AI-Agents

Wenn ein AI-Agent Änderungen an diesem Plugin durchführt, gelten folgende
Prioritäten in dieser Reihenfolge:

1. Keine Sicherheitsregression beim Dateisystemzugriff.
2. Bestehende Shortcodes dürfen nicht brechen.
3. Unterordner-Slashes müssen erhalten bleiben.
4. Medien dürfen wegen Thumbnail-Problemen niemals verschwinden.
5. Mehrere Galerieinstanzen pro Seite müssen funktionieren.
6. Lightbox-Navigation darf Galeriegrenzen nicht überschreiten.
7. Row-First-Layout und geschlossene Zeilen erhalten.
8. Einzelne Portraits dürfen nicht unverhältnismäßig groß werden.
9. Responsive Mobile-Verhalten erhalten.
10. Performance und geringe Abhängigkeiten erhalten.
11. Erst danach optische oder architektonische Verbesserungen durchführen.

---

# 36. Arbeitsregel für zukünftige Änderungen

Vor einer Änderung sollte ein AI-Agent zunächst bestimmen, welcher Bereich
betroffen ist:

    Filesystem discovery
        ->
    Filter
        ->
    Media metadata
        ->
    Thumbnail generation
        ->
    Row packing
        ->
    HTML rendering
        ->
    Responsive layout
        ->
    Lightbox

Änderungen sollten möglichst nur die tatsächlich betroffene Schicht verändern.

Insbesondere sollen Layoutprobleme nicht durch Änderungen an Dateisuche oder
Thumbnail-Erzeugung "gelöst" werden.

Ebenso dürfen Probleme der Row-Packing-Heuristik nicht durch übermäßiges
`object-fit: cover` oder starken Zoom versteckt werden.