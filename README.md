# Drinkport KG - Barcode & Etiketten-System (WebApp) - v2.7.0

Dieses webbasierte System löst die vorherige Lösung ab und dient dem Erstellen, Verwalten und Hochgeschwindigkeits-Drucken von Barcode-Etiketten. 

Die Applikation basiert auf einer blitzschnellen **In-Memory/Session-Architektur**. Riesige Datensätze belasten nicht mehr die Datenbank, sondern werden hochperformant direkt aus dem Arbeitsspeicher verarbeitet.

---

## ✨ Neuheiten in Version 2.7.0
Dieses Update verbessert die Usability des Visual Designers erheblich und behebt kritische Bugs im Datenfluss:

*   **🏢 Branding-Update:** Umbenennung von „Weidlich LVS" zu „Drinkport KG" — die Anwendung ist jetzt für alle Standorte verfügbar.
*   **📧 Support-Kontakt im Footer:** Der Footer auf der Startseite enthält jetzt einen `IT-Support`-Link, der das Standard-Mailprogramm mit vorausgefüllter E-Mail öffnet (Empfänger, Betreff & Nachrichtenvorlage).
*   **🎯 Intelligentes Element-Einfügen:** Neue Elemente werden nicht mehr alle auf Position 5/5mm eingefügt, sondern diagonal gestaffelt (um je 5mm versetzt). Dadurch entstehen keine Überlappungen mehr und alle Elemente sind direkt sichtbar.
*   **⚡ Auto-Selektion neuer Elemente:** Ein neu eingefügtes Element ist sofort selektiert (blau umrandet) und liegt automatisch im Vordergrund — direkt bearbeitbar, ohne andere Elemente wegschieben zu müssen.
*   **🔼🔽 Ebenen-Verwaltung:** Neue ▲/▼-Buttons in der Objekt-Toolbar ermöglichen es, Elemente eine Ebene nach vorne oder hinten zu verschieben — perfekt für komplexe Designs mit Überlappungen.
*   **📐 Responsive Icon-Skalierung:** Die Bearbeitungs-Icons (▲, ▼, ✏️, 🗑️) skalieren sich dynamisch mit der Elementhöhe (0.45x bis 1.0x), um immer optimal lesbar zu bleiben.
*   **🔗 Navigation-Fix:** Der „Projektwahl"-Button in der Designer-Ansicht leitet jetzt zurück zur Projektliste des aktuellen Standorts — nicht mehr zur Standortwahl.
*   **🐛 Critical Bugs behoben:**
    - **Druck-Performance:** Fallback-Logik für Datensatz-Auswahl korrigiert (war `?? true`, jetzt `?? false`). Dadurch wurden bei großen Datenmengen (13.000+) unbeabsichtigt alle Datensätze zum PDF hinzugefügt, statt nur der ausgewählten.
    - **Element-Auswahl:** Einfacher Klick auf ein Element deselektiert jetzt korrekt alle anderen (war: bereits ausgewählte Elemente blieben selektiert).
    - **Canvas-Deselektierung:** Klick auf einen leeren Bereich des Canvas deselektiert jetzt alle Elemente (war: nicht möglich).

---

## ✨ Neuheiten in Version 2.6.0
Diese Version bringt mächtige Power-User-Funktionen für den Designer und eine intelligente Datenvalidierung:

*   **🖱️ Multi-Selektion & Dragging:** Wähle mehrere Objekte mit <kbd>Strg</kbd> + Klick aus und verschiebe sie gemeinsam.
*   **🛠️ Ausrichtungs-Toolbar:** Neue Werkzeuge zum linksbündigen Ausrichten, Angleichen der Breite und zur gleichmäßigen vertikalen Verteilung (inkl. +/- Abstandshalter).
*   **📏 EAN 8 & 13 Validierung:** Echtzeit-Prüfung der Ziffernanzahl im Designer, eine Zusammenfassung fehlerhafter Datensätze vor dem Druck sowie deutliche "ERROR"-Markierungen auf den Etiketten bei ungültigen Daten.
*   **📝 Textausrichtung:** Unterstützung für horizontale Ausrichtung (Links, Zentriert, Rechts) direkt in den Objekteigenschaften.
*   **💎 Branding & Polish:** Konsistentes `favicon.ico` auf allen Seiten und Upgrade auf Bootstrap Icons v1.11.3.

---

## ✨ Neuheiten in Version 2.5.0
Dieses Major-Update transformiert die Applikation in ein mandantenfähiges Verwaltungs-System für mehrere Standorte:

