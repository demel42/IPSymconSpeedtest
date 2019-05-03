# IPSymconSpeedtest

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Module-Version](https://img.shields.io/badge/Modul_Version-1.6-blue.svg)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/126683101/shield?branch=master)](https://github.styleci.io/repos/142661222)

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

 - IP-Symcon ab Version 5<br>
   Version 4.4 mit Branch _ips_4.4_ (nur noch Fehlerkorrekturen)

## 3. Installation

### a. Betriebssystem vorbereiten

#### Linux (Raspbian u.a.) 

`sudo apt-get install python-pip`<br>
`sudo pip install speedtest-cli`

#### andere Betriebssysteme

Suche nach __speedtest-cli installieren__ im Internet bringt z.B. diese Seite: https://www.howtogeek.com/179016/how-to-test-your-internet-speed-from-the-command-line/

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
| Instanz ist deaktiviert         | boolean | false        | Instanz temporär deaktivieren |
|                                 |         |              | |
| Bevorzugter Server              | integer |              | Angabe eines spezischen Servers anstellen der automatischen Auswahl (nach Ping-Zeit) |
| zu ignorierende Server          | string  |              | Komma-separierte Liste von Server-ID's, die bei der automatischen Auswahl ignoriert werden sollen |
| Option --no-pre-allocate setzen | boolean |              | Die Option dient zur Vermeinung von Engpässen auf Systemen mit wenig Hauptspeicher |
|                                 |         |              | |
| Aktualisiere Daten ...          | integer | 60           | Aktualisierungsintervall, Angabe in Minuten |

Dіe Gesamtliste der Server erhält man mittels Shell-Kommand `speedtest-cli --list`.<br>

I.d.R ist die automatische Ermittlung der Servers völlig ausreichend. Manchmal ist es aber so, das ein Server bei guter Erreichbarkeit einen zu geringen Durchsatz bietet; dann sollte man diesen Server ignorieren.

Wenn das Updateintervall auf **0** steht, wird kein automatischer Test durchgeführt. Man kann die Funktion _Speedtest_PerformTest_ dann in einem Script zu festgelegten Zeiten durchführen.
Hinweis: ein Test dauert bis zu einer Minute, währenddessen wird die Bandbreite des Internetzugangs vollständig ausgenutzt. Daher empfiehlt sich, die Tests nicht zu häufig zu machen.


## 6. Anhang

GUIDs

- Modul: `{661B9CEA-A3E8-4CE9-8DDA-F5EA62604474}`
- Instanzen:
  - Speedtest: `{C631E099-15CB-4CF7-9E7C-C55F63912BE5}`

## 7. Versions-Historie

- 1.6 @ 03.05.2019 16:40<br>
  - Prüfung der Systemvoraussetzungen im Konfigurationsdialog

- 1.5 @ 29.03.2019 16:19<br>
  - SetValue() abgesichert

- 1.4 @ 22.03.2019 09:50<br>
  - Schalter, um ein Modul (temporär) zu deaktivieren
  - Konfigurations-Element IntervalBox -> NumberSpinner
  - Anpassung IPS 5

- 1.3 @ 22.10.2018 10:55<br>
  - Abspaltung Branch _ips_4.4_
  - Fix im Formular (_Akulisiere Daten_ fehlte)

- 1.2 @ 06.09.2018 18:59<br>
  - Versionshistorie dazu,
  - define's der Variablentypen,
  - Schaltfläche mit Link zu README.md im Konfigurationsdialog

- 1.1 @ 14.08.2018 16:51<br>
  - Option '--no-pre-allocate'

- 1.0 @ 28.07.2018<br>
  Initiale Version
