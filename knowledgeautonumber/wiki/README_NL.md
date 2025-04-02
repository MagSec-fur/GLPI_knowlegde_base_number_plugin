# Knowledgebaseautonumber

Deze GLPI-plugin voegt een kennisdatabasetoken-ID toe voor bestaande en nieuwe kennisdatabasetitems.  
Gemaakt door Destiny_fur van MagSec  
*W.I.P.*

## Installatie wanneer je toegang hebt tot de GLPI-pluginmap

1. Download de plugin.

2. Kopieer de `knowledgeautonumber` map naar de `plugins` map van je GLPI-installatie.

3. Installeer en activeer de plugin via de pluginbeheerinterface van GLPI.

## Installeren via CLI

1. Navigeer naar je GLPI-pluginmap:

   ```bash
   cd je_glpi_plugins_map
   ```

2. Clone de repository:

   ```bash
   git clone https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin.git
   ```

3. Ga naar de pluginmap:

   ```bash
   cd GLPI_knowlegde_base_number_plugin
   ```

4. Verplaats de pluginmap:

   ```bash
   mv knowledgebasenumber ..
   ```

5. Ga terug naar de bovenliggende map:

   ```bash
   cd ..
   ```

6. Verwijder de gekloonde repositorymap:

   ```bash
   rm -rf GLPI_knowlegde_base_number_plugin
   ```

7. Installeer en activeer de plugin via de pluginbeheerinterface van GLPI.

## Gebruik

Wanneer je een kennisdatabasetitem maakt of bewerkt, wordt er een kennisdatabaset-ID gegenereerd in het formaat `KI-####`.

## Bijdragen

- Open een ticket voor elke bug/functie zodat deze besproken kan worden.
- Volg de [ontwikkelingsrichtlijnen](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html).
- Volg het [GitFlow](http://git-flow.readthedocs.io/) proces voor branching.
- Werk in een nieuwe branch op je eigen fork.
- Open een PR die door een ontwikkelaar beoordeeld zal worden.
