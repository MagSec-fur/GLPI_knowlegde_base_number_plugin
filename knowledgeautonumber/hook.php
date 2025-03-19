<?php

function plugin_knowledgeautonumber_get_translation($string, $language = null) {
    $translations = [
        'en_GB' => [
            'Knowledge Item Number' => 'Knowledge Item Number',
            'Automatically generated after saving' => 'Automatically generated after saving'
        ],
        'nl_NL' => [
            'Knowledge Item Number' => 'Kennisbanknummer',
            'Automatically generated after saving' => 'Automatisch gegenereerd na opslaan'
        ]
    ];

    // Bepaal de huidige taal van GLPI
    if ($language === null) {
        $language = $_SESSION['glpilanguage'] ?? 'en_GB';
    }

    return $translations[$language][$string] ?? $string;
}

function plugin_knowledgeautonumber_pre_item_add($item) {
    if ($item instanceof KnowbaseItem && !isset($item->input['kb_number'])) {
        global $DB;

        $DB->beginTransaction();
        try {
            // Lock de sequence rij
            $DB->query("SELECT * FROM glpi_plugin_knowledgeautonumber_sequence WHERE id = 1 FOR UPDATE");

            // Haal laatste nummer op
            $iterator = $DB->request([
                'SELECT' => ['last_number'],
                'FROM' => 'glpi_plugin_knowledgeautonumber_sequence',
                'WHERE' => ['id' => 1]
            ]);
            $last_number = $iterator->current()['last_number'];

            // Genereer nieuw nummer
            $next_number = $last_number + 1;
            $kb_number = "KI-" . str_pad($next_number, 4, "0", STR_PAD_LEFT);

            // Update sequence
            $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
                'last_number' => $next_number
            ], ['id' => 1]);

            // Voeg toe aan item
            $item->input['kb_number'] = $kb_number;

            $DB->commit();

            Toolbox::logInFile("debug", "Nieuw kb_number gegenereerd: $kb_number");

        } catch (Exception $e) {
            $DB->rollback();
            Toolbox::logInFile("sql-errors", "Fout in pre_item_add: " . $e->getMessage());
        }
    }
}

function plugin_knowledgeautonumber_post_item_form($item, array $options = []) {
    if (is_array($item) && isset($item['item'])) {
        $item = $item['item'];
    }

    if ($item instanceof KnowbaseItem) {
        // Haal vertalingen op
        $label = plugin_knowledgeautonumber_get_translation('Knowledge Item Number');
        $placeholder = plugin_knowledgeautonumber_get_translation('Automatically generated after saving');

        $kb_number = $item->fields['kb_number'] ?? $placeholder;

        echo "<div class='form-field row mb-2'>";
        echo "<label class='col-form-label col-sm-4'>$label</label>";
        echo "<div class='col-sm-8'>";
        echo "<input type='text' class='form-control' value='$kb_number' readonly>";
        echo "</div></div>";
    }
}