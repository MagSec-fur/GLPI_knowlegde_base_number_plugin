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
        ],
        'pl_PL' => [
            'Knowledge Item Number' => 'Numer pozycji wiedzy',
            'Automatically generated after saving' => 'Automatycznie generowane po zapisaniu'
        ],
        'de_DE' => [
            'Knowledge Item Number' => 'Wissensartikelnummer',
            'Automatically generated after saving' => 'Automatisch generiert nach dem Speichern'
        ],
        'fr_FR' => [
            'Knowledge Item Number' => 'Numéro d\'article de connaissance',
            'Automatically generated after saving' => 'Généré automatiquement après enregistrement'
        ],
        'es_ES' => [
            'Knowledge Item Number' => 'Número de artículo de conocimiento',
            'Automatically generated after saving' => 'Generado automáticamente después de guardar'
        ],
        'it_IT' => [
            'Knowledge Item Number' => 'Numero elemento conoscenza',
            'Automatically generated after saving' => 'Generato automaticamente dopo il salvataggio'
        ],
        'pt_PT' => [
            'Knowledge Item Number' => 'Número do item de conhecimento',
            'Automatically generated after saving' => 'Gerado automaticamente após salvar'
        ],
        'ru_RU' => [
            'Knowledge Item Number' => 'Номер элемента знаний',
            'Automatically generated after saving' => 'Автоматически генерируется после сохранения'
        ],
        'ja_JP' => [
            'Knowledge Item Number' => 'ナレッジアイテム番号',
            'Automatically generated after saving' => '保存後に自動生成'
        ],
        'zh_CN' => [
            'Knowledge Item Number' => '知识项目编号',
            'Automatically generated after saving' => '保存后自动生成'
        ],
        'ar_SA' => [
            'Knowledge Item Number' => 'رقم عنصر المعرفة',
            'Automatically generated after saving' => 'تم إنشاؤه تلقائيًا بعد الحفظ'
        ],
        'tr_TR' => [
            'Knowledge Item Number' => 'Bilgi Öğe Numarası',
            'Automatically generated after saving' => 'Kaydetmeden sonra otomatik oluşturulur'
        ],
        'sv_SE' => [
            'Knowledge Item Number' => 'Kunskapsartikelnummer',
            'Automatically generated after saving' => 'Automatiskt genereras efter sparande'
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
        // Bepaal of het een nieuw item is (geen ID)
        $is_new_item = ($item->getID() == 0);

        // Haal vertalingen op
        $label = plugin_knowledgeautonumber_get_translation('Knowledge Item Number');
        $placeholder = plugin_knowledgeautonumber_get_translation('Automatically generated after saving');

        // Toon placeholder alleen bij nieuwe items
        $kb_number = $is_new_item ? $placeholder : ($item->fields['kb_number'] ?? '');

        echo "<div class='form-field row mb-2'>";
        echo "<label class='col-form-label col-sm-4'>$label</label>";
        echo "<div class='col-sm-8'>";
        echo "<input type='text' class='form-control' value='$kb_number' readonly>";
        echo "</div></div>";
    }
}