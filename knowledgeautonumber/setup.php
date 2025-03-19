<?php

function plugin_version_knowledgeautonumber() {
    return [
        'name'           => 'Knowledge AutoNumber',
        'version'        => '1.0.3',
        'author'         => 'Destiny_fur',
        'license'        => 'GPLv2',
        'minGlpiVersion' => '10.0.6',
        'maxGlpiVersion' => '10.0.99'
    ];
}

function plugin_init_knowledgeautonumber() {
    global $PLUGIN_HOOKS;
    
    $PLUGIN_HOOKS['csrf_compliant']['knowledgeautonumber'] = true;
    $PLUGIN_HOOKS['pre_item_add']['knowledgeautonumber'] = [
        'KnowbaseItem' => 'plugin_knowledgeautonumber_pre_item_add'
    ];
    $PLUGIN_HOOKS['post_item_form']['knowledgeautonumber'] = 'plugin_knowledgeautonumber_post_item_form';

    $PLUGIN_HOOKS['init_session']['knowledgeautonumber'] = 'plugin_knowledgeautonumber_init_session';
}



function plugin_knowledgeautonumber_init_session() {
    global $PLUGIN_HOOKS;

    // Laad vertalingen voor de plugin
    if (Session::getLoginUserID()) {
        Plugin::loadLang('knowledgeautonumber');
    }
}



function plugin_knowledgeautonumber_install() {
    global $DB;

    // Sequence tabel aanmaken
    if (!$DB->tableExists('glpi_plugin_knowledgeautonumber_sequence')) {
        $DB->query("
            CREATE TABLE `glpi_plugin_knowledgeautonumber_sequence` (
                `id` INT UNSIGNED NOT NULL PRIMARY KEY,
                `last_number` INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        ");
        // Eerste record toevoegen
        $DB->insert('glpi_plugin_knowledgeautonumber_sequence', [
            'id' => 1,
            'last_number' => 0
        ]);
    } else {
        // Reset de sequence naar 0
        $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
            'last_number' => 0
        ], ['id' => 1]);
    }

    // Voeg kb_number kolom toe
    if (!$DB->fieldExists('glpi_knowbaseitems', 'kb_number')) {
        $DB->query("ALTER TABLE `glpi_knowbaseitems` ADD COLUMN `kb_number` VARCHAR(10) DEFAULT NULL");
    }

    // Bestaande items nummeren
    $iterator = $DB->request([
        'SELECT' => ['id'],
        'FROM' => 'glpi_knowbaseitems',
        'WHERE' => ['kb_number' => null]
    ]);

    if ($iterator->count() > 0) {
        $DB->beginTransaction();
        try {
            // Start vanaf 1
            $next_number = 1;

            // Update alle items
            foreach ($iterator as $row) {
                $kb_number = "KI-" . str_pad($next_number, 4, "0", STR_PAD_LEFT);
                $DB->update('glpi_knowbaseitems', [
                    'kb_number' => $kb_number
                ], ['id' => $row['id']]);
                $next_number++;
            }

            // Update sequence
            $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
                'last_number' => $next_number - 1
            ], ['id' => 1]);

            $DB->commit();
        } catch (Exception $e) {
            $DB->rollback();
            Toolbox::logInFile("sql-errors", "Fout bij nummeren bestaande items: " . $e->getMessage());
        }
    }

    return true;
}

function plugin_knowledgeautonumber_uninstall() {
    global $DB;

    if ($DB->fieldExists('glpi_knowbaseitems', 'kb_number')) {
        $DB->query("ALTER TABLE `glpi_knowbaseitems` DROP COLUMN `kb_number`");
    }

    if ($DB->tableExists('glpi_plugin_knowledgeautonumber_sequence')) {
        $DB->query("DROP TABLE `glpi_plugin_knowledgeautonumber_sequence`");
    }

    return true;
}
?>