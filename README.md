# IPSymconSpeedtest

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

_Speedtest_ von _Ookla_ (https://www.speedtest.net) bietet ein Tool zu Ermittlung der Internet-Bandbreite. Neben der Webseite gibt es auch ein Kommandozeile-Interface; dieses Interface wird mit diesem Modul genutzt.

## 2. Voraussetzungen

 - IP-Symcon ab Version 6.0

## 3. Installation

### a. Betriebssystem vorbereiten

### Standardversion speedtest-cli

#### Linux (Raspbian u.a.) 

`sudo apt-get install python-pip`<br>
`sudo pip install speedtest-cli`

#### andere Betriebssysteme

Suche nach __speedtest-cli installieren__ im Internet bringt z.B. diese Seite: https://www.howtogeek.com/179016/how-to-test-your-internet-speed-from-the-command-line/

### Original Speedtest von Ookla

Bei hohen Bandbreiten ist u.U. die Angabe vom Standard _speedtest-cli_ viel zu gering - unabhängig von der verfügbaren Rechnerleistung. Es gibt von Ookla eine eigene Version von des speedtest: https://www.speedtest.net/de/apps/cli.
DIe Installation für die wichtgsten Betriebssysteme sind hier erklärt.

### b. Laden des Moduls

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconSpeedtest.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### c. Einrichtung in IPS

In IP-Symcon nun _Instanz hinzufügen_ (_CTRL+1_) auswählen unter der Kategorie, unter der man die Instanz hinzufügen will, und Hersteller _(sonstiges)_ und als Gerät _Speedtest_ auswählen.

Wichtiger Hinweis: wesentlich für den Test ist eine ausreichenden LAN-Leistung des Servers, auf dem IPS läuft. Mit einem normalen Raspberry (3B und älter mit dem onboard 100Mbit-LAN) ermittelt der Speedtest bei mir ca. 88 Mbit/s, mit einem 3B+ (hat ein onboard Gigabit-LAN) bis 190 MBit/s und mit einem iMac gibt es an guten Tagen bis zu 220 MBit/s (der Anschluss ist ein 200 MBit/s). Die CPU-Leistung ist nicht ganz so relevant.

## 4. Funktionsreferenz

### zentrale Funktion

`boolean Speedtest_PerformTest(integer $InstanzID, integer $preferred_server, string $exclude_server)`<br>

## 5. Konfiguration:

### Variablen

| Eigenschaft                     | Typ     | Standardwert | Beschreibung |
| :------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren            | boolean | false        | Instanz temporär deaktivieren |
|                                 |         |              | |
| Bevorzugter Server              | integer |              | Angabe eines spezischen Servers anstellen der automatischen Auswahl (nach Ping-Zeit) |
|                                 |         |              | |
| - nur bei speedtest-cli -       |         |              | |
| zu ignorierende Server          | string  |              | Komma-separierte Liste von Server-ID's, die bei der automatischen Auswahl ignoriert werden sollen |
| Option --no-pre-allocate setzen | boolean |              | Die Option dient zur Vermeinung von Engpässen auf Systemen mit wenig Hauptspeicher |
|                                 |         |              | |
| Programmpfad                    | string  |              | der absolute vollständige Programmpfad, nur erforderlich bei irregulärer Installation des Testprogramms |
|                                 |         |              | |
| Aktualisiere Daten ...          | integer | 60           | Aktualisierungsintervall, Angabe in Minuten |

Dіe Gesamtliste der Server erhält man mittels Shell-Kommand `speedtest-cli --list` resp. `speedtest --servers`<br>

I.d.R ist die automatische Ermittlung der Servers völlig ausreichend. Manchmal ist es aber so, das ein Server bei guter Erreichbarkeit einen zu geringen Durchsatz bietet; dann sollte man diesen Server ignorieren.

Wenn das Updateintervall auf **0** steht, wird kein automatischer Test durchgeführt. Man kann die Funktion _Speedtest_PerformTest_ dann in einem Script zu festgelegten Zeiten durchführen.
Hinweis: ein Test dauert bis zu einer Minute, währenddessen wird die Bandbreite des Internetzugangs unter Umständen vollständig ausgenutzt. Daher empfiehlt sich, die Tests nicht zu häufig zu machen.


## 6. Anhang

GUIDs

- Modul: `{661B9CEA-A3E8-4CE9-8DDA-F5EA62604474}`
- Instanzen:
  - Speedtest: `{C631E099-15CB-4CF7-9E7C-C55F63912BE5}`

## 7. Versions-Historie

- 2.7 @ 04.07.2023 14:44
  - Vorbereitung auf IPS 7 / PHP 8.2
  - update submodule CommonStubs
    - Absicherung bei Zugriff auf Objekte und Inhalte

- 2.6.1 @ 07.10.2022 13:59
  - update submodule CommonStubs
    Fix: Update-Prüfung wieder funktionsfähig

- 2.6 @ 05.07.2022 16:48
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert
  - update submodule CommonStubs

- 2.5.2 @ 22.06.2022 10:33
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert

- 2.5.1 @ 28.05.2022 11:37
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts

- 2.5 @ 28.05.2022 08:39
  - Fix: nach IPS-Neustart wurde der Timer nicht mehr gesetzt
  - update submodule CommonStubs
  - einige Funktionen waren fehlerhafterweise "protected" und nicht "private"
  - interne Funktionen sind nun entweder private oder nur noch via IPS_RequestAction() erreichbar

- 2.4.2 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 2.4.1 @ 10.05.2022 15:06
  - update submodule CommonStubs

- 2.4 @ 06.05.2022 12:29
  - IPS-Version ist nun minimal 6.0
  - Anzeige der Modul/Bibliotheks-Informationen, Referenzen und Timer
  - Implememtierung einer Update-Logik
  - Überlagerung von Translate und Aufteilung von locale.json in 3 translation.json (Modul, libs und CommonStubs)
  - diverse interne Änderungen

- 2.3 @ 02.01.2022 11:58
  - "Original Speedtest von Ookla" akzeptiert nun die Lizenzen bei jedem Abruf
  - Anzeige der Modul/Bibliotheks-Informationen

- 2.2 @ 13.08.2021 17:36
  - Anpassungen für IPS 6
    - IPS_LogMessage(...) ersetzt durch $this->LogMessage(..., KL_MESSAGE);

- 2.1 @ 14.07.2021 18:21
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt
  - Schalter "Instanz ist deaktiviert" umbenannt in "Instanz deaktivieren"

- 2.0 @ 05.09.2020 10:43
  - LICENSE.md hinzugefügt
  - intere Funktionen sind nun "private"
  - define's durch statische Klassen-Variablen ersetzt
  - lokale Funktionen aus common.php in locale.php verlagert
  - "Original Speedtest von Ookla" als alternatives Testprogramm hinzugefügt
  - Protokollierung einer erfolgreichen Messung in den Meldungen entfernt

- 1.11 @ 01.01.2020 18:52
  - Anpassungen an IPS 5.3
    - Formular-Elemente: 'label' in 'caption' geändert
  - Schreibfehler korrigiert

- 1.10 @ 11.12.2019 08:23
  - Fix wegen strict_types=1
  - Modul-Stati für "unbekannter Server" und "Service nicht verfügbar"

- 1.9 @ 10.10.2019 17:27
  - Anpassungen an IPS 5.2
    - IPS_SetVariableProfileValues(), IPS_SetVariableProfileDigits() nur bei INTEGER, FLOAT
    - Dokumentation-URL in module.json
  - Umstellung auf strict_types=1
  - Umstellung von StyleCI auf php-cs-fixer

- 1.8 @ 09.08.2019 14:32
  - Schreibfehler korrigiert

- 1.7 @ 06.05.2019 15:32
  - Konfigurationsdialog ist reduziert, wenn Systemvoraussetzungen fehlen

- 1.6 @ 03.05.2019 16:40
  - Prüfung der Systemvoraussetzungen im Konfigurationsdialog

- 1.5 @ 29.03.2019 16:19
  - SetValue() abgesichert

- 1.4 @ 22.03.2019 09:50
  - Schalter, um ein Modul (temporär) zu deaktivieren
  - Konfigurations-Element IntervalBox -> NumberSpinner
  - Anpassung IPS 5

- 1.3 @ 22.10.2018 10:55
  - Abspaltung Branch _ips_4.4_
  - Fix im Formular (_Akulisiere Daten_ fehlte)

- 1.2 @ 06.09.2018 18:59
  - Versionshistorie dazu,
  - define's der Variablentypen,
  - Schaltfläche mit Link zu README.md im Konfigurationsdialog

- 1.1 @ 14.08.2018 16:51
  - Option '--no-pre-allocate'

- 1.0 @ 28.07.2018
  - Initiale Version
