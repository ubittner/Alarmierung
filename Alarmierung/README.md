# Alarmierung

Zur Verwendung dieses Moduls als Privatperson, Einrichter oder Integrator wenden Sie sich bitte zunächst an den Autor.

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.  
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.  
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.  
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.


### Inhaltsverzeichnis

1. [Modulbeschreibung](#1-modulbeschreibung)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Schaubild](#3-schaubild)
4. [Auslöser](#4-auslöser)
5. [Externe Aktion](#5-externe-aktion)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)
   1. [Alarmierung schalten](#61-alarmierung-schalten)
   2. [Voralam auslösen](#62-voralarm-auslösen)
   3. [Hauptalarm auslösen](#63-hauptalarm-auslösen)
   4. [Nachalarm auslösen](#64-nachalarm-auslösen)

### 1. Modulbeschreibung

Dieses Modul steuert eine Alarmierung in [IP-Symcon](https://www.symcon.de).

### 2. Voraussetzungen

- IP-Symcon ab Version 6.1

### 3. Schaubild

```
                      +---------------------+
                      | Alarmierung (Modul) |
                      |                     |
Auslöser<-------------+ Alarm               |
                      | |                   |
                      | +->Voralarm         |<------------ Auslöser externes Modul
                      |    |                +------------> Aktion
                      |    +->Hauptalarm    |<------------ Auslöser externes Modul
                      |       |             +------------> Aktion 
                      |       +->Nachalarm  |<------------ Auslöser externes Modul
                      |                     +------------> Aktion
                      +----------+----------+
                                 |
                                 |
                                 |                        +------------------------+
                                 |                        | Alarmprotokoll (Modul) |
                                 |                        |                        |
                                 +----------------------->| Ereignisprotokoll      |
                                                          +------------------------+
```

### 4. Auslöser

Das Modul Alarmierung reagiert auf verschiedene Auslöser.  

### 5. Externe Aktion

Das Modul Alarmierung kann über eine externe Aktion geschaltet werden.  
Nachfolgendes Beispiel löst eine Alarmierung aus.

> ALM_SetAlarming(12345, true);

### 6. PHP-Befehlsreferenz

#### 6.1 Alarmierung schalten

```
ALM_SetAlarming(integer INSTANCE_ID, boolean STATE);
```

Der Befehl liefert keinen Rückgabewert.

| Parameter     | Beschreibung   | Wert                             |
|---------------|----------------|----------------------------------|
| `INSTANCE_ID` | ID der Instanz |                                  |
| `STATE`       | Status         | false = Kein Alarm, true = Alarm |

Beispiel:
> ALM_SetAlarming(12345, true);

---

#### 6.2 Voralarm auslösen

```
ALM_SetPreAlarm(integer INSTANCE_ID);
```

Der Befehl liefert keinen Rückgabewert.

| Parameter     | Beschreibung   |
|---------------|----------------|
| `INSTANCE_ID` | ID der Instanz |                                 

Beispiel:
> ALM_SetPreAlarm(12345);

---

#### 6.3 Hauptalarm auslösen

```
ALM_SetMainAlarm(integer INSTANCE_ID);
```

Der Befehl liefert keinen Rückgabewert.

| Parameter     | Beschreibung   |
|---------------|----------------|
| `INSTANCE_ID` | ID der Instanz |                                 

Beispiel:
> ALM_SetMainAlarm(12345);

---

#### 6.4 Nachalarm auslösen

```
ALM_SetPostAlarm(integer INSTANCE_ID);
```

Der Befehl liefert keinen Rückgabewert.

| Parameter     | Beschreibung   |
|---------------|----------------|
| `INSTANCE_ID` | ID der Instanz |                                 

Beispiel:
> ALM_SetPostAlarm(12345);

---