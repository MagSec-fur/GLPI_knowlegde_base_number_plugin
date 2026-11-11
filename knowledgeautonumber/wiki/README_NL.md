# Knowledge AutoNumber

Deze GLPI-plugin geeft elk kennisbankartikel een uniek, vast nummer in het formaat
`KI-0001`, `KI-0002`, …

Gemaakt door Destiny_fur van MagSec.

## Functies

- Een artikel krijgt zijn nummer bij het aanmaken (webinterface, REST API, imports, …).
- Nummers veranderen nooit. Een artikel bekijken of bewerken nummert niets opnieuw.
- Nummers zijn uniek, ook als meerdere artikelen tegelijk worden aangemaakt.
- Het nummer van een verwijderd artikel wordt niet hergebruikt.
- Het nummer staat boven de inhoud van het artikel en in het bewerkformulier.
- "Kennisbanknummer" is beschikbaar als zoekcriterium en kolom in de zoekmachine van GLPI en in de
  REST API.
- Een consolecommando nummert artikelen die nog geen nummer hebben.
- Vertaald in 14 talen.

## Vereisten

| Plugin | GLPI              | PHP                                            |
|--------|-------------------|------------------------------------------------|
| 1.1.0  | 10.0.x en 11.0.x  | de versie die je GLPI vereist (7.4+ / 8.2+)    |

## Installatie

De pluginmap moet `knowledgeautonumber` heten.

### Vanuit een release-archief

1. Download het archief van de [releasepagina](https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin/releases).
2. Pak het uit in de `plugins` map van je GLPI-installatie, zodat je
   `plugins/knowledgeautonumber/setup.php` krijgt.
3. Ga in GLPI naar **Instellen > Plugins**, installeer de plugin en activeer hem.

### Met git

```bash
cd /pad/naar/glpi/plugins
git clone https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin.git
mv GLPI_knowlegde_base_number_plugin/knowledgeautonumber .
rm -rf GLPI_knowlegde_base_number_plugin
```

Installeer en activeer de plugin daarna via **Instellen > Plugins**, of vanuit de GLPI-map:

```bash
php bin/console plugin:install knowledgeautonumber -u <admin login>
php bin/console plugin:activate knowledgeautonumber
```

Tijdens de installatie krijgen bestaande artikelen een nummer, oudste artikel eerst.

## Bijwerken vanaf 1.0.x

1. Vervang de map `knowledgeautonumber` door de nieuwe versie.
2. Klik in **Instellen > Plugins** op **Bijwerken** (of voer
   `php bin/console plugin:install knowledgeautonumber -u <admin login>` uit) en activeer de plugin opnieuw.

De update:

- laat alle bestaande nummers ongewijzigd;
- nummert artikelen zonder nummer, verder na het hoogste bestaande nummer;
- verwijdert de foreign key die versies tot en met 1.0.19 op de GLPI-tabel `glpi_knowbaseitems`
  aanmaakten, en voegt een unieke index op de nummers toe.

Versies tot en met 1.0.19 nummerden alle artikelen opnieuw vanaf `KI-0001` telkens als een formulier
werd getoond (issue [#6](https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin/issues/6)).
De nummers op het moment van bijwerken blijven vanaf dan behouden. Het bestand
`files/_log/knowledgeautonumber.log` kon met die versies tientallen megabytes groot worden; je kunt
het na de update verwijderen.

## Gebruik

- Maak een kennisbankartikel aan: het krijgt het volgende nummer bij het opslaan.
- Het nummer staat boven de inhoud van het artikel, en in het tabblad **Bewerken** als
  alleen-lezen veld "Kennisbanknummer".
- Zoek een artikel op nummer met het zoekcriterium "Kennisbanknummer".

### Consolecommando

Artikelen die zijn aangemaakt terwijl de plugin uit stond, hebben geen nummer. Nummer ze met:

```bash
# Alleen tonen hoeveel artikelen geen nummer hebben
php bin/console plugins:knowledgeautonumber:fill-missing --dry-run

# Nummeren (voeg -v toe om elk artikel te tonen)
php bin/console plugins:knowledgeautonumber:fill-missing
```

Dit commando wijzigt nooit bestaande nummers en begint de reeks nooit opnieuw.

## Verwijderen

Bij het verwijderen (deïnstalleren) van de plugin worden alle nummers gewist. Als je hem opnieuw
installeert, worden artikelen opnieuw genummerd vanaf `KI-0001`. Schakel de plugin uit in plaats van
hem te verwijderen als je de nummers wilt bewaren.

## Bijdragen

- Open een ticket voor elke bug/functie zodat deze besproken kan worden.
- Volg de [ontwikkelingsrichtlijnen](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html).
- Volg het [GitFlow](http://git-flow.readthedocs.io/) proces voor branching.
- Werk in een nieuwe branch op je eigen fork.
- Open een PR die door een ontwikkelaar beoordeeld zal worden.