*   **🏢 Multi-Standort-Management:** Die Startseite bietet nun eine klare Auswahl für bis zu 18 Standorte. Projekte werden sauber nach Standorten getrennt verwaltet.
*   **🖼️ Standort-Logos:** Jeder Standort kann mit einem individuellen Logo (Pictogramm) personalisiert werden (unterstützt PNG, JPG, SVG).
*   **🔐 Geschützter Admin-Bereich:** Neuer Bereich `/admin` zur zentralen Steuerung von Standorten und Vorlagen, gesichert durch Passwortschutz.
*   **📋 Globale Vorlagen-Datenbank:** Zentrale Verwaltung von Etiketten-Maßen (Zweckform, Zweck etc.). Einmal im Admin-Bereich angelegt, stehen sie allen Projekten zur Verfügung.
*   **📏 A4-Maßprüfung:** Intelligente Echtzeit-Prüfung im Admin-Bereich stellt sicher, dass Etiketten-Layouts physisch auf einen A4-Bogen passen (210x297mm).
*   **🐛 Stabilitäts-Fixes:** Korrektur von PHP-Syntaxfehlern und Optimierung der Text-Sichtbarkeit im Dark-Mode.

---

## ✨ Neuheiten in Version 2.2.1
Dieses Update bringt Komfort-Funktionen für den Designer und verbesserte Fehlerbehebung:

*   **🖱️ Interaktives Resizing:** Objekte (Text & Barcodes) können nun direkt mit der Maus an den Ecken skaliert werden.
*   **🔢 Dezimal-Flexibilität:** In allen Eingabefeldern (Maße, Abstände, Skalierung) werden nun sowohl **Punkte als auch Kommas** gleichermaßen unterstützt.
*   **🛠️ Upload-Diagnose:** Verbessertes Error-Reporting beim CSV-Import hilft dabei, Fehler wie zu große Dateien sofort zu erkennen.

---

## ✨ Neuheiten in Version 2.2.0
Dieses Feature-Update bringt mächtige Werkzeuge für die Präzisions-Kalibrierung und Gestaltung:

*   **🎯 Kalibrierungs-Modus:** Ein zuschaltbarer 1mm-Rahmen hilft dabei, den Ausdruck millimetergenau auf dem Etikett zu prüfen.
*   **⚖️ Druck-Skalierung:** Feinjustierung der Ausgabegröße in Prozent (z.B. 99.5%). Damit lassen sich hardwarebedingte Abweichungen des Druckers perfekt ausgleichen.
*   **↕️ Senkrechter Text:** Texte und Zahlen können nun per Klick senkrecht (gestapelt) angeordnet werden – ideal für schmale Randbereiche.
*   **🎨 Erweiterte Formatierung:** Neue Editor-Optionen für **Fett** und *Kursiv* bei allen Textobjekten.
*   **Barcode-Tuning:** Die Klartextzeile unter dem Barcode kann jetzt pro Objekt ein- oder ausgeblendet werden.
*   **💾 Pro-Projekt-Settings:** Alle Kalibrierungs- und Formatierungseinstellungen werden dauerhaft im Projekt gespeichert.

---

## ✨ Neuheiten in Version 2.1.1
Dieses Wartungs- und Performance-Update verbessert die Stabilität auf modernen Server-Umgebungen:

*   **🚀 High-Performance Datentabelle:** Die Suche in großen Datensätzen (13.000+ Zeilen) friert die Webseite nicht mehr ein. Durch intelligentes *Debouncing* und optimierte DOM-Zugriffe reagiert der Filter jetzt extrem flüssig.
*   **🐘 PHP 8.4 Ready:** Kompatibilitäts-Fix für `str_getcsv()`. Die Applikation ist nun für zukünftige PHP-Versionen gerüstet und wirft keine "Deprecated"-Warnungen mehr.
*   **📂 Pfad-Stabilität:** Verbesserte CSV-Parsing-Logik, die nun auch problemlos Backslashes (z.B. in Windows-Dateipfaden) innerhalb der Datenfelder verarbeitet.
*   **🏷️ Neues Markendesign:** Integration des offiziellen `barcode_green.ico` Favicons für alle Ansichten.
*   **🧹 Datenbank-Cleanup:** Bereinigung veralteter SQL-Befehle im Reload-Prozess für eine saubere Struktur.

---

## ✨ Neuheiten in Version 2.1.0
Dieses Update fokussierte sich auf maximale Benutzerfreundlichkeit im Designer und volle Unterstützung für Spezial-Hardware:

*   **⚡ Aktiver Designer:** Formate (Breite/Höhe) ändern sich jetzt in Echtzeit beim Tippen. Kein Neuladen mehr nötig!
*   **🔍 Power-Zoom (Auto-Scaling):** Winzige Etiketten (z.B. 12mm P-Touch) werden automatisch bis zu 5-fach vergrößert dargestellt.
*   **🔳 QR-Code Perfektion:** Automatische 1:1 Synchronisierung und verzerrungsfreies Rendering (kein "Eiern" mehr).
*   **📎 Vorlagen-Gedächtnis:** Das System merkt sich nun pro Projekt, welches Template (Zweckform, Brother etc.) als Basis dient.
*   **🌀 Rollendrucker-Support:** Die Druckvorschau erkennt automatisch Endlos-Rollen (1x1 Layout) und blendet störende Bogen-Optionen aus.
*   **🛡️ Cache-Isolation:** Projekte beeinflussen sich nicht mehr gegenseitig durch Browser-Autovervollständigung.

---

## 🛠️ Das mentale Modell für Anwender: Ein Projekt = Ein Design

Beim Schulen und Einweisen von neuen Benutzern ist dieses Prinzip das Wichtigste:

