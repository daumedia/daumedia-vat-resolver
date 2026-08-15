# daumedia/vat-resolver

EU-Mehrwertsteuersätze und Leistungsortregeln für digitale Leistungen.

Framework-agnostisch, ohne Netzwerkzugriff im Request-Pfad, mit vollständiger
Unit-Testabdeckung.

## Warum es das gibt

Steuerlogik gehört nicht in eine Anwendung. Sie ändert sich nach einem anderen
Takt als der Rest (ein Mitgliedstaat ändert seinen Satz, wir nicht), sie muss
prüfbar sein, und sie wird von mehr als einem Produkt gebraucht. Also liegt sie
hier — mit den Sätzen in einer YAML-Datei statt im Code.

## Installation

```json
{
    "repositories": [
        { "type": "path", "url": "../vat-resolver", "options": { "symlink": true } }
    ],
    "require": { "daumedia/vat-resolver": "@dev" }
}
```

> Solange dies ein Pfad-Repository ist, müssen beide Verzeichnisse nebeneinander
> liegen. `composer.lock` speichert einen absoluten Pfad und ist deshalb nicht
> maschinenportabel. Sobald CI oder ein automatisiertes Deployment dazukommt, ist
> der Umstieg auf `{"type": "vcs"}` fällig — der Constraint `@dev` bleibt gültig.

## Benutzung

```php
use Daumedia\VatResolver\VatResolver;
use Daumedia\VatResolver\Enum\{CustomerType, ServiceType};
use Daumedia\VatResolver\Rates\YamlRateTableLoader;
use Daumedia\VatResolver\Vies\NullViesValidator;

$resolver = new VatResolver(
    (new YamlRateTableLoader())->load(),
    new NullViesValidator(),
    homeCountry: 'LU',
);

$result = $resolver->resolve('DE', CustomerType::B2C, null, ServiceType::DIGITAL);

$result->rate;              // 19.0  — Prozentpunkte, nicht 0.19
$result->basisPoints();     // 1900  — das ist die speicherbare Form
$result->taxCentsOn(1900);  // 361
$result->reverseCharge;     // false
$result->legalBasis;        // 'Art. 58 MwStSystRL — Leistungsort beim Empfänger …'
```

### Zwei Dinge, die leicht schiefgehen

**`rate` ist ein Float und darf niemals in die Datenbank.** Persistiert wird
`basisPoints()` als Integer — `1900`, nicht `19.0`. Gleitkommazahlen in
Geldspalten sind genau der Fehler, den diese Bibliothek verhindern soll.

**`homeCountry` ist ein Konstruktor-Parameter, kein Eintrag in der YAML.** Die
Satztabelle beschreibt die Welt, das Sitzland beschreibt den Anwender. Getrennt
zu halten heisst: ein Satz-Update fasst die Identität des Aufrufers nicht an, und
eine zweite Gesellschaft braucht keinen YAML-Fork.

## Die Satztabelle

`resources/vat_rates.yaml`. Kein Satz steht im Code. Ein Update ist ein reiner
Datei-Commit.

Der Loader validiert scharf und wirft beim Laden: fehlendes `meta.as_of`,
Ländercode nicht `[A-Z]{2}`, `standard` fehlend oder ausserhalb 0–30, EU-Land
unter dem Mindestnormalsatz von 15 % (Art. 97 MwStSystRL), und — der nützlichste
Fall — **Anzahl der EU-Mitgliedstaaten ≠ 27**, wodurch ein versehentlich
gelöschtes Land sofort auffliegt.

`RateTableFreshnessTest` geht rot, sobald `meta.as_of` älter als 400 Tage ist.
Das ist Absicht: ein veralteter Satz ist eine gültige Zahl und fällt sonst
niemandem auf.

> **Vor dem Livegang:** Die Sätze in der ausgelieferten Tabelle sind nicht
> maschinell gegen die Quelle abgeglichen. Sie sind vor dem ersten echten
> Zahlungslauf gegen die in `meta.source` genannte TAXUD-Liste zu prüfen.
> Die ermässigten Sätze werden von keiner Regel verwendet und sind entsprechend
> ungeprüft.

## Regelwerk

| Fall | Ergebnis |
|---|---|
| Leistungsart `PHYSICAL_EVENT` | Satz des Veranstaltungslands, **nie** Reverse Charge (Art. 53) |
| Kunde im Sitzland | Inlandssatz, unabhängig vom Kundentyp |
| Drittland | 0 %, keine EU-USt (Art. 59) |
| EU-B2B mit von VIES bestätigter USt-IdNr. | 0 %, Reverse Charge (Art. 196) |
| Alles Übrige | Satz des Kundenlands (Art. 58) |
| Unbekanntes Land / `GOODS` | Exception |

Die letzte Zeile ist Absicht. Ein geratener Satz ist eine Rechnung, die später
korrigiert werden muss; grenzüberschreitend ist das teuer.

## VIES ohne Blockade im Request-Pfad

Zwei Interfaces, absichtlich ohne Vererbungsbeziehung:

- **`ViesValidatorInterface::check()`** — der Request-Pfad. Antwortet nur aus
  Bekanntem, macht nie I/O.
- **`ViesLookupInterface::lookup()`** — der Hintergrund. Darf blockieren.

Weil das zweite das erste **nicht** erweitert, lässt sich eine blockierende
Implementierung nicht dorthin verdrahten, wo der Resolver seinen Port erwartet.
Die Regel steht damit im Typsystem statt in einem Kommentar.

Drei Stufen:

1. `VatNumberFormat::isWellFormed()` wirft syntaktischen Müll ohne jede I/O weg.
2. `CachedViesValidator` liest den Cache. Treffer → Antwort. Fehltreffer → `null`
   **und** die Nummer meldet sich über `ViesLookupQueueInterface` selbst zur
   Prüfung an.
3. `ViesCacheWarmer` ruft im Worker den echten Dienst und schreibt unter genau
   den Schlüssel, den Stufe 2 liest. (Ein Schlüsselversatz würde lautlos
   fehlschlagen — deshalb deckt ein Test den Round-Trip ab.)

**`null` heisst „unbekannt", nie „ungültig".** Ohne Bestätigung gibt es keinen
Reverse Charge. Die erste Bestellung eines nie geprüften Geschäftskunden wird
also mit Steuer berechnet — die richtige Richtung: eine Rechnung mit zu viel
Steuer ist eine Korrektur, eine mit zu wenig ist unsere Steuerschuld.

## Standortnachweise

`LocationEvidenceChecker` vergleicht Länderindizien (Rechnungsadresse, IP-Land)
und **wirft bei Widerspruch**, statt sich für eines zu entscheiden. Die
Rechnungsadresse zu nehmen würde jemanden unterbesteuern, der woanders wohnt;
das IP-Land zu nehmen würde jemanden im Urlaub überbesteuern. Beides ist falsch.

Art. 24b DVO (EU) 282/2011 verlangt bei B2C-Digitalleistungen zwei sich nicht
widersprechende Nachweise; weniger als zwei ist ebenfalls eine Exception.

## Tests

```bash
composer install
vendor/bin/phpunit
```

147 Tests. Kein Netzwerkzugriff: der einzige HTTP-Konsument bekommt seinen
PSR-18-Client injiziert, und in Tests ist das ausnahmslos `StaticHttpClient`.

## Persistierte Werte

Die Enum-Werte von `CustomerType`, `ServiceType` und `RateCategory` landen in den
Datenbanken der nutzenden Anwendungen. Ein Rename ist dort eine Migration, keine
Umbenennung hier — `PersistedValuesTest` ist die Stolperdrahtsicherung.