**Was ist ein Projekt?**
Ein Projekt ist praktisch wie ein **"Ordner für einen bestimmten Einsatzzweck"** (z.B. ein Projekt namens *"Große Paletten-Etiketten"* und ein anderes namens *"Kleine Regal-Labels"*). 

In so einem Projekt merkt sich die Software dauerhaft genau drei Dinge:
1. **Das Zubehör (Bogen-Layout)**: Wie groß ist das Etikett? (z.B. 100x50mm, 3-spaltig auf A4).
2. **Das Aussehen (Design-Layout)**: Wo steht der Text? Wo ist der Barcode? Welche Schriftgröße wird genutzt? (inkl. der Platzhalter).
3. **Die CSV-Spalten (Felder)**: Welche Daten gibt es grundsätzlich zum Einfügen? (Die Kopfzeilen der CSV Datei werden als Platzhalter wie `[~MatNr~]` in der Oberfläche bereitgestellt).

**Was ist ein Projekt explizit *nicht*?**
Es ist **kein Datenarchiv!** Die hochgeladenen CSV-Listen mit den echten Artikelnummern oder Adressen werden *nicht* im Projekt für die Ewigkeit gespeichert. Sie sind "Wegwerfware" und verfliegen, sobald die Arbeit erledigt und der Tab geschlossen ist.

---

## 🔄 Der tägliche Workflow für den Anwender

Wenn der Mitarbeiter seinen Dienst beginnt, sieht der Standard-Ablauf wie folgt aus:

1. **Projekt auswählen:**
   Auf der Startseite wählt der Mitarbeiter den gewünschten Einsatzzweck (das fertige "Projekt") aus.

2. **Daten "reinwerfen" (Reload CSV):**
   Er klickt rechts oben auf den Button **`Reload csv`** und lädt die heutige, tagesaktuelle Export-Liste (z.B. 13.000 Zeilen aus dem ERP-System) hoch.

3. **Auswählen & Drucken:**
   Er sieht die Daten nun im Tab "DATEN". 
   * Er kann nach relevanten Texten filtern.
   * Er wählt per Checkbox genau die Artikel aus, für die physische Aufkleber benötigt werden (Tipp: Die Master-Checkbox im Tabellenkopf wählt alle an/ab).
   * Danach klickt er auf "Drucken". Die Software generiert das PDF on-the-fly exakt aus der aktuellen Auswahl.

4. **Flüchtiger Speicher & Dauerhaftes Design:**
   Schließt der Mitarbeiter den Browser oder lässt die Session ablaufen, löschen sich die über 13.000 Artikeldaten automatisch aus dem aktiven Speicher. Es fallen keine Datenbank-Reste an. 
   **Das Wichtigste:** Das Etiketten-Layout, die gewählte Vorlage (Template) und die Projekteinstellungen warten aber brav unverändert auf den Einsatz am nächsten Tag!

5. **Spezialfall: Rollendrucker (z.B. Brother P-Touch)**
   Bei Etiketten, die nur eine Spalte und eine Zeile haben (1x1), schaltet die Druckvorschau automatisch in den "Endlos-Modus". Statt eines A4-Rasters sieht der Anwender eine klare Rollen-Vorschau ohne komplizierte Startpositions-Auswahl.

---

💡 **Muss für jede neue CSV-Datei ein neues Projekt angelegt werden?**
**Nein!** Wenn die CSV-Datei denselben Aufbau hat (also dieselben Spaltenüberschriften wie gestern), lädt der Anwender sie einfach per `Reload csv` in das *bestehende* Projekt ein. Das dortige Design bleibt erhalten und wendet sich automatisch fehlerfrei auf die tagesaktuellen Datenreihen an.

Erst wenn ein völlig neues Etikettenmaß verwendet werden soll oder eine grundlegend andere CSV-Liste bezogen wird, sollte ein neues Projekt in der Hauptansicht angelegt werden.

---

## 🪄 Vollautomatisches Platzhalter-System (Header)

Eine große Arbeitserleichterung für Anwender: **Platzhalter und Spaltennamen müssen zu keinem Zeitpunkt manuell eingetippt oder konfiguriert werden!** Das System ist darauf ausgelegt, sich selbst zu konfigurieren:

1. **Beim ersten Import:** 
   Das System liest vollautomatisch die oberste Zeile (den Header) der hochgeladenen CSV-Datei aus. Daraus generiert es für den Designer sofort fertige Klick-Bausteine (z.B. `[~MatNr~]`, `[~MHD~]`).
2. **Fehlertoleranz:**
   Sollte eine CSV-Spalte keine Überschrift haben, vergibt das System automatisch Notnamen wie "Spalte 3", damit keine Daten verloren gehen.
3. **Lebende Updates ("Reload CSV"):**
   Ändert sich der Export im ERP-System und es kommt morgen eine Spalte `Gewicht` hinzu? Kein Problem: Durch den Neulade-Vorgang ("Reload CSV") bemerkt das System das Update sofort und der neue Platzhalter `[~Gewicht~]` steht im Etikettendesigner ohne jeden Handgriff zur Verfügung.
